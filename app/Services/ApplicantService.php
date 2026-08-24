<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationEventType;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Support\SearchFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ApplicantService
{
    public function __construct(private readonly NotificationEventService $notifications) {}

    public function paginate(array $params, string $organizationId): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $sort = $this->normalizeSort($params);
        $search = SearchFilter::fromArray($params);

        $query = CampaignApplication::query()
            ->where('organization_id', $organizationId)
            ->when(($campaignId = $this->param($params, 'filter.campaignId')) && $campaignId !== 'all', fn (Builder $builder) => $builder->where('campaign_id', $campaignId))
            ->when(($status = $this->param($params, 'filter.applicantStatus')) && $status !== 'all', fn (Builder $builder) => $builder->where('applicant_status', $status))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('campaign_title', 'like', "%{$search}%");
                });
            });

        match ($sort) {
            'name' => $query->orderBy('name'),
            '-name' => $query->orderByDesc('name'),
            'donatedAt' => $query->orderBy('applied_at'),
            '-donatedAt' => $query->orderByDesc('applied_at'),
            default => $query->orderByDesc('applied_at'),
        };

        return $query->paginate($perPage);
    }

    public function create(array $attributes, string $organizationId, string $userId): CampaignApplication
    {
        return CampaignApplication::query()->create([
            'organization_id' => $organizationId,
            'campaign_id' => $this->resolveCampaignId($attributes, $organizationId),
            'name' => $attributes['name'],
            'phone' => $attributes['phone'],
            'campaign_title' => $attributes['campaignTitle'],
            'applicant_status' => $attributes['applicantStatus'],
            'applied_at' => $attributes['appliedAt'],
            'created_by' => $userId,
        ]);
    }

    public function update(CampaignApplication $application, array $attributes, string $organizationId): CampaignApplication
    {
        $previousStatus = (string) $application->applicant_status;
        $nextStatus = (string) $attributes['applicantStatus'];

        $application->update([
            'campaign_id' => $this->resolveCampaignId($attributes, $organizationId),
            'name' => $attributes['name'],
            'phone' => $attributes['phone'],
            'campaign_title' => $attributes['campaignTitle'],
            'applicant_status' => $nextStatus,
            'applied_at' => $attributes['appliedAt'],
        ]);

        if ($previousStatus !== $nextStatus && filled($application->created_by)) {
            $eventType = match ($nextStatus) {
                'accepted', 'approved' => NotificationEventType::ApplicationAccepted,
                'rejected' => NotificationEventType::ApplicationRejected,
                default => null,
            };

            if ($eventType !== null) {
                $accepted = $eventType === NotificationEventType::ApplicationAccepted;
                $this->notifications->notifyUser(
                    (string) $application->created_by,
                    $eventType,
                    $accepted ? 'تم قبول طلب التطوع' : 'تم رفض طلب التطوع',
                    $accepted
                        ? "تم قبول طلبك للتطوع في حملة {$application->campaign_title}."
                        : "لم يتم قبول طلبك للتطوع في حملة {$application->campaign_title}.",
                    'applicant',
                    'high',
                    $application->campaign_title,
                    '/applications/'.$application->id,
                    $organizationId,
                );
            }
        }

        return $application->refresh();
    }

    private function resolveCampaignId(array $attributes, string $organizationId): ?string
    {
        $campaignId = Campaign::query()
            ->where('organization_id', $organizationId)
            ->where('title', $attributes['campaignTitle'])
            ->value('id');

        return $campaignId === null ? null : (string) $campaignId;
    }

    private function normalizeSort(array $params): string
    {
        $sort = (string) ($params['sort'] ?? '');
        if ($sort !== '') {
            return $sort;
        }

        $sortBy = (string) ($params['sortBy'] ?? '');

        return match ($sortBy) {
            'date_oldest' => 'donatedAt',
            'name_asc' => 'name',
            'name_desc' => '-name',
            default => '-donatedAt',
        };
    }

    private function param(array $params, string $key): mixed
    {
        if (array_key_exists($key, $params)) {
            return $params[$key];
        }

        $flatKey = str_replace('.', '_', $key);
        if (array_key_exists($flatKey, $params)) {
            return $params[$flatKey];
        }

        return data_get($params, $key);
    }
}
