<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\PostData;
use App\Models\Campaign;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PostService
{
    /**
     * @param  array{page?: int|string|null, perPage?: int|string|null, search?: string|null, status?: string|null, type?: string|null, location?: string|null, organizationId?: string|null, sort?: string|null, sortBy?: string|null}  $params
     */
    public function discover(array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $sort = $this->normalizeDiscoverySort($params);

        $query = Post::query()
            ->with(['organization', 'campaign'])
            ->where('status', 'published')
            ->when(filled($params['status'] ?? null), fn (Builder $builder) => $builder->where('status', $params['status']))
            ->when(filled($params['type'] ?? null), fn (Builder $builder) => $builder->where('type', $params['type']))
            ->when(filled($params['location'] ?? null), fn (Builder $builder) => $builder->where('location', 'like', '%'.$params['location'].'%'))
            ->when(filled($params['organizationId'] ?? null), fn (Builder $builder) => $builder->where('organization_id', $params['organizationId']))
            ->when(filled($params['search'] ?? null), function (Builder $builder) use ($params): void {
                $search = (string) $params['search'];
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%");
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

    public function findPublicPost(string $id): ?Post
    {
        return Post::query()
            ->with(['organization', 'campaign'])
            ->whereKey($id)
            ->where('status', 'published')
            ->first();
    }

    /**
     * @param  array{page?: int|string|null, perPage?: int|string|null, sort?: string|null, sortBy?: string|null, filter?: array{status?: string|null, type?: string|null, search?: string|null}, filter_status?: string|null, filter_type?: string|null, filter_search?: string|null, "filter.status"?: string|null, "filter.type"?: string|null, "filter.search"?: string|null}  $params
     */
    public function paginate(array $params, string $organizationId): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $sort = $this->normalizeSort($params);

        $query = Post::query()
            ->with('campaign')
            ->where('organization_id', $organizationId)
            ->when(($status = $this->param($params, 'filter.status')) && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when(($type = $this->param($params, 'filter.type')) && $type !== 'all', fn (Builder $builder) => $builder->where('type', $type))
            ->when(($search = $this->param($params, 'filter.search')) && $search !== 'all', function (Builder $builder) use ($search): void {
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

    private function resolveCampaignId(?string $campaignTitle, string $organizationId): ?string
    {
        if (! $campaignTitle) {
            return null;
        }

        return Campaign::query()
            ->where('organization_id', $organizationId)
            ->where('title', $campaignTitle)
            ->value('id');
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
