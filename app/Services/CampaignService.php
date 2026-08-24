<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\CampaignData;
use App\Models\Campaign;
use App\Models\Category;
use App\Support\SearchFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CampaignService
{
    public function discover(array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $sort = $this->normalizeDiscoverySort($params);
        $search = SearchFilter::fromArray($params);

        $query = Campaign::query()
            ->with($this->mobileDiscoveryRelations())
            ->where('status', 'active')
            ->when(filled($params['status'] ?? null), fn (Builder $builder) => $builder->where('status', $params['status']))
            ->when(filled($params['category'] ?? null), function (Builder $builder) use ($params): void {
                $this->applyCategoryFilter($builder, (string) $params['category']);
            })
            ->when(filled($params['location'] ?? null), fn (Builder $builder) => $builder->where('location', 'like', '%'.$params['location'].'%'))
            ->when(filled($params['organizationId'] ?? null), fn (Builder $builder) => $builder->where('organization_id', $params['organizationId']))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('categoryRelation', fn (Builder $category) => $category->where('name', 'like', "%{$search}%"))
                        ->orWhere('location', 'like', "%{$search}%")
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

    public function findPublicCampaign(string $id): ?Campaign
    {
        return Campaign::query()
            ->with($this->mobileDiscoveryRelations())
            ->whereKey($id)
            ->where('status', 'active')
            ->first();
    }

    public function paginate(array $params, string $organizationId): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 10), 100));
        $sort = $this->normalizeSort($params);
        $status = $params['status'] ?? $this->param($params, 'filter.status');
        $search = SearchFilter::fromArray($params);

        $query = Campaign::query()
            ->with(['imageMedia', 'categoryRelation'])
            ->where('organization_id', $organizationId)
            ->when($status && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when(($category = $this->param($params, 'filter.category')) && $category !== 'all', function (Builder $builder) use ($category): void {
                $this->applyCategoryFilter($builder, (string) $category);
            })
            ->when(($location = $this->param($params, 'filter.location')) && $location !== 'all', fn (Builder $builder) => $builder->where('location', 'like', '%'.$location.'%'))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('categoryRelation', fn (Builder $category) => $category->where('name', 'like', "%{$search}%"))
                        ->orWhere('location', 'like', "%{$search}%");
                });
            });

        match ($sort) {
            'updatedAt' => $query->orderBy('updated_at'),
            '-updatedAt' => $query->orderByDesc('updated_at'),
            'progress' => $query->orderByRaw('CASE WHEN goal_amount > 0 THEN (raised_amount / goal_amount) ELSE 0 END ASC'),
            '-progress' => $query->orderByRaw('CASE WHEN goal_amount > 0 THEN (raised_amount / goal_amount) ELSE 0 END DESC'),
            default => $query->orderByDesc('updated_at'),
        };

        return $query->paginate($perPage);
    }

    public function create(CampaignData $data, string $organizationId): Campaign
    {
        $attributes = [
            'title' => $data->title,
            'summary' => $data->summary,
            'status' => $data->status,
            'location' => $data->location,
            'organization_id' => $organizationId,
            'goal_amount' => $data->goalAmount,
            'beneficiaries_count' => $data->beneficiariesCount,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
        ];

        if ($data->category !== null) {
            $attributes['category'] = $data->category;
        }

        $categoryId = $this->resolveCategoryId($data->categoryId, $data->category);
        if ($categoryId !== null) {
            $attributes['category_id'] = $categoryId;
        }

        return Campaign::create($attributes);
    }

    public function update(Campaign $campaign, CampaignData $data): Campaign
    {
        $attributes = [
            'title' => $data->title,
            'summary' => $data->summary,
            'status' => $data->status,
            'location' => $data->location,
            'goal_amount' => $data->goalAmount,
            'beneficiaries_count' => $data->beneficiariesCount,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
        ];

        if ($data->category !== null) {
            $attributes['category'] = $data->category;
        }

        $attributes['category_id'] = $this->resolveCategoryId($data->categoryId, $data->category);

        $campaign->update($attributes);

        return $campaign;
    }

    public function updateStatus(Campaign $campaign, string $status, ?string $closedReason = null): Campaign
    {
        if ($campaign->status === $status) {
            return $campaign;
        }

        $transition = "{$campaign->status}:{$status}";

        match ($transition) {
            'draft:active' => $campaign->update([
                'status' => 'active',
                'closed_at' => null,
                'closed_reason' => null,
            ]),
            'active:draft' => $campaign->update([
                'status' => 'draft',
                'closed_at' => null,
                'closed_reason' => null,
            ]),
            'active:closed' => $campaign->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_reason' => $closedReason,
            ]),
            default => throw ValidationException::withMessages([
                'status' => ["Campaign status cannot transition from {$campaign->status} to {$status}."],
            ]),
        };

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
            'closed_reason' => $reason,
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
        $sortingField = (string) ($params['sortingField'] ?? '');
        if ($sortingField !== '') {
            $direction = ($params['sortingDir'] ?? 'desc') === 'asc' ? '' : '-';

            return $direction.$sortingField;
        }

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

    /** @return array<int|string, mixed> */
    private function mobileDiscoveryRelations(): array
    {
        return [
            'organization',
            'creator',
            'categoryRelation',
            'imageMedia',
            'posts' => static fn ($relation) => $relation
                ->whereIn('status', ['published', 'approved'])
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->with('images'),
        ];
    }

    private function normalizeDiscoverySort(array $params): string
    {
        $sort = (string) ($params['sort'] ?? '');
        if ($sort !== '') {
            return $sort;
        }

        $sortBy = (string) ($params['sortBy'] ?? '');

        return match ($sortBy) {
            'progress_highest' => '-progress',
            'progress_lowest' => 'progress',
            'updated_oldest' => 'updatedAt',
            'newest' => 'newest',
            'oldest' => 'oldest',
            default => '-updatedAt',
        };
    }

    private function applyCategoryFilter(Builder $builder, string $category): void
    {
        $builder->where(function (Builder $inner) use ($category): void {
            $inner->where('category', $category)
                ->orWhere('category_id', $category)
                ->orWhereHas('categoryRelation', fn (Builder $relation) => $relation->where('name', $category));
        });
    }

    private function resolveCategoryId(?string $categoryId, ?string $legacyCategory): ?string
    {
        if (filled($categoryId)) {
            return $categoryId;
        }

        if (! filled($legacyCategory)) {
            return null;
        }

        $category = Category::query()->where('name', $legacyCategory)->first();
        if ($category !== null) {
            return $category->target === 'campaign' ? (string) $category->id : null;
        }

        return (string) Category::query()->create([
            'name' => $legacyCategory,
            'target' => 'campaign',
            'description' => 'Legacy campaign category: '.$legacyCategory,
            'status' => 'active',
            'usage_count' => 0,
        ])->id;
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
