<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Post;
use App\Models\PostLike;
use App\Models\Report;
use App\Models\SavedPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PostEngagementService
{
    /** @return array{postId: string, isLiked: bool, likesCount: int} */
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

    /** @return array{postId: string, isLiked: bool, likesCount: int} */
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

    /** @return array{postId: string, isSaved: bool, savesCount: int} */
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

    /** @return array{postId: string, isSaved: bool, savesCount: int} */
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
     * @param  array{reason: string, details?: string|null}  $data
     */
    public function report(User $user, string $postId, array $data): Report
    {
        return DB::transaction(function () use ($user, $postId, $data): Report {
            $post = $this->findPublicPostForUpdate($postId);
            $reason = trim($data['reason']);
            $details = filled($data['details'] ?? null) ? trim((string) $data['details']) : null;
            $label = $this->labelForReason($reason);

            return Report::query()->create([
                'title' => mb_substr("بلاغ عن منشور: {$label}", 0, 255),
                'description' => $details ?: $label,
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
                    'reasonLabel' => $label,
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
            ->whereIn('status', ['published', 'approved'])
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @return array{postId: string, isLiked: bool, likesCount: int} */
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

    /** @return array{postId: string, isSaved: bool, savesCount: int} */
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
        return match ($reason) {
            'abusive' => 'abuse',
            'fraud', 'impersonation' => 'fraud',
            default => 'other',
        };
    }

    private function labelForReason(string $reason): string
    {
        return match ($reason) {
            'misleading' => 'محتوى مضلل',
            'abusive' => 'محتوى مسيء أو غير لائق',
            'fraud' => 'احتيال أو طلب تبرع مشبوه',
            'impersonation' => 'انتحال جهة أو شخصية',
            default => 'سبب آخر',
        };
    }
}
