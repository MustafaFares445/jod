<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\MediaModel;
use App\Enums\NotificationEventType;
use App\Models\Media;
use App\Models\MediaLike;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Report;
use App\Models\SavedMedia;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MediaEngagementService
{
    public function __construct(private readonly NotificationEventService $notifications) {}

    /** @return array{mediaId: string, isLiked: bool, likesCount: int} */
    public function like(string $mediaId, User $user): array
    {
        return DB::transaction(function () use ($mediaId, $user): array {
            $media = $this->publicVideoForUpdate($mediaId);
            $like = MediaLike::query()->firstOrCreate([
                'user_id' => $user->id,
                'media_id' => $media->id,
            ]);

            if ($like->wasRecentlyCreated) {
                $media->increment('reactions_count');
                $media->refresh();
            }

            return [
                'mediaId' => (string) $media->id,
                'isLiked' => true,
                'likesCount' => (int) $media->reactions_count,
            ];
        });
    }

    /** @return array{mediaId: string, isLiked: bool, likesCount: int} */
    public function unlike(string $mediaId, User $user): array
    {
        return DB::transaction(function () use ($mediaId, $user): array {
            $media = $this->publicVideoForUpdate($mediaId);
            $deleted = MediaLike::query()
                ->where('user_id', $user->id)
                ->where('media_id', $media->id)
                ->delete();

            if ($deleted > 0 && (int) $media->reactions_count > 0) {
                $media->decrement('reactions_count');
                $media->refresh();
            }

            return [
                'mediaId' => (string) $media->id,
                'isLiked' => false,
                'likesCount' => (int) $media->reactions_count,
            ];
        });
    }

    /** @return array{mediaId: string, isSaved: bool, savesCount: int} */
    public function save(string $mediaId, User $user): array
    {
        return DB::transaction(function () use ($mediaId, $user): array {
            $media = $this->publicVideoForUpdate($mediaId);
            $save = SavedMedia::query()->firstOrCreate([
                'user_id' => $user->id,
                'media_id' => $media->id,
            ]);

            if ($save->wasRecentlyCreated) {
                $media->increment('saves_count');
                $media->refresh();
            }

            return [
                'mediaId' => (string) $media->id,
                'isSaved' => true,
                'savesCount' => (int) $media->saves_count,
            ];
        });
    }

    /** @return array{mediaId: string, isSaved: bool, savesCount: int} */
    public function unsave(string $mediaId, User $user): array
    {
        return DB::transaction(function () use ($mediaId, $user): array {
            $media = $this->publicVideoForUpdate($mediaId);
            $deleted = SavedMedia::query()
                ->where('user_id', $user->id)
                ->where('media_id', $media->id)
                ->delete();

            if ($deleted > 0 && (int) $media->saves_count > 0) {
                $media->decrement('saves_count');
                $media->refresh();
            }

            return [
                'mediaId' => (string) $media->id,
                'isSaved' => false,
                'savesCount' => (int) $media->saves_count,
            ];
        });
    }

    public function report(string $mediaId, User $user, string $reason, ?string $details = null): Report
    {
        $media = $this->publicVideo($mediaId)->loadMissing('post');
        $label = $this->reasonLabel($reason);
        $description = filled($details)
            ? "{$label}: ".trim((string) $details)
            : $label;
        $modelType = $media->model_type instanceof MediaModel
            ? $media->model_type->value
            : (string) $media->model_type;
        $organizationId = $modelType === MediaModel::ORGANIZATION->value
            ? (string) $media->model_id
            : ($media->post?->organization_id ? (string) $media->post->organization_id : null);

        $report = Report::query()->create([
            'title' => 'بلاغ عن ريل: '.$label,
            'description' => $description,
            'category' => $this->reportCategory($reason),
            'status' => 'new',
            'severity' => in_array($reason, ['abusive', 'fraud', 'impersonation'], true) ? 'high' : 'medium',
            'entity_type' => 'media',
            'entity_id' => $media->id,
            'organization_id' => $organizationId,
            'reporter_id' => $user->id,
            'evidence' => [
                'source' => 'mobile_app',
                'reason' => $reason,
                'reasonLabel' => $label,
                'details' => $details,
                'mediaUrl' => $media->publicUrl(),
                'originalName' => $media->original_name,
                'mediaModel' => $modelType,
                'postId' => $modelType === MediaModel::POST->value ? (string) $media->model_id : null,
            ],
            'timeline' => [[
                'action' => 'submitted',
                'label' => 'Report submitted',
                'at' => now()->toIso8601String(),
                'by' => (string) $user->name,
            ]],
        ]);

        $this->notifications->notifyUser(
            (string) $user->id,
            NotificationEventType::ReportSubmitted,
            'تم استلام بلاغك',
            'تم استلام بلاغك عن الريل وسيقوم فريق المراجعة بمتابعته.',
            'report',
            'normal',
            $report->title,
            '/reports/'.$report->id,
            $organizationId,
        );

        $this->notifications->notifyAdmins(
            NotificationEventType::ReportSubmitted,
            'بلاغ جديد عن ريل',
            "أرسل {$user->name} بلاغاً عن ريل منشور. السبب: {$label}.",
            'report',
            'high',
            $report->title,
            '/admin/reports/'.$report->id,
            $organizationId,
            (string) $user->id,
        );

        return $report;
    }

    private function publicVideoForUpdate(string $mediaId): Media
    {
        return $this->publicVideoQuery()
            ->whereKey($mediaId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function publicVideo(string $mediaId): Media
    {
        return $this->publicVideoQuery()
            ->whereKey($mediaId)
            ->firstOrFail();
    }

    /** @return Builder<Media> */
    private function publicVideoQuery(): Builder
    {
        return Media::query()
            ->where('prop', 'videos')
            ->where(function (Builder $query): void {
                $query->where(function (Builder $organizationVideo): void {
                    $organizationVideo
                        ->where('model_type', MediaModel::ORGANIZATION->value)
                        ->whereIn('model_id', Organization::query()
                            ->where('status', 'active')
                            ->select('id'));
                })->orWhere(function (Builder $postVideo): void {
                    $postVideo
                        ->where('model_type', MediaModel::POST->value)
                        ->whereIn('post_id', Post::query()
                            ->where('status', 'published')
                            ->whereNull('deleted_at')
                            ->select('id'));
                });
            });
    }

    private function reportCategory(string $reason): string
    {
        return match ($reason) {
            'misleading' => 'inappropriate',
            'abusive' => 'abuse',
            'fraud', 'impersonation' => 'fraud',
            default => 'other',
        };
    }

    private function reasonLabel(string $reason): string
    {
        return match ($reason) {
            'misleading' => 'محتوى مضلل',
            'abusive' => 'إساءة أو تنمر',
            'fraud' => 'احتيال أو طلب مشبوه',
            'impersonation' => 'انتحال صفة',
            default => 'سبب آخر',
        };
    }
}
