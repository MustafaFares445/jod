<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use App\Support\SearchFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;

class PublisherService
{
    public function findPublic(string $id): Organization|User|null
    {
        $organization = Organization::query()
            ->with('logoMedia')
            ->whereKey($id)
            ->where('status', 'active')
            ->first();

        if ($organization !== null) {
            return $organization;
        }

        return User::query()
            ->with('avatarMedia')
            ->whereKey($id)
            ->where('status', 'active')
            ->whereHas('posts', function (Builder $post): void {
                $post->whereIn('status', ['published', 'approved'])
                    ->whereNull('organization_id');
            })
            ->first();
    }

    /**
     * @param  array{page?: int|string|null, perPage?: int|string|null, search?: string|null, searchQueries?: string|null, type?: string|null, sort?: string|null, sortBy?: string|null}  $params
     */
    public function paginatePosts(Organization|User $publisher, array $params, ?User $viewer = null): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $search = SearchFilter::fromArray($params);

        $query = Post::query()
            ->with($this->postRelations($viewer))
            ->whereIn('status', ['published', 'approved']);

        if ($publisher instanceof Organization) {
            $query->where('organization_id', $publisher->id);
        } else {
            $query->where('author_id', $publisher->id)
                ->whereNull('organization_id');
        }

        $query
            ->when(filled($params['type'] ?? null), fn (Builder $builder) => $builder->where('type', $params['type']))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhereHas('campaign', fn (Builder $campaign) => $campaign->where('title', 'like', "%{$search}%"));
                });
            });

        $sort = (string) ($params['sort'] ?? $params['sortBy'] ?? 'newest');

        match ($sort) {
            'title', 'title_asc' => $query->orderBy('title'),
            '-title', 'title_desc' => $query->orderByDesc('title'),
            'updatedAt', 'updated_oldest', 'oldest' => $query->orderBy('published_at')->orderBy('created_at'),
            'most_engaged' => $query->orderByDesc('reactions_count')->orderByDesc('updated_at'),
            default => $query->orderByDesc('published_at')->orderByDesc('updated_at'),
        };

        return $query->orderBy('id')->paginate($perPage);
    }

    /** @return array<int|string, mixed> */
    private function postRelations(?User $viewer): array
    {
        $relations = ['organization.logoMedia', 'campaign', 'author.avatarMedia', 'images'];

        if ($viewer === null) {
            return $relations;
        }

        $relations['likes'] = static fn (Relation $relation) => $relation->where('user_id', $viewer->id);
        $relations['saves'] = static fn (Relation $relation) => $relation->where('user_id', $viewer->id);
        $relations['campaignApplications'] = static fn (Relation $relation) => $relation->where('created_by', $viewer->id);

        return $relations;
    }
}
