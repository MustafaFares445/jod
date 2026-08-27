<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\MediaModel;
use App\Enums\NotificationEventType;
use App\Models\Media;
use App\Models\MediaLike;
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
        $media = $this->publicVideo($mediaId);
        $label = $this->reasonLabel($reason);
        $description = filled($details)
            ? "{$label}: ".trim((string) $details)
            : $label;

        $report = Report::query()->create([
            'title' => 'بلاغ عن ريل: '.$label,
            'description' => $description,
            'category' => $this->reportCategory($reason),
            'status' => 'new',
            'severity' => in_array($reason, ['abusive', 'fraud', 'impersonation'], true) ? 'high' : 'medium',
            'entity_type' => 'media',
            'entity_id' => $media->id,
            'organization_id' => $media->model_id,
            'reporter_id' => $user->id,
            'evidence' => [
                'source' => 'mobile_app',
                'reason' => $reason,
                'reasonLabel' => $label,
                'details' => $details,
                'mediaUrl' => $media->publicUrl(),
                'originalName' => $media->original_name,
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
            $media->model_id,
        );

        $this->notifications->notifyAdmins(
            NotificationEventType::ReportSubmitted,
            'بلاغ جديد عن ريل',
            "أرسل {$user->name} بلاغاً عن ريل تابع لمنظمة. السبب: {$label}.",
            'report',
            'high',
            $report->title,
            '/admin/reports/'.$report->id,
            $media->model_id,
            (string) $user->id,
        );

        return $report;
    }

    private function publicVideoForUpdate(string $mediaId): Media
    {
        return Media::query()
            ->whereKey($mediaId)
            ->where('model_type', MediaModel::ORGANIZATION->value)
            ->where('prop', 'videos')
            ->whereHas('organization', fn (Builder $organization) => $organization->where('status', 'active'))
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function publicVideo(string $mediaId): Media
    {
        return Media::query()
            ->whereKey($mediaId)
            ->where('model_type', MediaModel::ORGANIZATION->value)
            ->where('prop', 'videos')
            ->whereHas('organization', fn (Builder $organization) => $organization->where('status', 'active'))
            ->firstOrFail();
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
