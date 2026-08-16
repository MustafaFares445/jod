<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\PostData;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostService
{
    /**
     * @param  array{page?: int|string|null, perPage?: int|string|null, search?: string|null, status?: string|null, actionState?: string|null, type?: string|null, location?: string|null, organizationId?: string|null, sort?: string|null, sortBy?: string|null}  $params
     */
    public function discover(array $params, ?User $viewer = null): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $sort = $this->normalizeDiscoverySort($params);

        $query = Post::query()
            ->with($this->mobileRelations($viewer))
            ->where('status', 'published')
            ->when(filled($params['status'] ?? null), fn (Builder $builder) => $builder->where('status', $params['status']))
            ->when(filled($params['type'] ?? null), fn (Builder $builder) => $builder->where('type', $params['type']))
            ->when(filled($params['location'] ?? null), fn (Builder $builder) => $builder->where('location', 'like', '%'.$params['location'].'%'))
            ->when(filled($params['organizationId'] ?? null), fn (Builder $builder) => $builder->where('organization_id', $params['organizationId']))
            ->when(filled($params['actionState'] ?? null), function (Builder $builder) use ($params, $viewer): void {
                $this->applyActionStateFilter($builder, (string) $params['actionState'], $viewer);
            })
            ->when(filled($params['search'] ?? null), function (Builder $builder) use ($params): void {
                $search = (string) $params['search'];
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
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
            'newest' => $query->orderByDesc('published_at')->orderByDesc('updated_at'),
            'most_engaged' => $query
                ->orderByRaw('(COALESCE(reactions_count, 0) + COALESCE(comments_count, 0) + COALESCE(shares_count, 0)) DESC')
                ->orderByDesc('updated_at'),
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
            ->where('status', 'published')
            ->first();
    }

    /**
     * @param  array{page?: int|string|null, perPage?: int|string|null, sort?: string|null, sortBy?: string|null, filter?: array{status?: string|null, type?: string|null, search?: string|null}, filter_status?: string|null, filter_type?: string|null, filter_search?: string|null, "filter.status"?: string|null, "filter.type"?: string|null, "filter.search"?: string|null}  $params
     */
    public function paginate(array $params, string $organizationId): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 10), 100));
        $sort = $this->normalizeSort($params);
        $status = $params['status'] ?? $this->param($params, 'filter.status');
        $search = $params['searchQueries'] ?? $this->param($params, 'filter.search');

        $query = Post::query()
            ->with(['campaign', 'images'])
            ->where('organization_id', $organizationId)
            ->when($status && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when(($type = $this->param($params, 'filter.type')) && $type !== 'all', fn (Builder $builder) => $builder->where('type', $type))
            ->when($search && $search !== 'all', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('author_name', 'like', "%{$search}%");
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
            'status' => $data->status,
            'author_name' => $data->authorName,
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
            'author_name' => $data->authorName,
            'location' => $data->location,
            'campaign_id' => $campaignId,
        ]);

        return $post;
    }

    public function updateStatus(Post $post, string $status): Post
    {
        if ($post->status === $status) {
            return $post;
        }

        return match ("{$post->status}:{$status}") {
            'draft:published' => $this->publish($post),
            'published:archived' => $this->archive($post),
            'archived:draft' => $this->restore($post),
            default => throw ValidationException::withMessages([
                'status' => ["Post status cannot transition from {$post->status} to {$status}."],
            ]),
        };
    }

    public function publish(Post $post): Post
    {
        return $this->transitionStatus(
            $post,
            'draft',
            ['status' => 'published', 'published_at' => now()],
            'Only draft posts can be published.',
        );
    }

    public function archive(Post $post): Post
    {
        return $this->transitionStatus(
            $post,
            'published',
            ['status' => 'archived'],
            'Only published posts can be archived.',
        );
    }

    public function restore(Post $post): Post
    {
        return $this->transitionStatus(
            $post,
            'archived',
            ['status' => 'draft', 'published_at' => null],
            'Only archived posts can be restored.',
        );
    }

    public function delete(Post $post): void
    {
        $post->delete();
    }

    /**
     * @return array<int|string, mixed>
     */
    private function mobileRelations(?User $viewer): array
    {
        $relations = ['organization', 'campaign', 'author', 'images'];

        if ($viewer === null) {
            return $relations;
        }

        $relations['saves'] = static fn (Builder $builder) => $builder->where('user_id', $viewer->id);
        $relations['campaignApplications'] = static fn (Builder $builder) => $builder->where('created_by', $viewer->id);

        return $relations;
    }

    private function applyActionStateFilter(Builder $query, string $state, ?User $viewer): void
    {
        if ($state === 'submitted') {
            if ($viewer === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where('type', 'volunteer_opportunity')
                ->whereHas('campaign', fn (Builder $campaign) => $campaign->where('status', 'active'))
                ->whereHas(
                    'campaignApplications',
                    fn (Builder $application) => $application->where('created_by', $viewer->id),
                );

            return;
        }

        if ($state === 'closed') {
            $query->whereIn('type', ['volunteer_opportunity', 'donation_campaign'])
                ->where(function (Builder $campaignState): void {
                    $campaignState->whereDoesntHave('campaign')
                        ->orWhereHas('campaign', fn (Builder $campaign) => $campaign->where('status', '!=', 'active'));
                });

            return;
        }

        $query->where(function (Builder $interactive) use ($viewer): void {
            $interactive->where(function (Builder $donation): void {
                $donation->where('type', 'donation_campaign')
                    ->whereHas('campaign', fn (Builder $campaign) => $campaign->where('status', 'active'));
            })->orWhere(function (Builder $volunteer) use ($viewer): void {
                $volunteer->where('type', 'volunteer_opportunity')
                    ->whereHas('campaign', fn (Builder $campaign) => $campaign->where('status', 'active'));

                if ($viewer !== null) {
                    $volunteer->whereDoesntHave(
                        'campaignApplications',
                        fn (Builder $application) => $application->where('created_by', $viewer->id),
                    );
                }
            });
        });
    }

    private function resolveCampaignId(?string $campaignTitle, string $organizationId): ?string
    {
        if (! $campaignTitle) {
            return null;
        }

        $campaignId = Campaign::query()
            ->where('organization_id', $organizationId)
            ->where('title', $campaignTitle)
            ->value('id');

        if ($campaignId === null) {
            throw ValidationException::withMessages([
                'campaignTitle' => ['Selected campaign does not belong to the organization.'],
            ]);
        }

        return (string) $campaignId;
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
            'title_asc' => 'title',
            'title_desc' => '-title',
            default => '-updatedAt',
        };
    }

    /**
     * @param  array{sort?: string|null, sortBy?: string|null}  $params
     */
    private function normalizeDiscoverySort(array $params): string
    {
        $sort = (string) ($params['sort'] ?? '');
        if ($sort !== '') {
            return $sort;
        }

        $sortBy = (string) ($params['sortBy'] ?? '');

        return match ($sortBy) {
            'title_asc' => 'title',
            'title_desc' => '-title',
            'updated_oldest' => 'updatedAt',
            'newest' => 'newest',
            'most_engaged' => 'most_engaged',
            default => 'newest',
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

    /**
     * @param  array{status: string, published_at?: mixed}  $attributes
     */
    private function transitionStatus(Post $post, string $expectedStatus, array $attributes, string $message): Post
    {
        return DB::transaction(function () use ($post, $expectedStatus, $attributes, $message): Post {
            $lockedPost = Post::query()
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPost->status !== $expectedStatus) {
                throw ValidationException::withMessages([
                    'status' => [$message],
                ]);
            }

            $lockedPost->update($attributes);

            return $lockedPost;
        });
    }
}
