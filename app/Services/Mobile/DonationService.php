<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\DonationStatus;
use App\Enums\NotificationEventType;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DonationService
{
    public function __construct(private readonly NotificationEventService $notifications) {}

    public function paginatePublicForCampaign(Campaign $campaign, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));

        return Donation::query()
            ->with('creator.avatarMedia')
            ->where('campaign_id', $campaign->id)
            ->where('status', DonationStatus::Completed->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** @param array{perPage?: int, campaignId?: string, flow?: 'contributed'|'received', status?: string} $params */
    public function paginateForUser(User $user, array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $flow = (string) ($params['flow'] ?? 'contributed');
        $query = Donation::query()->with(['campaign.organization','campaign.group','campaign.creator','creator']);

        if ($flow === 'received') {
            $query->where(function (Builder $received) use ($user): void {
                if (filled($user->organization_id) && Gate::forUser($user)->allows('viewAny', Donation::class)) $received->where('organization_id', $user->organization_id);
                else $received->whereRaw('1 = 0');
                $received->orWhereHas('campaign', fn (Builder $campaign) => $campaign->whereNull('organization_id')->whereNull('group_id')->where('creator_id', $user->id));
            });
        } else {
            $query->where('created_by', $user->id);
        }

        return $query
            ->when(filled($params['campaignId'] ?? null), fn (Builder $builder) => $builder->where('campaign_id', $params['campaignId']))
            ->when(filled($params['status'] ?? null), fn (Builder $builder) => $builder->where('status', $params['status']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForUser(User $user, string $donationId): ?Donation
    {
        $donation = Donation::query()->with(['campaign.organization','campaign.group','campaign.creator','creator'])->whereKey($donationId)->first();
        if ($donation === null) {
            return null;
        }

        if ((string) $donation->created_by === (string) $user->id) {
            return $donation;
        }

        $campaign = $donation->campaign;
        if ($campaign !== null && blank($campaign->organization_id) && blank($campaign->group_id) && (string) $campaign->creator_id === (string) $user->id) return $donation;
        return Gate::forUser($user)->allows('view', $donation) ? $donation : null;
    }

    /**
     * Creates a donation intent only. No campaign accounting is changed until complete().
     *
     * @param array{amount:int|float|string, contactMethod:string, paymentMethod?:string|null, phone?:string|null, city?:string|null, notes?:string|null, isAnonymous?:bool} $attributes
     */
    public function createIntent(User $user, string $campaignId, array $attributes): Donation
    {
        $donation = DB::transaction(function () use ($user, $campaignId, $attributes): Donation {
            $campaign = Campaign::query()
                ->whereKey($campaignId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($campaign === null) {
                throw ValidationException::withMessages([
                    'campaign' => ['The selected campaign is not available for donations.'],
                ]);
            }

            if (blank($campaign->organization_id) && blank($campaign->group_id) && (string) $campaign->creator_id === (string) $user->id) {
                throw ValidationException::withMessages(['campaign' => ['You cannot donate to your own personal campaign.']]);
            }

            $amount = number_format((float) $attributes['amount'], 2, '.', '');

            return Donation::query()->create([
                'organization_id' => $campaign->organization_id,
                'group_id' => $campaign->group_id,
                'campaign_id' => $campaign->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $attributes['phone'] ?? $user->phone,
                'campaign_title' => $campaign->title,
                'amount_or_type' => $amount,
                'donated_at' => now(),
                'city' => $attributes['city'] ?? $user->city,
                'source' => 'mobile_app',
                'payment_method' => $attributes['paymentMethod'] ?? null,
                'status' => DonationStatus::Pending,
                'contact_method' => $attributes['contactMethod'],
                'notes' => $attributes['notes'] ?? null,
                'campaign_ref' => $campaign->id,
                'assigned_to' => null,
                'internal_notes' => null,
                'created_by' => $user->id,
                'is_anonymous' => (bool) ($attributes['isAnonymous'] ?? false),
            ]);
        });

        $campaign = $donation->campaign()->with(['organization','group','creator'])->firstOrFail();
        $donation->setRelation('campaign', $campaign);
        $formattedAmount = number_format((float) $donation->amount_or_type, 2);
        $managerLabel = $this->managerLabel($campaign);

        $this->notifications->notifyUser(
            $user,
            NotificationEventType::DonationIntentCreated,
            'تم تسجيل طلب التبرع',
            "تم تسجيل رغبتك بالتبرع بقيمة {$formattedAmount} لحملة {$campaign->title}. بانتظار موافقة {$managerLabel}، ثم يبدأ التواصل والتنسيق.",
            'donation',
            'normal',
            $campaign->title,
            '/campaigns/'.$campaign->id,
            filled($campaign->organization_id) ? (string) $campaign->organization_id : null,
        );

        if (filled($campaign->group_id)) {
            $group = \App\Models\Group::query()->find($campaign->group_id);
            if ($group !== null) {
                $this->notifications->notifyUser($group->owner_id, NotificationEventType::DonationIntentCreated, 'طلب تبرع جديد', "أرسل {$user->name} طلب تبرع بقيمة {$formattedAmount} لحملة {$campaign->title} التابعة لفريق {$group->name}.", 'donation', 'high', $campaign->title, "/groups/{$group->id}?tab=donations", null, (string) $user->id);
            }
        } elseif (filled($campaign->organization_id)) {
            $this->notifications->notifyOrganization((string) $campaign->organization_id, NotificationEventType::DonationIntentCreated, 'طلب تبرع جديد', "يوجد طلب تبرع جديد بقيمة {$formattedAmount} لحملة {$campaign->title}.", 'donation', 'high', $campaign->title, '/org/donations/'.$donation->id, (string) $user->id);
        } elseif (filled($campaign->creator_id)) {
            $this->notifications->notifyUser($campaign->creator_id, NotificationEventType::DonationIntentCreated, 'طلب تبرع جديد لحملتك', "أرسل {$user->name} طلب تبرع بقيمة {$formattedAmount} لحملة {$campaign->title}.", 'donation', 'high', $campaign->title, "/my-campaigns/{$campaign->id}", null, (string) $user->id);
        }

        return $donation->load(['campaign.organization','campaign.group','campaign.creator','creator']);
    }

    /** @deprecated Use createIntent(). */
    public function record(User $user, string $campaignId, array $attributes): Donation
    {
        if (! isset($attributes['contactMethod'])) {
            $attributes['contactMethod'] = 'phone';
        }

        return $this->createIntent($user, $campaignId, $attributes);
    }

    public function accept(User $actor, string $donationId): Donation
    {
        $donation = $this->transitionForOrganization($actor, $donationId, DonationStatus::Pending, DonationStatus::Accepted, [
            'accepted_at' => now(),
        ]);

        $this->notifyDonor($donation, NotificationEventType::DonationAccepted, 'تم قبول طلب التبرع', 'وافق '.$this->managerLabel($donation->campaign).' على طلب تبرعك. الخطوة التالية هي بدء التواصل لتنسيق التبرع.');

        return $donation;
    }

    public function markContacting(User $actor, string $donationId): Donation
    {
        $donation = $this->transitionForOrganization($actor, $donationId, DonationStatus::Accepted, DonationStatus::Contacting, [
            'contacted_at' => now(),
        ]);

        $this->notifyDonor($donation, NotificationEventType::DonationContactStarted, 'بدأ التواصل بخصوص تبرعك', 'بدأ '.$this->managerLabel($donation->campaign).' التواصل معك لإتمام التبرع.');

        return $donation;
    }

    public function markAgreed(User $actor, string $donationId): Donation
    {
        $donation = $this->transitionForOrganization($actor, $donationId, DonationStatus::Contacting, DonationStatus::Agreed, [
            'agreed_at' => now(),
        ]);

        $this->notifyDonor($donation, NotificationEventType::DonationAgreed, 'تم الاتفاق على التبرع', 'تم تسجيل الاتفاق على طريقة إتمام التبرع خارج JOD.');

        return $donation;
    }

    public function complete(User $actor, string $donationId, float $confirmedAmount): Donation
    {
        $goalReached = false;

        if ($confirmedAmount <= 0) {
            throw ValidationException::withMessages(['amount' => ['The confirmed received amount must be greater than zero.']]);
        }

        $donation = DB::transaction(function () use ($actor, $donationId, $confirmedAmount, &$goalReached): Donation {
            $donation = Donation::query()->whereKey($donationId)->lockForUpdate()->firstOrFail();
            $this->authorizeOrganizationUpdate($actor, $donation);

            // Idempotent completion: already-completed requests never increment twice.
            if ($donation->status === DonationStatus::Completed) {
                return $donation;
            }

            $this->requireStatus($donation, DonationStatus::Agreed);

            $campaign = Campaign::query()->whereKey($donation->campaign_id)->lockForUpdate()->firstOrFail();
            $previousRaised = (float) $campaign->raised_amount;
            $amount = round($confirmedAmount, 2);

            $hasPreviousCompletedDonation = Donation::query()
                ->where('campaign_id', $campaign->id)
                ->where('created_by', $donation->created_by)
                ->where('status', DonationStatus::Completed->value)
                ->whereKeyNot($donation->id)
                ->exists();

            $donation->forceFill([
                'status' => DonationStatus::Completed,
                'confirmed_amount' => $amount,
                'completed_at' => now(),
                'confirmed_by' => $actor->id,
            ])->save();

            $campaign->increment('raised_amount', $amount);
            if (! $hasPreviousCompletedDonation) {
                $campaign->increment('donors_count');
            }
            $campaign->refresh();

            $goal = (float) $campaign->goal_amount;
            $goalReached = $goal > 0 && $previousRaised < $goal && (float) $campaign->raised_amount >= $goal;

            return $donation;
        });

        $donation->load(['campaign.organization','campaign.group','campaign.creator','creator']);
        $this->notifyDonor($donation, NotificationEventType::DonationCompleted, 'تم تأكيد استلام تبرعك', 'أكد '.$this->managerLabel($donation->campaign).' استلام تبرعك. شكراً لمساهمتك.');

        if ($goalReached && $donation->campaign !== null) {
            $campaign = $donation->campaign;
            if (filled($campaign->organization_id)) {
                $this->notifications->notifyOrganization((string) $campaign->organization_id, NotificationEventType::CampaignGoalReached, 'الحملة وصلت إلى هدفها', "وصلت حملة {$campaign->title} إلى هدف التبرعات المحدد.", 'campaign', 'high', $campaign->title, '/org/campaigns/'.$campaign->id);
            } elseif (filled($campaign->group_id)) {
                $group = $campaign->group;
                if ($group !== null) $this->notifications->notifyUser($group->owner_id, NotificationEventType::CampaignGoalReached, 'الحملة وصلت إلى هدفها', "وصلت حملة {$campaign->title} إلى هدف التبرعات المحدد.", 'campaign', 'high', $campaign->title, "/groups/{$group->id}");
            } elseif (filled($campaign->creator_id)) {
                $this->notifications->notifyUser($campaign->creator_id, NotificationEventType::CampaignGoalReached, 'حملتك وصلت إلى هدفها', "وصلت حملة {$campaign->title} إلى هدف التبرعات المحدد. يمكنك إغلاقها عندما تنتهي من معالجة الطلبات القائمة.", 'campaign', 'high', $campaign->title, "/my-campaigns/{$campaign->id}");
            }
        }

        return $donation;
    }

    public function cancel(User $actor, string $donationId, string $reason): Donation
    {
        $donation = DB::transaction(function () use ($actor, $donationId, $reason): Donation {
            $donation = Donation::query()->whereKey($donationId)->lockForUpdate()->firstOrFail();
            $this->authorizeOrganizationUpdate($actor, $donation);

            if (! in_array($donation->status, [DonationStatus::Pending, DonationStatus::Accepted, DonationStatus::Contacting, DonationStatus::Agreed], true)) {
                throw $this->invalidTransition($donation, DonationStatus::Cancelled);
            }

            $donation->forceFill([
                'status' => DonationStatus::Cancelled,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            return $donation;
        });

        $donation->load(['campaign.organization','campaign.group','campaign.creator','creator']);
        $this->notifyDonor($donation, NotificationEventType::DonationCancelled, 'تم إلغاء طلب التبرع', 'تم إلغاء عملية التبرع ولم يتم احتساب المبلغ ضمن الحملة.');

        return $donation;
    }

    /** @param array<string,mixed> $values */
    private function transitionForOrganization(User $actor, string $donationId, DonationStatus $from, DonationStatus $to, array $values): Donation
    {
        $donation = DB::transaction(function () use ($actor, $donationId, $from, $to, $values): Donation {
            $donation = Donation::query()->whereKey($donationId)->lockForUpdate()->firstOrFail();
            $this->authorizeOrganizationUpdate($actor, $donation);
            $this->requireStatus($donation, $from);
            $donation->forceFill(array_merge($values, ['status' => $to]))->save();

            return $donation;
        });

        return $donation->load(['campaign.organization','campaign.group','campaign.creator','creator']);
    }

    private function authorizeOrganizationUpdate(User $actor, Donation $donation): void
    {
        $donation->loadMissing('campaign');
        $campaign = $donation->campaign;
        if ($campaign !== null && blank($campaign->organization_id) && blank($campaign->group_id) && (string) $campaign->creator_id === (string) $actor->id) return;
        if (filled($donation->group_id)) {
            $canManageGroup = \App\Models\GroupMember::query()
                ->where('group_id', $donation->group_id)
                ->where('user_id', $actor->id)
                ->where('status', 'active')
                ->whereIn('role', ['owner', 'admin'])
                ->exists();
            if (! $canManageGroup) abort(403, 'You are not authorized to manage this group donation.');
            return;
        }
        if (! Gate::forUser($actor)->allows('update', $donation)) abort(403, 'You are not authorized to manage this donation.');
    }

    private function requireStatus(Donation $donation, DonationStatus $expected): void
    {
        if ($donation->status !== $expected) {
            throw $this->invalidTransition($donation, $expected);
        }
    }

    private function invalidTransition(Donation $donation, DonationStatus $target): ValidationException
    {
        return ValidationException::withMessages([
            'status' => ["Donation cannot transition from {$donation->status->value} to {$target->value}."],
        ]);
    }

    private function managerLabel(?Campaign $campaign): string
    {
        if ($campaign === null) return 'إدارة الحملة';
        if (filled($campaign->organization_id)) return 'المنظمة';
        if (filled($campaign->group_id)) return 'مدير الفريق';
        return 'صاحب الحملة';
    }

    private function notifyDonor(Donation $donation, NotificationEventType $event, string $title, string $message): void
    {
        $creator = $donation->creator;
        if ($creator === null) {
            return;
        }

        $this->notifications->notifyUser(
            $creator,
            $event,
            $title,
            $message,
            'donation',
            'normal',
            $donation->campaign_title,
            '/me/donations/'.$donation->id,
            filled($donation->organization_id) ? (string) $donation->organization_id : null,
        );
    }
}
