<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\NotificationEventType;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\Report;
use App\Models\SavedPost;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Support\Facades\DB;

class PostEngagementService
{
    public function __construct(private readonly NotificationEventService $notifications) {}

    /** @return array{postId: string, isLiked: bool, likesCount: int} */
    public function like(User $user, string $postId): array
    {
        return DB::transaction(function () use ($user, $postId): array {
            $post = $this->findPublicPostForUpdate($postId);

            $like = PostLike::query()->firstOrCreate([
                'user_id' => $user->id,
                'post_id' => $post->id,
            ]);

            if ($like->wasRecentlyCreated) {
                $post->increment('reactions_count');
                $post->refresh();
            }

            return $this->likeState($post, true);
        });
    }

    /** @return array{postId: string, isLiked: bool, likesCount: int} */
    public function unlike(User $user, string $postId): array
    {
        return DB::transaction(function () use ($user, $postId): array {
            $post = $this->findPublicPostForUpdate($postId);

            $deleted = PostLike::query()
                ->where('user_id', $user->id)
                ->where('post_id', $post->id)
                ->delete();

            if ($deleted > 0 && (int) $post->reactions_count > 0) {
                $post->decrement('reactions_count');
                $post->refresh();
            }

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

            $report = Report::query()->create([
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

            $this->notifications->notifyUser(
                $user,
                NotificationEventType::ReportSubmitted,
                'تم استلام بلاغك',
                'تم إرسال البلاغ بنجاح وسيتم مراجعته من الإدارة.',
                'report',
                'normal',
                $report->title,
                '/reports/'.$report->id,
                $report->organization_id !== null ? (string) $report->organization_id : null,
            );

            $this->notifications->notifyAdmins(
                NotificationEventType::ReportSubmitted,
                'بلاغ جديد يحتاج للمراجعة',
                "تم استلام بلاغ جديد من {$user->name}: {$report->title}",
                'report',
                'high',
                $report->title,
                '/admin/reports/'.$report->id,
                (string) $user->id,
            );

            return $report;
        });
    }

    private function findPublicPostForUpdate(string $postId): Post
    {
        return Post::query()
            ->whereKey($postId)
            ->where('status', 'published')
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @return array{postId: string, isLiked: bool, likesCount: int} */
    private function likeState(Post $post, bool $isLiked): array
    {
        return [
            'postId' => (string) $post->id,
            'isLiked' => $isLiked,
            'likesCount' => max(0, (int) $post->reactions_count),
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
