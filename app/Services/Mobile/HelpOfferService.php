<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\HelpOfferStatus;
use App\Enums\HelpRequestStatus;
use App\Enums\NotificationEventType;
use App\Models\HelpOffer;
use App\Models\Post;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class HelpOfferService
{
    public function __construct(
        private readonly HelpRequestStatusService $helpStatus,
        private readonly NotificationEventService $notifications,
    ) {}

    public function create(User $helper, string $postId, array $attributes): HelpOffer
    {
        $offer = DB::transaction(function () use ($helper, $postId, $attributes): HelpOffer {
            $post = Post::query()->whereKey($postId)->lockForUpdate()->first();
            if ($post === null || $post->type !== 'help_request' || $post->status !== 'published') {
                throw ValidationException::withMessages(['post' => ['Help offers can only be created for public help requests.']]);
            }
            if ($post->help_status?->isTerminal() || ($post->expires_at !== null && $post->expires_at->isPast())) {
                throw ValidationException::withMessages(['post' => ['This help request is no longer accepting offers.']]);
            }
            if ($post->author_id === null) throw ValidationException::withMessages(['post' => ['This help request does not have an owner who can receive offers.']]);
            if ((string) $post->author_id === (string) $helper->id) throw ValidationException::withMessages(['post' => ['You cannot offer help on your own help request.']]);
            Gate::forUser($helper)->authorize('create', [HelpOffer::class, $post]);
            $duplicate = HelpOffer::query()->where('post_id', $post->id)->where('helper_user_id', $helper->id)
                ->whereIn('status', [HelpOfferStatus::Pending->value, HelpOfferStatus::Accepted->value, HelpOfferStatus::Contacting->value, HelpOfferStatus::Agreed->value])->exists();
            if ($duplicate) throw ValidationException::withMessages(['post' => ['You already have an active help offer for this request.']]);
            return HelpOffer::query()->create([
                'post_id' => $post->id, 'helper_user_id' => $helper->id, 'post_owner_id' => $post->author_id,
                'type' => $attributes['type'], 'amount' => $attributes['amount'] ?? null,
                'description' => $attributes['description'] ?? null, 'status' => HelpOfferStatus::Pending,
                'contact_method' => $attributes['contactMethod'] ?? null, 'phone' => $attributes['phone'] ?? $helper->phone,
            ]);
        });
        $offer->load('post', 'helper', 'postOwner');
        $this->notifyOwner($offer, NotificationEventType::HelpOfferCreated, 'عرض مساعدة جديد', 'تم تقديم عرض مساعدة جديد على طلبك.');
        return $offer;
    }

    public function paginateForUser(User $user, array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $flow = (string) ($params['flow'] ?? 'made');
        return HelpOffer::query()->with(['post', 'helper', 'postOwner'])
            ->when($flow === 'received', fn (Builder $query) => $query->where('post_owner_id', $user->id), fn (Builder $query) => $query->where('helper_user_id', $user->id))
            ->when(filled($params['status'] ?? null), fn (Builder $query) => $query->where('status', $params['status']))
            ->when(filled($params['postId'] ?? null), fn (Builder $query) => $query->where('post_id', $params['postId']))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage);
    }

    public function findForUser(User $user, string $offerId): ?HelpOffer
    {
        $offer = HelpOffer::query()->with(['post', 'helper', 'postOwner'])->find($offerId);
        if ($offer === null || ! Gate::forUser($user)->allows('view', $offer)) return null;
        return $offer;
    }

    public function accept(User $actor, string $offerId): HelpOffer
    {
        $offer = $this->transition($actor, $offerId, 'accept', [HelpOfferStatus::Pending], HelpOfferStatus::Accepted, ['accepted_at' => now()]);
        $this->helpStatus->sync($offer->post);
        $this->notifyHelper($offer, NotificationEventType::HelpOfferAccepted, 'تم قبول عرض المساعدة', 'وافق صاحب الطلب على عرض المساعدة الخاص بك.');
        return $offer;
    }

    public function reject(User $actor, string $offerId, ?string $reason = null): HelpOffer
    {
        $offer = $this->transition($actor, $offerId, 'reject', [HelpOfferStatus::Pending], HelpOfferStatus::Rejected, ['rejected_at' => now(), 'rejection_reason' => $reason]);
        $this->helpStatus->sync($offer->post);
        $this->notifyHelper($offer, NotificationEventType::HelpOfferRejected, 'تم رفض عرض المساعدة', 'لم يتم قبول عرض المساعدة على هذا الطلب.');
        return $offer;
    }

    public function markContacting(User $actor, string $offerId): HelpOffer
    {
        $offer = $this->transition($actor, $offerId, 'coordinate', [HelpOfferStatus::Accepted], HelpOfferStatus::Contacting, ['contacted_at' => now()]);
        $this->helpStatus->sync($offer->post);
        $this->notifyOtherParticipant($actor, $offer, NotificationEventType::HelpOfferContactStarted, 'بدأ التواصل', 'تم تسجيل بدء التواصل لتنسيق المساعدة.');
        return $offer;
    }

    public function markAgreed(User $actor, string $offerId): HelpOffer
    {
        $offer = $this->transition($actor, $offerId, 'coordinate', [HelpOfferStatus::Contacting], HelpOfferStatus::Agreed, ['agreed_at' => now()]);
        $this->helpStatus->sync($offer->post);
        $this->notifyOtherParticipant($actor, $offer, NotificationEventType::HelpOfferAgreed, 'تم الاتفاق على المساعدة', 'تم تسجيل الاتفاق على طريقة تقديم المساعدة خارج JOD.');
        return $offer;
    }

    public function cancel(User $actor, string $offerId, string $reason): HelpOffer
    {
        $offer = DB::transaction(function () use ($actor, $offerId, $reason): HelpOffer {
            $offer = HelpOffer::query()->whereKey($offerId)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('coordinate', $offer);
            if (! in_array($offer->status, [HelpOfferStatus::Pending, HelpOfferStatus::Accepted, HelpOfferStatus::Contacting, HelpOfferStatus::Agreed], true)) $this->throwInvalidTransition($offer, HelpOfferStatus::Cancelled);
            $offer->forceFill(['status' => HelpOfferStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            return $offer->load(['post', 'helper', 'postOwner']);
        });
        $this->helpStatus->sync($offer->post);
        $this->notifyOtherParticipant($actor, $offer, NotificationEventType::HelpOfferCancelled, 'تم إلغاء عملية المساعدة', 'تم إلغاء عملية المساعدة وإعادة تقييم حالة الطلب.');
        return $offer;
    }

    public function confirmProvided(User $helper, string $offerId): HelpOffer { return $this->confirm($helper, $offerId, 'confirmProvided', 'helper_confirmed_at', NotificationEventType::HelpOfferHelperConfirmed); }
    public function confirmReceived(User $owner, string $offerId): HelpOffer { return $this->confirm($owner, $offerId, 'confirmReceived', 'receiver_confirmed_at', NotificationEventType::HelpOfferReceiverConfirmed); }

    public function updateHelpStatus(User $owner, string $postId, HelpRequestStatus $status): Post
    {
        $post = Post::query()->whereKey($postId)->where('type', 'help_request')->firstOrFail();
        Gate::forUser($owner)->authorize('markFulfilled', [HelpOffer::class, $post]);
        if ($status === HelpRequestStatus::Fulfilled) {
            $post = $this->helpStatus->fulfill($post);
            $this->notifications->notifyUser($owner, NotificationEventType::HelpRequestFulfilled, 'تم إغلاق طلب المساعدة', 'تم تسجيل أن الحاجة تمت تلبيتها بالكامل ولن يستقبل الطلب عروضاً جديدة.', 'help', 'normal', $post->title, '/posts/'.$post->id);
            return $post;
        }
        if ($status === HelpRequestStatus::Open) {
            $post = $this->helpStatus->reopen($post);
            $this->notifications->notifyUser($owner, NotificationEventType::HelpRequestReopened, 'تم فتح طلب المساعدة', 'الطلب متاح مجدداً لاستقبال عروض المساعدة.', 'help', 'normal', $post->title, '/posts/'.$post->id);
            return $post;
        }
        throw ValidationException::withMessages(['status' => ['The owner may explicitly set a help request to fulfilled or open. in_progress is derived from active accepted offers.']]);
    }

    private function transition(User $actor, string $offerId, string $ability, array $from, HelpOfferStatus $to, array $values): HelpOffer
    {
        return DB::transaction(function () use ($actor, $offerId, $ability, $from, $to, $values): HelpOffer {
            $offer = HelpOffer::query()->whereKey($offerId)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($ability, $offer);
            if (! in_array($offer->status, $from, true)) $this->throwInvalidTransition($offer, $to);
            $offer->forceFill(array_merge($values, ['status' => $to]))->save();
            return $offer->load(['post', 'helper', 'postOwner']);
        });
    }

    private function confirm(User $actor, string $offerId, string $ability, string $column, NotificationEventType $event): HelpOffer
    {
        $completedNow = false;
        $offer = DB::transaction(function () use ($actor, $offerId, $ability, $column, &$completedNow): HelpOffer {
            $offer = HelpOffer::query()->whereKey($offerId)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize($ability, $offer);
            if ($offer->status === HelpOfferStatus::Completed) return $offer;
            if ($offer->status !== HelpOfferStatus::Agreed) $this->throwInvalidTransition($offer, HelpOfferStatus::Completed);
            if ($offer->{$column} === null) $offer->forceFill([$column => now()])->save();
            $offer->refresh();
            if ($offer->helper_confirmed_at !== null && $offer->receiver_confirmed_at !== null) {
                $offer->forceFill(['status' => HelpOfferStatus::Completed, 'completed_at' => now()])->save();
                $completedNow = true;
            }
            return $offer->load(['post', 'helper', 'postOwner']);
        });
        if ($completedNow) {
            $this->helpStatus->sync($offer->post);
            $this->notifyBoth($offer, NotificationEventType::HelpOfferCompleted, 'تم إكمال عرض المساعدة', 'أكد الطرفان تقديم واستلام المساعدة. يمكنك إبقاء الطلب مفتوحاً أو تحديد أنه تمت تلبيته بالكامل.');
        } else {
            $this->notifyOtherParticipant($actor, $offer, $event, 'تم تسجيل تأكيد جديد', 'أكد الطرف الآخر تنفيذ جانبه من عملية المساعدة.');
        }
        return $offer;
    }

    private function throwInvalidTransition(HelpOffer $offer, HelpOfferStatus $to): never
    {
        throw ValidationException::withMessages(['status' => ["Help offer cannot transition from {$offer->status->value} to {$to->value}."]]);
    }

    private function notifyOwner(HelpOffer $offer, NotificationEventType $event, string $title, string $body): void
    {
        $this->notifications->notifyUser($offer->postOwner, $event, $title, $body, 'help', 'normal', $offer->post?->title, '/help-offers/'.$offer->id, null, (string) $offer->helper_user_id);
    }

    private function notifyHelper(HelpOffer $offer, NotificationEventType $event, string $title, string $body): void
    {
        $this->notifications->notifyUser($offer->helper, $event, $title, $body, 'help', 'normal', $offer->post?->title, '/help-offers/'.$offer->id, null, (string) $offer->post_owner_id);
    }

    private function notifyOtherParticipant(User $actor, HelpOffer $offer, NotificationEventType $event, string $title, string $body): void
    {
        if ((string) $actor->id === (string) $offer->helper_user_id) $this->notifyOwner($offer, $event, $title, $body);
        else $this->notifyHelper($offer, $event, $title, $body);
    }

    private function notifyBoth(HelpOffer $offer, NotificationEventType $event, string $title, string $body): void
    {
        $this->notifyOwner($offer, $event, $title, $body);
        $this->notifyHelper($offer, $event, $title, $body);
    }
}
