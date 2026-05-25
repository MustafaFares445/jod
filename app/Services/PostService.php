<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\PostData;
use App\Models\Campaign;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class PostService
{
    public function paginate(array $params, int $organizationId): LengthAwarePaginator
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

        return $query->paginate($perPage);
    }

    public function create(PostData $data, int $organizationId): Post
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

    public function update(Post $post, PostData $data, int $organizationId): Post
    {
        $campaignId = $this->resolveCampaignId($data->campaignTitle, $organizationId);

        $post->update([
            'title' => $data->title,
            'summary' => $data->summary,
            'type' => $data->type,
            'status' => $data->status,
            'author_name' => $data->authorName,
            'location' => $data->location,
            'campaign_id' => $campaignId,
            'published_at' => $data->status === 'published'
                ? ($post->published_at ?? now())
                : ($data->status === 'draft' ? null : $post->published_at),
        ]);

        return $post;
    }

    public function publish(Post $post): Post
    {
        if ($post->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft posts can be published.'],
            ]);
        }

        $post->update(['status' => 'published', 'published_at' => now()]);

        return $post;
    }

    public function archive(Post $post): Post
    {
        if ($post->status !== 'published') {
            throw ValidationException::withMessages([
                'status' => ['Only published posts can be archived.'],
            ]);
        }

        $post->update(['status' => 'archived']);

        return $post;
    }

    public function restore(Post $post): Post
    {
        if ($post->status !== 'archived') {
            throw ValidationException::withMessages([
                'status' => ['Only archived posts can be restored.'],
            ]);
        }

        $post->update(['status' => 'draft']);

        return $post;
    }

    public function delete(Post $post): void
    {
        $post->delete();
    }

    private function resolveCampaignId(?string $campaignTitle, int $organizationId): ?int
    {
        if (!$campaignTitle) {
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
