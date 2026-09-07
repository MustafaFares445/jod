<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\NotificationEventType;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\Post;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CampaignApplicationService
{
    public const INACTIVE_STATUSES = ['rejected', 'withdrawn'];

    public function __construct(private readonly NotificationEventService $notifications) {}

    /**
     * @param  array{page?: int, perPage?: int, campaignId?: string|null, status?: string|null}  $params
     */
    public function paginateForUser(User $user, array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));

        return CampaignApplication::query()
            ->with(['campaign.organization', 'organization'])
            ->where('created_by', $user->id)
            ->where('source', 'mobile_app')
            ->when(
                filled($params['campaignId'] ?? null),
                fn (Builder $query) => $query->where('campaign_id', $params['campaignId']),
            )
            ->when(
                filled($params['status'] ?? null),
                fn (Builder $query) => $query->where('applicant_status', $params['status']),
            )
            ->orderByDesc('applied_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForUser(User $user, string $applicationId): ?CampaignApplication
    {
        return CampaignApplication::query()
            ->with(['campaign.organization', 'organization'])
            ->where('created_by', $user->id)
            ->where('source', 'mobile_app')
            ->whereKey($applicationId)
            ->first();
    }

    /**
     * @param  array{phone?: string|null, city?: string|null}  $attributes
     */
    public function apply(User $user, string $campaignId, array $attributes): CampaignApplication
    {
        return DB::transaction(function () use ($user, $campaignId, $attributes): CampaignApplication {
            $campaign = Campaign::query()
                ->whereKey($campaignId)
                ->where('status', 'active')
                ->whereHas('posts', function (Builder $post): void {
                    $post->where('status', 'published')
                        ->where('type', 'volunteer_opportunity');
                })
                ->lockForUpdate()
                ->first();

            if ($campaign === null) {
                throw ValidationException::withMessages([
                    'campaign' => ['The selected campaign is not available for applications.'],
                ]);
            }

            $application = CampaignApplication::query()
                ->where('campaign_id', $campaign->id)
                ->where('created_by', $user->id)
                ->where('source', 'mobile_app')
                ->lockForUpdate()
                ->first();

            if ($application !== null && ! in_array($application->applicant_status, self::INACTIVE_STATUSES, true)) {
                return $application->load(['campaign.organization', 'organization']);
            }

            $values = [
                'organization_id' => $campaign->organization_id,
                'campaign_id' => $campaign->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $attributes['phone'] ?? $user->phone,
                'campaign_title' => $campaign->title,
                'applicant_status' => 'pending',
                'applied_at' => now(),
                'city' => $attributes['city'] ?? $user->city,
                'source' => 'mobile_app',
                'campaign_ref' => $campaign->id,
                'assigned_to' => null,
                'internal_notes' => null,
                'request_type' => 'volunteer',
                'created_by' => $user->id,
            ];

            if ($application === null) {
                $application = CampaignApplication::query()->create($values);
            } else {
                $application->update($values);
            }

            $this->syncApplicantCount($campaign);

            $this->notifications->notifyUser(
                $user,
                NotificationEventType::ApplicationSubmitted,
                'تم إرسال طلب التطوع',
                "تم إرسال طلبك للتطوع في حملة {$campaign->title} وهو الآن بانتظار المراجعة.",
                'applicant',
                'normal',
                $campaign->title,
                '/applications/'.$application->id,
                (string) $campaign->organization_id,
            );

            $this->notifications->notifyOrganization(
                (string) $campaign->organization_id,
                NotificationEventType::ApplicationSubmitted,
                'طلب تطوع جديد',
                "تم استلام طلب تطوع جديد من {$user->name} لحملة {$campaign->title}.",
                'applicant',
                'high',
                $user->name,
                '/org/applicants/'.$application->id,
                (string) $user->id,
            );

            return $application->refresh()->load(['campaign.organization', 'organization']);
        });
    }

    /**
     * @param  array{phone?: string|null, city?: string|null}  $attributes
     */
    public function applyToPost(User $user, string $postId, array $attributes): CampaignApplication
    {
        $post = Post::query()
            ->whereKey($postId)
            ->where('status', 'published')
            ->where('type', 'volunteer_opportunity')
            ->first();

        if ($post === null) {
            throw ValidationException::withMessages([
                'post' => ['The selected volunteer opportunity is not available for applications.'],
            ]);
        }

        if (filled($post->campaign_id)) {
            return $this->apply($user, (string) $post->campaign_id, $attributes);
        }

        if (! filled($post->organization_id)) {
            throw ValidationException::withMessages([
                'post' => ['Only organization volunteer opportunities can receive applications.'],
            ]);
        }

        return DB::transaction(function () use ($user, $post, $attributes): CampaignApplication {
            $lockedPost = Post::query()
                ->whereKey($post->id)
                ->where('status', 'published')
                ->where('type', 'volunteer_opportunity')
                ->whereNull('campaign_id')
                ->lockForUpdate()
                ->first();

            if ($lockedPost === null || ! filled($lockedPost->organization_id)) {
                throw ValidationException::withMessages([
                    'post' => ['The selected volunteer opportunity is not available for applications.'],
                ]);
            }

            $application = CampaignApplication::query()
                ->whereNull('campaign_id')
                ->where('campaign_ref', $lockedPost->id)
                ->where('created_by', $user->id)
                ->where('source', 'mobile_app')
                ->where('request_type', 'volunteer')
                ->lockForUpdate()
                ->first();

            if ($application !== null && ! in_array($application->applicant_status, self::INACTIVE_STATUSES, true)) {
                return $application->load(['campaign.organization', 'organization']);
            }

            $title = filled($lockedPost->title) ? (string) $lockedPost->title : 'فرصة تطوع';
            $values = [
                'organization_id' => $lockedPost->organization_id,
                'campaign_id' => null,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $attributes['phone'] ?? $user->phone,
                'campaign_title' => $title,
                'applicant_status' => 'pending',
                'applied_at' => now(),
                'city' => $attributes['city'] ?? $user->city,
                'source' => 'mobile_app',
                'campaign_ref' => $lockedPost->id,
                'assigned_to' => null,
                'internal_notes' => null,
                'request_type' => 'volunteer',
                'created_by' => $user->id,
            ];

            if ($application === null) {
                $application = CampaignApplication::query()->create($values);
            } else {
                $application->update($values);
            }

            $this->syncPostApplicantCount($lockedPost);

            $this->notifications->notifyUser(
                $user,
                NotificationEventType::ApplicationSubmitted,
                'تم إرسال طلب التطوع',
                "تم إرسال طلبك للتطوع في فرصة {$title} وهو الآن بانتظار المراجعة.",
                'applicant',
                'normal',
                $title,
                '/applications/'.$application->id,
                (string) $lockedPost->organization_id,
            );

            $this->notifications->notifyOrganization(
                (string) $lockedPost->organization_id,
                NotificationEventType::ApplicationSubmitted,
                'طلب تطوع جديد',
                "تم استلام طلب تطوع جديد من {$user->name} لفرصة {$title}.",
                'applicant',
                'high',
                $user->name,
                '/org/applicants/'.$application->id,
                (string) $user->id,
            );

            return $application->refresh()->load(['campaign.organization', 'organization']);
        });
    }

    public function withdraw(User $user, string $applicationId): ?CampaignApplication
    {
        return DB::transaction(function () use ($user, $applicationId): ?CampaignApplication {
            $snapshot = CampaignApplication::query()
                ->where('created_by', $user->id)
                ->where('source', 'mobile_app')
                ->whereKey($applicationId)
                ->first();

            if ($snapshot === null) {
                return null;
            }

            $campaign = $snapshot->campaign_id !== null
                ? Campaign::query()->whereKey($snapshot->campaign_id)->lockForUpdate()->first()
                : null;
            $post = $snapshot->campaign_id === null && filled($snapshot->campaign_ref)
                ? Post::query()->whereKey($snapshot->campaign_ref)->lockForUpdate()->first()
                : null;

            $application = CampaignApplication::query()
                ->where('created_by', $user->id)
                ->where('source', 'mobile_app')
                ->whereKey($applicationId)
                ->lockForUpdate()
                ->first();

            if ($application === null) {
                return null;
            }

            $withdrawableStatuses = ['pending', 'under_review', 'accepted', 'approved', 'contacting'];
            if (! in_array($application->applicant_status, $withdrawableStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => ['This volunteer application can no longer be withdrawn.'],
                ]);
            }

            $wasActive = true;
            $application->update(['applicant_status' => 'withdrawn']);

            if ($campaign !== null) {
                $this->syncApplicantCount($campaign);

                if ($wasActive) {
                    $this->notifications->notifyOrganization(
                        (string) $campaign->organization_id,
                        NotificationEventType::ApplicationWithdrawn,
                        'تم سحب طلب تطوع',
                        "قام {$user->name} بسحب طلب التطوع في حملة {$campaign->title}.",
                        'applicant',
                        'normal',
                        $user->name,
                        '/org/applicants/'.$application->id,
                        (string) $user->id,
                    );
                }
            } elseif ($post !== null) {
                $this->syncPostApplicantCount($post);

                if ($wasActive && filled($post->organization_id)) {
                    $title = filled($post->title) ? (string) $post->title : 'فرصة تطوع';
                    $this->notifications->notifyOrganization(
                        (string) $post->organization_id,
                        NotificationEventType::ApplicationWithdrawn,
                        'تم سحب طلب تطوع',
                        "قام {$user->name} بسحب طلب التطوع في فرصة {$title}.",
                        'applicant',
                        'normal',
                        $user->name,
                        '/org/applicants/'.$application->id,
                        (string) $user->id,
                    );
                }
            }

            return $application->refresh()->load(['campaign.organization', 'organization']);
        });
    }

    private function syncApplicantCount(Campaign $campaign): void
    {
        $count = CampaignApplication::query()
            ->where('campaign_id', $campaign->id)
            ->whereNotIn('applicant_status', self::INACTIVE_STATUSES)
            ->count();

        $campaign->update(['applicants_count' => $count]);
    }

    private function syncPostApplicantCount(Post $post): void
    {
        $count = CampaignApplication::query()
            ->whereNull('campaign_id')
            ->where('campaign_ref', $post->id)
            ->where('request_type', 'volunteer')
            ->whereNotIn('applicant_status', self::INACTIVE_STATUSES)
            ->count();

        $post->update(['applications_count' => $count]);
    }
}
