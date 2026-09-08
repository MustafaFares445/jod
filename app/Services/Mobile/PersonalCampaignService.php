<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\NotificationEventType;
use App\Models\Campaign;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PersonalCampaignService
{
    public function __construct(private readonly NotificationEventService $notifications) {}

    public function paginateOwned(User $owner, array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        return Campaign::query()->with($this->relations())
            ->whereNull('organization_id')->whereNull('group_id')->where('creator_id', $owner->id)
            ->when(filled($params['status'] ?? null), fn (Builder $query) => $query->where('status', $params['status']))
            ->orderByDesc('created_at')->paginate($perPage);
    }

    /** @param list<UploadedFile> $images */
    public function create(User $owner, array $data, array $images = []): Campaign
    {
        $campaign = DB::transaction(function () use ($owner, $data, $images): Campaign {
            $campaign = Campaign::query()->create([
                'organization_id' => null, 'group_id' => null, 'creator_id' => $owner->id,
                'title' => $data['title'], 'summary' => $data['summary'], 'content' => $data['content'] ?? $data['summary'],
                'category_id' => $data['categoryId'], 'audience' => $data['audience'] ?? 'general', 'status' => 'pending',
                'location' => $data['location'], 'goal_amount' => $data['goalAmount'], 'raised_amount' => 0,
                'beneficiaries_count' => $data['beneficiariesCount'] ?? 1, 'donors_count' => 0, 'applicants_count' => 0,
                'start_date' => $data['startDate'] ?? now()->toDateString(), 'end_date' => $data['endDate'] ?? null, 'submitted_at' => now(),
            ]);
            $this->storeImages($campaign, $images, false);
            $this->syncPendingPost($campaign);
            return $campaign;
        });
        $this->notifySubmitted($campaign, $owner);
        return $this->load($campaign);
    }

    /** @param list<UploadedFile> $images */
    public function updateOwned(Campaign $campaign, User $owner, array $data, array $images = []): Campaign
    {
        $this->ensureOwner($campaign, $owner);
        if (! in_array((string) $campaign->status, ['pending', 'rejected'], true)) throw ValidationException::withMessages(['status' => ['Only pending or rejected personal campaigns can be edited.']]);
        DB::transaction(function () use ($campaign, $data, $images): void {
            $map = ['title'=>'title','summary'=>'summary','content'=>'content','location'=>'location','goalAmount'=>'goal_amount','beneficiariesCount'=>'beneficiaries_count','startDate'=>'start_date','endDate'=>'end_date','categoryId'=>'category_id','audience'=>'audience'];
            $attributes = [];
            foreach ($map as $input => $column) if (array_key_exists($input, $data)) $attributes[$column] = $data[$input];
            $attributes += ['status'=>'pending','submitted_at'=>now(),'reviewed_at'=>null,'reviewed_by'=>null,'rejection_reason'=>null,'suspension_reason'=>null];
            $campaign->update($attributes);
            if ($images !== []) $this->storeImages($campaign, $images, true);
            $this->syncPendingPost($campaign->refresh());
        });
        $this->notifySubmitted($campaign->refresh(), $owner);
        return $this->load($campaign->refresh());
    }

    public function closeOwned(Campaign $campaign, User $owner, string $reason): Campaign
    {
        $this->ensureOwner($campaign, $owner);
        if ((string) $campaign->status !== 'active') throw ValidationException::withMessages(['status' => ['Only active personal campaigns can be closed.']]);
        $campaign->update(['status'=>'closed','closed_at'=>now(),'closed_reason'=>$reason]);
        Post::query()->where('campaign_id', $campaign->id)->where('type', 'donation_campaign')->update(['status'=>'blocked','block_reason'=>$reason,'blocked_at'=>now(),'blocked_by'=>$owner->id]);
        $this->notifications->notifyCampaignParticipants($campaign, NotificationEventType::CampaignClosed, 'تم إغلاق الحملة', "تم إغلاق حملة {$campaign->title}. لن تستقبل الحملة طلبات تبرع جديدة، ويمكن إكمال الطلبات القائمة.", 'campaign', 'normal', $campaign->title, "/campaigns/{$campaign->id}", (string) $owner->id);
        return $this->load($campaign->refresh());
    }

    public function isPersonalCampaignPost(Post $post): bool
    {
        $post->loadMissing('campaign');
        $campaign = $post->campaign;
        return $post->type === 'donation_campaign'
            && $campaign !== null
            && blank($campaign->organization_id)
            && blank($campaign->group_id)
            && filled($campaign->creator_id);
    }

    public function applyPostModeration(Post $post, User $reviewer, string $postStatus, ?string $reason = null): bool
    {
        if (! $this->isPersonalCampaignPost($post)) return false;
        $campaign = $post->campaign;
        if ($campaign === null) return false;

        if ($postStatus === 'published') {
            if ((string) $campaign->status === 'closed') {
                throw ValidationException::withMessages(['status' => ['Closed personal campaigns cannot be republished.']]);
            }
            $campaign->update([
                'status' => 'active',
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'rejection_reason' => null,
                'suspension_reason' => null,
            ]);
            $this->notifications->notifyUser(
                $campaign->creator_id,
                NotificationEventType::CampaignPublished,
                'تمت الموافقة على حملتك',
                "تم اعتماد حملة {$campaign->title} ونشرها. أصبحت الحملة قادرة على استقبال التبرعات.",
                'campaign', 'high', $campaign->title, "/campaigns/{$campaign->id}", null, (string) $reviewer->id,
            );
            return true;
        }

        if ($postStatus === 'blocked') {
            $reason = trim((string) $reason);
            $wasActive = (string) $campaign->status === 'active';
            $campaign->update([
                'status' => $wasActive ? 'suspended' : 'rejected',
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
                'rejection_reason' => $wasActive ? null : $reason,
                'suspension_reason' => $wasActive ? $reason : null,
            ]);
            $this->notifications->notifyUser(
                $campaign->creator_id,
                $wasActive ? NotificationEventType::CampaignSuspended : NotificationEventType::CampaignRejected,
                $wasActive ? 'تم إيقاف حملتك مؤقتاً' : 'تم رفض طلب الحملة',
                $wasActive ? "تم إيقاف حملة {$campaign->title}: {$reason}" : "تم رفض حملة {$campaign->title}: {$reason}",
                'campaign', 'high', $campaign->title, "/my-campaigns/{$campaign->id}", null, (string) $reviewer->id,
            );
            return true;
        }

        return false;
    }

    public function load(Campaign $campaign): Campaign { return $campaign->load($this->relations()); }

    public function ensureOwner(Campaign $campaign, User $owner): void
    {
        $this->ensurePersonal($campaign);
        if ((string) $campaign->creator_id !== (string) $owner->id) abort(403, 'Only the personal campaign owner can perform this action.');
    }

    private function ensurePersonal(Campaign $campaign): void
    {
        if (filled($campaign->organization_id) || filled($campaign->group_id) || ! filled($campaign->creator_id)) abort(404);
    }

    private function relations(): array { return ['creator.avatarMedia','category','imageMedia','reviewedBy']; }

    /** @param list<UploadedFile> $images */
    private function storeImages(Campaign $campaign, array $images, bool $replace): void
    {
        if ($replace) foreach ($campaign->imageMedia()->get() as $media) { Storage::disk($media->disk)->delete($media->path); $media->delete(); }
        foreach (array_slice($images, 0, 10) as $position => $file) {
            $path = $file->store("media/campaign/{$campaign->id}/images", 'public');
            if ($path === false) continue;
            Media::query()->create(['model_type'=>'campaign','model_id'=>$campaign->id,'prop'=>'images','disk'=>'public','path'=>$path,'original_name'=>$file->getClientOriginalName(),'mime_type'=>$file->getMimeType(),'size'=>(int)($file->getSize()?:0),'position'=>$position]);
        }
    }

    private function notifySubmitted(Campaign $campaign, User $owner): void
    {
        $this->notifications->notifyAdmins(NotificationEventType::CampaignSubmitted, 'حملة تبرع جديدة بانتظار المراجعة', "أرسل {$owner->name} حملة «{$campaign->title}» للمراجعة ضمن إدارة المنشورات.", 'post', 'high', $campaign->title, '/dashboard/admin/posts/review?type=donation_campaign', (string) $owner->id);
        $this->notifications->notifyUser($owner, NotificationEventType::CampaignSubmitted, 'تم استلام طلب الحملة', "حملة {$campaign->title} قيد مراجعة إدارة جود.", 'campaign', 'normal', $campaign->title, "/my-campaigns/{$campaign->id}");
    }

    private function syncPendingPost(Campaign $campaign): void
    {
        $post = Post::query()->where('campaign_id', $campaign->id)->where('type', 'donation_campaign')->first();
        $values = [
            'title' => $campaign->title,
            'summary' => mb_substr((string) ($campaign->summary ?? $campaign->content ?? ''), 0, 255),
            'content' => $campaign->content ?? $campaign->summary,
            'type' => 'donation_campaign',
            'status' => 'pending',
            'location' => $campaign->location,
            'category_id' => $campaign->category_id,
            'audience' => $campaign->audience ?? 'general',
            'campaign_id' => $campaign->id,
            'organization_id' => null,
            'group_id' => null,
            'author_id' => $campaign->creator_id,
            'updated_by' => $campaign->creator_id,
            'submitted_at' => $campaign->submitted_at ?? now(),
            'reviewed_at' => null,
            'reviewed_by' => null,
            'published_at' => null,
            'block_reason' => null,
            'blocked_at' => null,
            'blocked_by' => null,
        ];
        $post ? $post->update($values) : Post::query()->create($values);
    }
}
