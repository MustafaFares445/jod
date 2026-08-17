<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ApplicantService
{
    public function paginate(array $params, string $organizationId): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $sort = $this->normalizeSort($params);
        $search = $params['searchQueries'] ?? $this->param($params, 'filter.search');

        $query = CampaignApplication::query()
            ->where('organization_id', $organizationId)
            ->when(($campaignId = $this->param($params, 'filter.campaignId')) && $campaignId !== 'all', fn (Builder $builder) => $builder->where('campaign_id', $campaignId))
            ->when(($status = $this->param($params, 'filter.applicantStatus')) && $status !== 'all', fn (Builder $builder) => $builder->where('applicant_status', $status))
            ->when($search && $search !== 'all', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
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
        return CampaignApplication::create([
            'organization_id' => $organizationId,
            'campaign_id' => $this->resolveCampaignId($attributes['campaignTitle'], $organizationId),
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'phone' => $attributes['phone'] ?? null,
            'campaign_title' => $attributes['campaignTitle'],
            'applicant_status' => $attributes['applicantStatus'] ?? $attributes['amountOrType'] ?? 'pending',
            'applied_at' => $attributes['appliedAt'] ?? now(),
            'city' => $attributes['city'] ?? null,
            'source' => $attributes['source'] ?? null,
            'campaign_ref' => $attributes['campaignRef'] ?? null,
            'assigned_to' => $attributes['assignedTo'] ?? null,
            'internal_notes' => $attributes['internalNotes'] ?? null,
            'request_type' => $attributes['requestType'] ?? null,
            'created_by' => $userId,
        ]);
    }

    public function update(CampaignApplication $application, array $attributes, string $organizationId): CampaignApplication
    {
        $updates = [];

        foreach ([
            'name' => 'name',
            'email' => 'email',
            'phone' => 'phone',
            'applicantStatus' => 'applicant_status',
            'appliedAt' => 'applied_at',
            'city' => 'city',
            'source' => 'source',
            'campaignRef' => 'campaign_ref',
            'assignedTo' => 'assigned_to',
            'internalNotes' => 'internal_notes',
            'requestType' => 'request_type',
        ] as $requestKey => $column) {
            if (array_key_exists($requestKey, $attributes)) {
                $updates[$column] = $attributes[$requestKey];
            }
        }

        if (array_key_exists('amountOrType', $attributes) && ! array_key_exists('applicantStatus', $attributes)) {
            $updates['applicant_status'] = $attributes['amountOrType'];
        }

        if (array_key_exists('campaignTitle', $attributes)) {
            $updates['campaign_title'] = $attributes['campaignTitle'];
            $updates['campaign_id'] = $this->resolveCampaignId($attributes['campaignTitle'], $organizationId);
        }

        $application->update($updates);

        return $application;
    }

    private function resolveCampaignId(string $campaignTitle, string $organizationId): ?string
    {
        $campaignId = Campaign::query()
            ->where('organization_id', $organizationId)
            ->where('title', $campaignTitle)
            ->value('id');

        if ($campaignId === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'campaignTitle' => ['Selected campaign does not belong to the organization.'],
            ]);
        }

        return (string) $campaignId;
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
