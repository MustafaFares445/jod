<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\PostData;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\User;
use App\Support\SearchFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostService
{
    public function discover(array $params, ?User $viewer = null): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? $params['perPAge'] ?? 20), 100));
        $sort = $this->normalizeDiscoverySort($params);
        $search = SearchFilter::fromArray($params);

        $query = Post::query()
            ->with($this->mobileRelations($viewer))
            ->whereIn('status', ['published', 'approved'])
            ->when(filled($params['type'] ?? null), fn (Builder $builder) => $builder->where('type', $params['type']))
            ->when(filled($params['audience'] ?? null), fn (Builder $builder) => $builder->where('audience', $params['audience']))
            ->when(filled($params['location'] ?? null), fn (Builder $builder) => $builder->where('location', 'like', '%'.$params['location'].'%'))
            ->when(filled($params['categoryId'] ?? null), fn (Builder $builder) => $builder->where('category_id', $params['categoryId']))
            ->when(filled($params['category'] ?? null), function (Builder $builder) use ($params): void {
                $category = (string) $params['category'];
                $builder->where(function (Builder $inner) use ($category): void {
                    $inner->where('category_id', $category)
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', $category));
                });
            })
            ->when(filled($params['organizationId'] ?? null), fn (Builder $builder) => $builder->where('organization_id', $params['organizationId']))
            ->when(filled($params['actionState'] ?? null), function (Builder $builder) use ($params, $viewer): void {
                $this->applyActionStateFilter($builder, (string) $params['actionState'], $viewer);
            })
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('organization', function (Builder $organization) use ($search): void {
                            $organization->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('location', 'like', "%{$search}%");
                        })
                        ->orWhereHas('author', function (Builder $author) use ($search): void {
                            $author->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            });

        match ($sort) {
            'title' => $query->orderBy('title'),
            '-title' => $query->orderByDesc('title'),
            'updatedAt' => $query->orderBy('updated_at'),
            '-updatedAt' => $query->orderByDesc('updated_at'),
            'newest' => $query->orderByDesc('published_at')->orderByDesc('created_at'),
            'oldest' => $query->orderBy('published_at')->orderBy('created_at'),
            'most_engaged' => $query->orderByDesc('reactions_count')->orderByDesc('updated_at'),
            default => $query->orderByDesc('updated_at'),
        };
        $query->orderBy('id');

        return $query->paginate($perPage);
    }

    public function findPublicPost(string $id, ?User $viewer = null): ?Post
    {
        return Post::query()
            ->with($this->mobileRelations($viewer))
            ->whereKey($id)
            ->whereIn('status', ['published', 'approved'])
            ->first();
    }

    public function paginate(array $params, string $organizationId): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 10), 100));
        $sort = $this->normalizeSort($params);
        $status = $params['status'] ?? $this->param($params, 'filter.status');
        $search = SearchFilter::fromArray($params);
        $audience = $params['audience'] ?? $this->param($params, 'filter.audience');

        $query = Post::query()
            ->with(['campaign', 'images', 'videos'])
            ->where('organization_id', $organizationId)
            ->when($status && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when($audience && $audience !== 'all', fn (Builder $builder) => $builder->where('audience', $audience))
            ->when(($type = $this->param($params, 'filter.type')) && $type !== 'all', fn (Builder $builder) => $builder->where('type', $type))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhereHas('campaign', fn (Builder $campaign) => $campaign->where('title', 'like', "%{$search}%"));
                });
            });

        match ($sort) {
            'title' => $query->orderBy('title'),
            '-title' => $query->orderByDesc('title'),
            'updatedAt' => $query->orderBy('updated_at'),
            '-updatedAt' => $query->orderByDesc('updated_at'),
            default => $query->orderByDesc('updated_at'),
        };
        $query->orderBy('id');

        return $query->paginate($perPage);
    }

    public function create(PostData $data, string $organizationId): Post
    {
        $campaignId = $this->resolveCampaignId($data->campaignTitle, $organizationId);

        return Post::create([
            'title' => $data->title,
            'summary' => $data->summary,
            'type' => $data->type,
            'audience' => $data->audience,
            'status' => $data->status,
            'location' => $data->location,
            'organization_id' => $organizationId,
            'campaign_id' => $campaignId,
            'published_at' => $data->status === 'published' ? now() : null,
        ]);
    }

    public function update(Post $post, PostData $data, string $organizationId): Post
    {
        $campaignId = $this->resolveCampaignId($data->campaignTitle, $organizationId);

        $post->update([
            'title' => $data->title,
            'summary' => $data->summary,
            'type' => $data->type,
            'audience' => $data->audience,
            'location' => $data->location,
            'campaign_id' => $campaignId,
        ]);

        return $post;
    }

    public function updateStatus(Post $post, string $status): Post
    {
        if ($post->status === $status) return $post;

        return match ("{$post->status}:{$status}") {
            'draft:published' => $this->publish($post),
            'published:archived' => $this->archive($post),
            'archived:draft' => $this->restore($post),
            default => throw ValidationException::withMessages(['status' => ["Post status cannot transition from {$post->status} to {$status}."]]),
        };
    }

    public function publish(Post $post): Post
    {
        return $this->transitionStatus($post, 'draft', ['status' => 'published', 'published_at' => now()], 'Only draft posts can be published.');
    }

    public function archive(Post $post): Post
    {
        return $this->transitionStatus($post, 'published', ['status' => 'archived'], 'Only published posts can be archived.');
    }

    public function restore(Post $post): Post
    {
        return $this->transitionStatus($post, 'archived', ['status' => 'draft', 'published_at' => null], 'Only archived posts can be restored.');
    }

    public function delete(Post $post): void { $post->delete(); }

    private function normalizeSort(array $params): string
    {
        $sortingField = (string) ($params['sortingField'] ?? '');
        if ($sortingField !== '') return (($params['sortingDir'] ?? 'desc') === 'asc' ? '' : '-').$sortingField;
        $sort = (string) ($params['sort'] ?? '');
        if ($sort !== '') return $sort;
        return match ((string) ($params['sortBy'] ?? '')) {
            'updated_oldest' => 'updatedAt', 'title_asc' => 'title', 'title_desc' => '-title', default => '-updatedAt',
        };
    }

    private function normalizeDiscoverySort(array $params): string
    {
        $sort = (string) ($params['sort'] ?? '');
        if ($sort !== '') return $sort;
        return match ((string) ($params['sortBy'] ?? '')) {
            'title_asc' => 'title', 'title_desc' => '-title', 'updated_oldest' => 'updatedAt', 'newest' => 'newest',
            'oldest' => 'oldest', 'most_engaged' => 'most_engaged', default => 'newest',
        };
    }

    private function param(array $params, string $key): mixed
    {
        if (array_key_exists($key, $params)) return $params[$key];
        $flatKey = str_replace('.', '_', $key);
        if (array_key_exists($flatKey, $params)) return $params[$flatKey];
        return data_get($params, $key);
    }

    private function transitionStatus(Post $post, string $expectedStatus, array $attributes, string $message): Post
    {
        return DB::transaction(function () use ($post, $expectedStatus, $attributes, $message): Post {
            $lockedPost = Post::query()->whereKey($post->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedPost->status !== $expectedStatus) throw ValidationException::withMessages(['status' => [$message]]);
            $lockedPost->update($attributes);
            return $lockedPost;
        });
    }

    private function mobileRelations(?User $viewer): array
    {
        $relations = ['organization', 'campaign', 'author', 'images', 'videos'];
        if ($viewer === null) return $relations;
        $relations['likes'] = static fn (Relation $builder) => $builder->where('user_id', $viewer->id);
        $relations['saves'] = static fn (Relation $builder) => $builder->where('user_id', $viewer->id);
        $relations['campaignApplications'] = static fn (Relation $builder) => $builder->where('created_by', $viewer->id);
        return $relations;
    }

    private function applyActionStateFilter(Builder $query, string $state, ?User $viewer): void
    {
        if ($state === 'submitted') {
            if ($viewer === null) { $query->whereRaw('1 = 0'); return; }
            $query->where('type', 'volunteer_opportunity')
                ->whereHas('campaign', fn (Builder $campaign) => $campaign->where('status', 'active'))
                ->whereHas('campaignApplications', fn (Builder $application) => $application->where('created_by', $viewer->id));
            return;
        }
        if ($state === 'closed') {
            $query->whereIn('type', ['volunteer_opportunity', 'donation_campaign'])
                ->where(function (Builder $campaignState): void {
                    $campaignState->whereDoesntHave('campaign')->orWhereHas('campaign', fn (Builder $campaign) => $campaign->where('status', '!=', 'active'));
                });
            return;
        }
        $query->where(function (Builder $interactive) use ($viewer): void {
            $interactive->where(function (Builder $donation): void {
                $donation->where('type', 'donation_campaign')->whereHas('campaign', fn (Builder $campaign) => $campaign->where('status', 'active'));
            })->orWhere(function (Builder $volunteer) use ($viewer): void {
                $volunteer->where('type', 'volunteer_opportunity')->whereHas('campaign', fn (Builder $campaign) => $campaign->where('status', 'active'));
                if ($viewer !== null) $volunteer->whereDoesntHave('campaignApplications', fn (Builder $application) => $application->where('created_by', $viewer->id));
            });
        });
    }

    private function resolveCampaignId(?string $campaignTitle, string $organizationId): ?string
    {
        if (! filled($campaignTitle)) return null;
        return Campaign::query()->where('organization_id', $organizationId)->where('title', $campaignTitle)->value('id');
    }
}
