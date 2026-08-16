<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostShare;
use App\Models\Report;
use App\Models\SavedPost;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class PostEngagementService
{
    /**
     * @return array{postId: string, isLiked: bool, likesCount: int}
     */
    public function like(User $user, string $postId): array
    {
        return DB::transaction(function () use ($user, $postId): array {
            $post = $this->findPublicPostForUpdate($postId);

            PostLike::query()->firstOrCreate([
                'user_id' => $user->id,
                'post_id' => $post->id,
            ]);

            return $this->likeState($post, true);
        });
    }

    /**
     * @return array{postId: string, isLiked: bool, likesCount: int}
     */
    public function unlike(User $user, string $postId): array
    {
        return DB::transaction(function () use ($user, $postId): array {
            $post = $this->findPublicPostForUpdate($postId);

            PostLike::query()
                ->where('user_id', $user->id)
                ->where('post_id', $post->id)
                ->delete();

            return $this->likeState($post, false);
        });
    }

    /**
     * @return array{postId: string, isSaved: bool, savesCount: int}
     */
    public function save(User $user, string $postId): array
    {
        return DB::transaction(function () use ($user, $postId): array {
            $post = $this->findPublicPostForUpdate($postId);

            SavedPost::query()->firstOrCreate([
                'user_id' => $user->id,
                'post_id' => $post->id,
            ]);

            return $this->saveState($post, true);
        });
    }

    /**
     * @return array{postId: string, isSaved: bool, savesCount: int}
     */
    public function unsave(User $user, string $postId): array
    {
        return DB::transaction(function () use ($user, $postId): array {
            $post = $this->findPublicPostForUpdate($postId);

            SavedPost::query()
                ->where('user_id', $user->id)
                ->where('post_id', $post->id)
                ->delete();

            return $this->saveState($post, false);
        });
    }

    /**
     * Record one completed share-sheet action.
     *
     * @return array{postId: string, sharesCount: int}
     */
    public function share(User $user, string $postId, ?string $channel = null): array
    {
        return DB::transaction(function () use ($user, $postId, $channel): array {
            $post = $this->findPublicPostForUpdate($postId);

            PostShare::query()->create([
                'post_id' => $post->id,
                'user_id' => $user->id,
                'channel' => filled($channel) ? trim((string) $channel) : null,
            ]);

            $sharesCount = PostShare::query()->where('post_id', $post->id)->count();
            $post->update(['shares_count' => $sharesCount]);

            return [
                'postId' => (string) $post->id,
                'sharesCount' => $sharesCount,
            ];
        });
    }

    /**
     * @param  array{reason: string, details?: string|null}  $data
     */
    public function report(User $user, string $postId, array $data): Report
    {
        return DB::transaction(function () use ($user, $postId, $data): Report {
            $post = $this->findPublicPostForUpdate($postId);
            $reason = trim($data['reason']);
            $details = filled($data['details'] ?? null) ? trim((string) $data['details']) : null;

            return Report::query()->create([
                'title' => mb_substr("Mobile post report: {$reason}", 0, 255),
                'description' => $details ?: $reason,
                'category' => $this->categoryForReason($reason),
                'status' => 'new',
                'severity' => 'medium',
                'entity_type' => 'post',
                'entity_id' => $post->id,
                'organization_id' => $post->organization_id,
                'reporter_id' => $user->id,
                'evidence' => [
                    'source' => 'mobile',
                    'reason' => $reason,
                    'details' => $details,
                ],
                'timeline' => [
                    [
                        'action' => 'created',
                        'actorId' => $user->id,
                        'at' => now()->toIso8601String(),
                    ],
                ],
            ]);
        });
    }

    private function findPublicPostForUpdate(string $postId): Post
    {
        return Post::query()
            ->whereKey($postId)
            ->where('status', 'published')
            ->lockForUpdate()
            ->firstOr(fn () => throw new ModelNotFoundException);
    }

    /**
     * @return array{postId: string, isLiked: bool, likesCount: int}
     */
    private function likeState(Post $post, bool $isLiked): array
    {
        $likesCount = PostLike::query()->where('post_id', $post->id)->count();
        $post->update(['reactions_count' => $likesCount]);

        return [
            'postId' => (string) $post->id,
            'isLiked' => $isLiked,
            'likesCount' => $likesCount,
        ];
    }

    /**
     * @return array{postId: string, isSaved: bool, savesCount: int}
     */
    private function saveState(Post $post, bool $isSaved): array
    {
        return [
            'postId' => (string) $post->id,
            'isSaved' => $isSaved,
            'savesCount' => SavedPost::query()->where('post_id', $post->id)->count(),
        ];
    }

    private function categoryForReason(string $reason): string
    {
        $category = strtolower($reason);

        return in_array($category, ['fraud', 'abuse', 'inappropriate', 'spam', 'other'], true)
            ? $category
            : 'other';
    }
}
