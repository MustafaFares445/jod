<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\NotificationEventType;
use App\Models\Campaign;
use App\Models\CampaignApplication;
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
                    $post->whereIn('status', ['published', 'approved'])
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

            $application = CampaignApplication::query()
                ->where('created_by', $user->id)
                ->where('source', 'mobile_app')
                ->whereKey($applicationId)
                ->lockForUpdate()
                ->first();

            if ($application === null) {
                return null;
            }

            $wasActive = ! in_array($application->applicant_status, self::INACTIVE_STATUSES, true);
            if ($wasActive) {
                $application->update(['applicant_status' => 'withdrawn']);
            }

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
}
