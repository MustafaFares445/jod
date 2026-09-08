<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationEventType;
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\Post;
use App\Support\SearchFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

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
            ->when(($postId = $this->param($params, 'filter.postId')) && $postId !== 'all', function (Builder $builder) use ($postId): void {
                $builder->whereNull('campaign_id')
                    ->where('request_type', 'volunteer')
                    ->where('campaign_ref', $postId);
            })
            ->when(($targetType = $this->param($params, 'filter.targetType')) && $targetType !== 'all', function (Builder $builder) use ($targetType): void {
                if ($targetType === 'campaign') {
                    $builder->whereNotNull('campaign_id');
                    return;
                }

                $builder->whereNull('campaign_id')
                    ->where('request_type', 'volunteer')
                    ->whereNotNull('campaign_ref');
            })
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

        $standaloneVolunteerPost = $this->isStandaloneVolunteerPostApplication($application);

        $application->update([
            'campaign_id' => $standaloneVolunteerPost ? null : $this->resolveCampaignId($attributes, $organizationId),
            'name' => $attributes['name'],
            'phone' => $attributes['phone'],
            'campaign_title' => $attributes['campaignTitle'],
            'applicant_status' => $nextStatus,
            'applied_at' => $attributes['appliedAt'],
        ]);

        $this->syncApplicationTargetCount($application);

        if ($previousStatus !== $nextStatus && filled($application->created_by)) {
            $eventType = match ($nextStatus) {
                'accepted', 'approved' => NotificationEventType::ApplicationAccepted,
                'rejected' => NotificationEventType::ApplicationRejected,
                default => null,
            };

            if ($eventType !== null) {
                $accepted = $eventType === NotificationEventType::ApplicationAccepted;
                $targetLabel = $standaloneVolunteerPost ? 'فرصة' : 'حملة';
                $this->notifications->notifyUser(
                    (string) $application->created_by,
                    $eventType,
                    $accepted ? 'تم قبول طلب التطوع' : 'تم رفض طلب التطوع',
                    $accepted
                        ? "تم قبول طلبك للتطوع في {$targetLabel} {$application->campaign_title}."
                        : "لم يتم قبول طلبك للتطوع في {$targetLabel} {$application->campaign_title}.",
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

    public function accept(CampaignApplication $application, string $organizationId): CampaignApplication
    {
        return $this->transition(
            $application,
            $organizationId,
            ['pending', 'under_review'],
            'accepted',
            NotificationEventType::ApplicationAccepted,
            'تم قبول طلب التطوع',
            'وافقت المنظمة على طلب تطوعك. الخطوة التالية هي بدء التواصل معك.',
        );
    }

    public function contact(CampaignApplication $application, string $organizationId): CampaignApplication
    {
        return $this->transition(
            $application,
            $organizationId,
            ['accepted', 'approved'],
            'contacting',
            NotificationEventType::ApplicationContactStarted,
            'بدأ التواصل بخصوص طلب التطوع',
            'بدأت المنظمة التواصل معك لتنسيق تفاصيل التطوع.',
        );
    }

    public function complete(CampaignApplication $application, string $organizationId): CampaignApplication
    {
        return $this->transition(
            $application,
            $organizationId,
            ['contacting'],
            'completed',
            NotificationEventType::ApplicationCompleted,
            'اكتمل طلب التطوع',
            'أكدت المنظمة اكتمال مشاركتك التطوعية. ستظهر الآن ضمن طلبات التطوع المكتملة.',
        );
    }

    public function reject(CampaignApplication $application, string $organizationId, string $reason): CampaignApplication
    {
        return $this->transition(
            $application,
            $organizationId,
            ['pending', 'under_review', 'accepted', 'approved'],
            'rejected',
            NotificationEventType::ApplicationRejected,
            'تم رفض طلب التطوع',
            "لم توافق المنظمة على طلب التطوع أو تم إيقافه قبل بدء التنفيذ. السبب: {$reason}",
            ['rejection_reason' => $reason, 'withdrawal_reason' => null],
        );
    }

    /** @param list<string> $allowedFrom */
    private function transition(
        CampaignApplication $application,
        string $organizationId,
        array $allowedFrom,
        string $nextStatus,
        NotificationEventType $eventType,
        string $title,
        string $message,
        array $attributes = [],
    ): CampaignApplication {
        if ((string) $application->organization_id !== $organizationId) {
            abort(404);
        }

        $currentStatus = (string) $application->applicant_status;
        if ($currentStatus === $nextStatus) {
            return $application->refresh();
        }

        if (! in_array($currentStatus, $allowedFrom, true)) {
            throw ValidationException::withMessages([
                'applicantStatus' => ["Application cannot transition from {$currentStatus} to {$nextStatus}."],
            ]);
        }

        $application->update(array_merge(['applicant_status' => $nextStatus], $attributes));
        $this->syncApplicationTargetCount($application);

        if (filled($application->created_by)) {
            $this->notifications->notifyUser(
                (string) $application->created_by,
                $eventType,
                $title,
                $message,
                'applicant',
                'high',
                $application->campaign_title,
                '/applications/'.$application->id,
                $organizationId,
            );
        }

        return $application->refresh();
    }

    private function isStandaloneVolunteerPostApplication(CampaignApplication $application): bool
    {
        return $application->campaign_id === null
            && $application->request_type === 'volunteer'
            && filled($application->campaign_ref);
    }

    private function syncApplicationTargetCount(CampaignApplication $application): void
    {
        if (filled($application->campaign_id)) {
            $count = CampaignApplication::query()
                ->where('campaign_id', $application->campaign_id)
                ->whereNotIn('applicant_status', ['rejected', 'withdrawn'])
                ->count();

            Campaign::query()->whereKey($application->campaign_id)->update(['applicants_count' => $count]);
            return;
        }

        if ($this->isStandaloneVolunteerPostApplication($application)) {
            $count = CampaignApplication::query()
                ->whereNull('campaign_id')
                ->where('campaign_ref', $application->campaign_ref)
                ->where('request_type', 'volunteer')
                ->whereNotIn('applicant_status', ['rejected', 'withdrawn'])
                ->count();

            Post::query()->whereKey($application->campaign_ref)->update(['applications_count' => $count]);
        }
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
