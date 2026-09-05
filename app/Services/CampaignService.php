<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CampaignData;
use App\Models\Campaign;
use App\Models\User;
use App\Support\SearchFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CampaignService
{
    public function discover(array $params, ?User $viewer = null): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $sort = $this->normalizeDiscoverySort($params);
        $search = SearchFilter::fromArray($params);

        $query = Campaign::query()
            ->with($this->mobileDiscoveryRelations($viewer))
            ->where('status', 'active')
            ->when(filled($params['status'] ?? null), fn (Builder $builder) => $builder->where('status', $params['status']))
            ->when(filled($params['audience'] ?? null), fn (Builder $builder) => $builder->where('audience', $params['audience']))
            ->when(filled($params['categoryId'] ?? null), fn (Builder $builder) => $builder->where('category_id', $params['categoryId']))
            ->when(filled($params['category'] ?? null), fn (Builder $builder) => $builder->whereHas('category', fn (Builder $category) => $category->where('name', 'like', '%'.$params['category'].'%')))
            ->when(filled($params['location'] ?? null), fn (Builder $builder) => $builder->where('location', 'like', '%'.$params['location'].'%'))
            ->when(filled($params['organizationId'] ?? null), fn (Builder $builder) => $builder->where('organization_id', $params['organizationId']))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('organization', function (Builder $organization) use ($search): void {
                            $organization->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('location', 'like', "%{$search}%");
                        });
                });
            });

        match ($sort) {
            'updatedAt' => $query->orderBy('updated_at'),
            '-updatedAt' => $query->orderByDesc('updated_at'),
            'newest' => $query->orderByDesc('created_at'),
            'oldest' => $query->orderBy('created_at'),
            'progress' => $query->orderByRaw('CASE WHEN goal_amount > 0 THEN (raised_amount / goal_amount) ELSE 0 END ASC'),
            '-progress' => $query->orderByRaw('CASE WHEN goal_amount > 0 THEN (raised_amount / goal_amount) ELSE 0 END DESC'),
            default => $query->orderByDesc('updated_at'),
        };

        return $query->paginate($perPage);
    }

    public function findPublicCampaign(string $id, ?User $viewer = null): ?Campaign
    {
        return Campaign::query()->with($this->mobileDiscoveryRelations($viewer))->whereKey($id)->where('status', 'active')->first();
    }

    public function paginate(array $params, string $organizationId): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 10), 100));
        $sort = $this->normalizeSort($params);
        $status = $params['status'] ?? $this->param($params, 'filter.status');
        $audience = $params['audience'] ?? $this->param($params, 'filter.audience');
        $search = SearchFilter::fromArray($params);

        $query = Campaign::query()
            ->with(['imageMedia', 'category'])
            ->where('organization_id', $organizationId)
            ->when($status && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when($audience && $audience !== 'all', fn (Builder $builder) => $builder->where('audience', $audience))
            ->when(($categoryId = $this->param($params, 'filter.categoryId')) && $categoryId !== 'all', fn (Builder $builder) => $builder->where('category_id', $categoryId))
            ->when(($category = $this->param($params, 'filter.category')) && $category !== 'all', fn (Builder $builder) => $builder->whereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', '%'.$category.'%')))
            ->when(($location = $this->param($params, 'filter.location')) && $location !== 'all', fn (Builder $builder) => $builder->where('location', 'like', '%'.$location.'%'))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', "%{$search}%"));
                });
            });

        match ($sort) {
            'updatedAt' => $query->orderBy('updated_at'), '-updatedAt' => $query->orderByDesc('updated_at'),
            'progress' => $query->orderByRaw('CASE WHEN goal_amount > 0 THEN (raised_amount / goal_amount) ELSE 0 END ASC'),
            '-progress' => $query->orderByRaw('CASE WHEN goal_amount > 0 THEN (raised_amount / goal_amount) ELSE 0 END DESC'),
            default => $query->orderByDesc('updated_at'),
        };
        return $query->paginate($perPage);
    }

    public function create(CampaignData $data, string $organizationId): Campaign
    {
        return Campaign::create([
            'title' => $data->title, 'summary' => $data->summary, 'category_id' => $data->categoryId,
            'audience' => $data->audience, 'status' => $data->status, 'location' => $data->location,
            'organization_id' => $organizationId, 'goal_amount' => $data->goalAmount,
            'beneficiaries_count' => $data->beneficiariesCount, 'start_date' => $data->startDate, 'end_date' => $data->endDate,
        ]);
    }

    public function update(Campaign $campaign, CampaignData $data): Campaign
    {
        $campaign->update([
            'title' => $data->title, 'summary' => $data->summary, 'category_id' => $data->categoryId,
            'audience' => $data->audience, 'status' => $data->status, 'location' => $data->location,
            'goal_amount' => $data->goalAmount, 'beneficiaries_count' => $data->beneficiariesCount,
            'start_date' => $data->startDate, 'end_date' => $data->endDate,
        ]);
        return $campaign;
    }

    public function updateStatus(Campaign $campaign, string $status, ?string $closedReason = null): Campaign
    {
        if ($campaign->status === $status) return $campaign;
        match ("{$campaign->status}:{$status}") {
            'draft:active' => $campaign->update(['status' => 'active', 'closed_at' => null, 'closed_reason' => null]),
            'active:draft' => $campaign->update(['status' => 'draft', 'closed_at' => null, 'closed_reason' => null]),
            'active:closed' => $campaign->update(['status' => 'closed', 'closed_at' => now(), 'closed_reason' => $closedReason]),
            default => throw ValidationException::withMessages(['status' => ["Campaign status cannot transition from {$campaign->status} to {$status}."]]),
        };
        return $campaign;
    }

    public function close(Campaign $campaign, string $reason): Campaign
    {
        if ($campaign->status !== 'active') throw ValidationException::withMessages(['status' => ['Only active campaigns can be closed.']]);
        $campaign->update(['status' => 'closed', 'closed_reason' => $reason, 'closed_at' => now()]);
        return $campaign;
    }

    public function delete(Campaign $campaign): void { $campaign->delete(); }

    private function normalizeSort(array $params): string
    {
        $sortingField = (string) ($params['sortingField'] ?? '');
        if ($sortingField !== '') return (($params['sortingDir'] ?? 'desc') === 'asc' ? '' : '-').$sortingField;
        $sort = (string) ($params['sort'] ?? ''); if ($sort !== '') return $sort;
        return match ((string) ($params['sortBy'] ?? '')) { 'updated_oldest' => 'updatedAt', 'progress_highest' => '-progress', 'progress_lowest' => 'progress', default => '-updatedAt' };
    }

    private function mobileDiscoveryRelations(?User $viewer = null): array
    {
        return [
            'organization.logoMedia',
            'creator',
            'category',
            'imageMedia',
            'posts' => static function ($relation) use ($viewer): void {
                $relation
                    ->where('status', 'published')
                    ->orderByDesc('published_at')
                    ->orderByDesc('created_at')
                    ->with('images');

                if ($viewer !== null) {
                    $relation->with(['likes' => static fn ($likes) => $likes->where('user_id', $viewer->id)]);
                }
            },
        ];
    }

    private function normalizeDiscoverySort(array $params): string
    {
        $sort = (string) ($params['sort'] ?? ''); if ($sort !== '') return $sort;
        return match ((string) ($params['sortBy'] ?? '')) {
            'progress_highest' => '-progress', 'progress_lowest' => 'progress', 'updated_oldest' => 'updatedAt',
            'newest' => 'newest', 'oldest' => 'oldest', default => '-updatedAt',
        };
    }

    private function param(array $params, string $key): mixed
    {
        if (array_key_exists($key, $params)) return $params[$key];
        $flatKey = str_replace('.', '_', $key); if (array_key_exists($flatKey, $params)) return $params[$flatKey];
        return data_get($params, $key);
    }
}
