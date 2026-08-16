<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class PublisherService
{
    public function findPublic(string $id): Organization|User|null
    {
        $organization = Organization::query()
            ->whereKey($id)
            ->where('status', 'active')
            ->whereHas('posts', fn (Builder $post) => $post->where('status', 'published'))
            ->first();

        if ($organization !== null) {
            return $this->withPublicStats($organization);
        }

        $user = User::query()
            ->whereKey($id)
            ->where('status', 'active')
            ->whereHas('posts', function (Builder $post): void {
                $post->where('status', 'published')
                    ->whereNull('organization_id');
            })
            ->first();

        return $user === null ? null : $this->withPublicStats($user);
    }

    /**
     * @param  array{page?: int|string|null, perPage?: int|string|null, search?: string|null, type?: string|null, sort?: string|null, sortBy?: string|null}  $params
     */
    public function paginatePosts(Organization|User $publisher, array $params, ?User $viewer = null): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));

        $query = Post::query()
            ->with($this->postRelations($viewer))
            ->where('status', 'published');

        $this->scopePublisherPosts($query, $publisher);

        $query
            ->when(filled($params['type'] ?? null), fn (Builder $builder) => $builder->where('type', $params['type']))
            ->when(filled($params['search'] ?? null), function (Builder $builder) use ($params): void {
                $search = (string) $params['search'];
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            });

        $sort = (string) ($params['sort'] ?? $params['sortBy'] ?? 'newest');

        match ($sort) {
            'title', 'title_asc' => $query->orderBy('title'),
            '-title', 'title_desc' => $query->orderByDesc('title'),
            'updatedAt', 'updated_oldest' => $query->orderBy('updated_at'),
            'most_engaged' => $query
                ->orderByRaw('(COALESCE(reactions_count, 0) + COALESCE(comments_count, 0) + COALESCE(shares_count, 0)) DESC')
                ->orderByDesc('updated_at'),
            default => $query->orderByDesc('published_at')->orderByDesc('updated_at'),
        };

        return $query->orderBy('id')->paginate($perPage);
    }

    private function withPublicStats(Organization|User $publisher): Organization|User
    {
        $query = Post::query()->where('status', 'published');
        $this->scopePublisherPosts($query, $publisher);

        $stats = $query
            ->selectRaw('COUNT(*) as posts_count')
            ->selectRaw('COALESCE(SUM(reactions_count), 0) as likes_count')
            ->selectRaw('COALESCE(SUM(shares_count), 0) as shares_count')
            ->first();

        $publisher->setAttribute('published_posts_count', (int) ($stats?->posts_count ?? 0));
        $publisher->setAttribute('published_likes_count', (int) ($stats?->likes_count ?? 0));
        $publisher->setAttribute('published_shares_count', (int) ($stats?->shares_count ?? 0));

        return $publisher;
    }

    private function scopePublisherPosts(Builder $query, Organization|User $publisher): void
    {
        if ($publisher instanceof Organization) {
            $query->where('organization_id', $publisher->id);

            return;
        }

        // Organization-backed posts render the organization as publisher,
        // so a user publisher page only contains truly individual posts.
        $query->where('author_id', $publisher->id)
            ->whereNull('organization_id');
    }

    /**
     * @return array<int|string, mixed>
     */
    private function postRelations(?User $viewer): array
    {
        $relations = ['organization', 'campaign', 'author', 'images'];

        if ($viewer === null) {
            return $relations;
        }

        $relations['saves'] = static fn (Builder $builder) => $builder->where('user_id', $viewer->id);
        $relations['campaignApplications'] = static fn (Builder $builder) => $builder->where('created_by', $viewer->id);

        return $relations;
    }
}
