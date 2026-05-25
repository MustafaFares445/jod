<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CampaignData;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CampaignService
{
    public function paginate(array $params, int $organizationId): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $sort = $this->normalizeSort($params);

        $query = Campaign::query()
            ->where('organization_id', $organizationId)
            ->when(($status = $this->param($params, 'filter.status')) && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when(($category = $this->param($params, 'filter.category')) && $category !== 'all', fn (Builder $builder) => $builder->where('category', $category))
            ->when(($location = $this->param($params, 'filter.location')) && $location !== 'all', fn (Builder $builder) => $builder->where('location', 'like', "%{$location}%"));

        match ($sort) {
            'updatedAt' => $query->orderBy('updated_at'),
            '-updatedAt' => $query->orderByDesc('updated_at'),
            'progress' => $query->orderByRaw('CASE WHEN goal_amount > 0 THEN (raised_amount / goal_amount) ELSE 0 END ASC'),
            '-progress' => $query->orderByRaw('CASE WHEN goal_amount > 0 THEN (raised_amount / goal_amount) ELSE 0 END DESC'),
            default => $query->orderByDesc('updated_at'),
        };

        return $query->paginate($perPage);
    }

    public function create(CampaignData $data, int $organizationId): Campaign
    {
        return Campaign::create([
            'title' => $data->title,
            'summary' => $data->summary,
            'category' => $data->category,
            'status' => $data->status,
            'location' => $data->location,
            'organization_id' => $organizationId,
            'goal_amount' => $data->goalAmount,
            'beneficiaries_count' => $data->beneficiariesCount,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
        ]);
    }

    public function update(Campaign $campaign, CampaignData $data): Campaign
    {
        $campaign->update([
            'title' => $data->title,
            'summary' => $data->summary,
            'category' => $data->category,
            'status' => $data->status,
            'location' => $data->location,
            'goal_amount' => $data->goalAmount,
            'beneficiaries_count' => $data->beneficiariesCount,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
        ]);

        return $campaign;
    }

    public function close(Campaign $campaign, string $reason): Campaign
    {
        if ($campaign->status !== 'active') {
            throw ValidationException::withMessages([
                'status' => ['Only active campaigns can be closed.'],
            ]);
        }

        $campaign->update([
            'status' => 'closed',
            'close_reason' => $reason,
            'closed_at' => now(),
        ]);

        return $campaign;
    }

    public function delete(Campaign $campaign): void
    {
        $campaign->delete();
    }

    private function normalizeSort(array $params): string
    {
        $sort = (string) ($params['sort'] ?? '');
        if ($sort !== '') {
            return $sort;
        }

        $sortBy = (string) ($params['sortBy'] ?? '');

        return match ($sortBy) {
            'updated_oldest' => 'updatedAt',
            'progress_highest' => '-progress',
            'progress_lowest' => 'progress',
            default => '-updatedAt',
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
