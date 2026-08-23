<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserPostService
{
    public function __construct(private readonly PostImageService $imageService) {}

    /**
     * @param  array{page?: int, perPage?: int, filter?: array{status?: string}, sort?: string}  $params
     */
    public function paginate(User $user, array $params): LengthAwarePaginator
    {
        $query = Post::query()->with('images')->where('author_id', $user->id);
        $status = $params['filter']['status'] ?? null;

        if ($status === 'active') {
            $query->whereIn('status', ['published', 'approved']);
        } elseif ($status) {
            $query->where('status', $status);
        }

        [$column, $direction] = $this->normalizeSort($params['sort'] ?? '-createdAt');

        return $query
            ->orderBy($column, $direction)
            ->orderBy('id')
            ->paginate((int) ($params['perPage'] ?? 20));
    }

    /**
     * @param  array{type: string, title?: string|null, details?: string|null, city?: string|null, categoryId?: string|null, saveAsDraft?: bool}  $data
     */
    public function create(User $user, array $data): Post
    {
        return DB::transaction(function () use ($user, $data): Post {
            $isDraft = (bool) ($data['saveAsDraft'] ?? false);
            $post = Post::query()->create([
                'title' => $data['title'] ?? null,
                'summary' => $this->summaryFromDetails($data['details'] ?? null),
                'content' => $data['details'] ?? null,
                'type' => $data['type'],
                'status' => $isDraft ? 'draft' : 'pending',
                'location' => $data['city'] ?? null,
                'category_id' => $data['categoryId'] ?? null,
                'author_id' => $user->id,
                'updated_by' => $user->id,
                'submitted_at' => $isDraft ? null : now(),
            ]);

            return $post->load('images');
        });
    }

    /**
     * @param  array{type?: string|null, title?: string|null, details?: string|null, city?: string|null, categoryId?: string|null}  $data
     */
    public function update(Post $post, array $data): Post
    {
        $attributes = [];

        if (array_key_exists('title', $data)) {
            $attributes['title'] = $data['title'];
        }

        if (array_key_exists('details', $data)) {
            $attributes['summary'] = $this->summaryFromDetails($data['details']);
            $attributes['content'] = $data['details'];
        }

        if (array_key_exists('type', $data)) {
            $attributes['type'] = $data['type'];
        }

        if (array_key_exists('city', $data)) {
            $attributes['location'] = $data['city'];
        }

        if (array_key_exists('categoryId', $data)) {
            $attributes['category_id'] = $data['categoryId'];
        }

        if ($attributes !== []) {
            $attributes['updated_by'] = $post->author_id;
            $post->update($attributes);
        }

        return $post->refresh()->load('images');
    }

    public function submit(Post $post): Post
    {
        return DB::transaction(function () use ($post): Post {
            $lockedPost = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedPost->status, ['draft', 'rejected'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft or rejected posts can be submitted.'],
                ]);
            }

            $lockedPost->update([
                'status' => 'pending',
                'submitted_at' => now(),
                'updated_by' => $lockedPost->author_id,
                'rejection_reason' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
            ]);

            return $lockedPost->refresh()->load('images');
        });
    }

    public function archive(Post $post): Post
    {
        return DB::transaction(function () use ($post): Post {
            $lockedPost = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedPost->status, ['published', 'approved'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only active posts can be archived.'],
                ]);
            }

            $lockedPost->update([
                'status' => 'archived',
                'updated_by' => $lockedPost->author_id,
            ]);

            return $lockedPost->refresh()->load('images');
        });
    }

    public function repost(Post $post): Post
    {
        return DB::transaction(function () use ($post): Post {
            $lockedPost = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

            if ($lockedPost->status !== 'archived') {
                throw ValidationException::withMessages([
                    'status' => ['Only archived posts can be reposted.'],
                ]);
            }

            $lockedPost->update([
                'status' => 'published',
                'published_at' => Carbon::now(),
                'updated_by' => $lockedPost->author_id,
            ]);

            return $lockedPost->refresh()->load('images');
        });
    }

    public function delete(Post $post): void
    {
        $this->imageService->purge($post);
        $post->delete();
    }

    /** @return array{0: string, 1: string} */
    private function normalizeSort(string $sort): array
    {
        return match ($sort) {
            'createdAt' => ['created_at', 'asc'],
            'updatedAt' => ['updated_at', 'asc'],
            '-updatedAt' => ['updated_at', 'desc'],
            'title' => ['title', 'asc'],
            '-title' => ['title', 'desc'],
            default => ['created_at', 'desc'],
        };
    }

    private function summaryFromDetails(?string $details): ?string
    {
        if ($details === null) {
            return null;
        }

        return mb_substr($details, 0, 255);
    }
}
