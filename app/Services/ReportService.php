<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationEventType;
use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function __construct(private readonly NotificationEventService $notifications) {}

    /** @return list<string> */
    public static function relations(): array
    {
        return [
            'organization',
            'reporter',
            'assignee',
            'reportedPost.organization',
            'reportedPost.campaign',
            'reportedPost.images',
            'reportedPost.author',
            'reportedPost.updatedBy',
            'reportedPost.reviewedBy',
            'reportedPost.approvedBy',
            'reportedPost.rejectedBy',
            'reportedCampaign.organization',
            'reportedCampaign.creator',
            'reportedCampaign.reviewedBy',
            'reportedCampaign.imageMedia',
            'reportedUser',
            'reportedOrganization',
        ];
    }

    public function paginate(array $params, ?string $organizationId = null): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $sort = (string) ($this->param($params, 'sort') ?? '-createdAt');

        $query = Report::query()->with(self::relations());

        if ($organizationId !== null && $organizationId !== '') {
            $query->where('organization_id', $organizationId);
        }

        $search = $params['searchQueries'] ?? $this->param($params, 'filter.search');

        $query
            ->when(($status = $this->param($params, 'filter.status')) && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when(($severity = $this->param($params, 'filter.severity')) && $severity !== 'all', fn (Builder $builder) => $builder->where('severity', $severity))
            ->when(($entityType = $this->param($params, 'filter.entityType')) && $entityType !== 'all', fn (Builder $builder) => $builder->where('entity_type', $entityType))
            ->when(($category = $this->param($params, 'filter.category')) && $category !== 'all', fn (Builder $builder) => $builder->where('category', $category))
            ->when($search, function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        match ($sort) {
            'createdAt', 'submittedAt' => $query->orderBy('created_at'),
            '-createdAt', '-submittedAt' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        return $query->paginate($perPage);
    }

    public function claim(Report $report, int|string|null $assigneeId, string $actorName, ?string $note = null): Report
    {
        if ($report->status !== 'new') {
            throw ValidationException::withMessages([
                'status' => ['Only new reports can be claimed.'],
            ]);
        }

        $report->update([
            'status' => 'in_progress',
            'assignee_id' => $assigneeId,
            'timeline' => $this->appendTimeline($report->timeline, 'claim', 'Report claimed', $actorName, $note),
        ]);

        $this->notifyReporter(
            $report,
            NotificationEventType::ReportInProgress,
            'بدأت مراجعة بلاغك',
            'تم استلام البلاغ من فريق المراجعة وبدأ العمل عليه.',
            'normal',
        );

        return $report;
    }

    public function requestInfo(Report $report, ?string $note, string $actorName): Report
    {
        if ($report->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'status' => ['Only in progress reports can request info.'],
            ]);
        }

        $report->update([
            'timeline' => $this->appendTimeline($report->timeline, 'request_info', 'Additional information requested', $actorName, $note),
        ]);

        $message = filled($note)
            ? 'طلب فريق المراجعة معلومات إضافية: '.$note
            : 'طلب فريق المراجعة معلومات إضافية بخصوص بلاغك.';

        $this->notifyReporter(
            $report,
            NotificationEventType::ReportInfoRequested,
            'مطلوب معلومات إضافية',
            $message,
            'high',
        );

        return $report;
    }

    public function close(Report $report, ?string $note, string $actorName): Report
    {
        if ($report->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'status' => ['Only in progress reports can be closed.'],
            ]);
        }

        $report->update([
            'status' => 'closed',
            'closed_at' => now(),
            'timeline' => $this->appendTimeline($report->timeline, 'close', 'Report closed', $actorName, $note),
        ]);

        $message = filled($note)
            ? 'تم إغلاق البلاغ. ملاحظة المراجعة: '.$note
            : 'تمت مراجعة البلاغ وإغلاقه.';

        $this->notifyReporter(
            $report,
            NotificationEventType::ReportClosed,
            'تم إغلاق البلاغ',
            $message,
            'normal',
        );

        return $report;
    }

    public function updateStatus(Report $report, string $status, string $actorName, ?string $note = null, int|string|null $assigneeId = null): Report
    {
        if ($report->status === $status) {
            return $report;
        }

        return match ($status) {
            'in_progress' => $this->claim($report, $assigneeId, $actorName, $note),
            'closed' => $this->close($report, $note, $actorName),
            default => throw ValidationException::withMessages([
                'status' => ['Unsupported report status transition.'],
            ]),
        };
    }

    private function notifyReporter(
        Report $report,
        NotificationEventType $eventType,
        string $title,
        string $body,
        string $priority,
    ): void {
        if (blank($report->reporter_id)) {
            return;
        }

        $this->notifications->notifyUser(
            (string) $report->reporter_id,
            $eventType,
            $title,
            $body,
            'report',
            $priority,
            $report->title,
            '/reports/'.$report->id,
            $report->organization_id !== null ? (string) $report->organization_id : null,
        );
    }

    private function appendTimeline(mixed $timeline, string $action, string $label, string $actorName, ?string $note = null): array
    {
        $timeline = $this->normalizeTimeline($timeline);

        $entry = [
            'action' => $action,
            'label' => $label,
            'at' => now()->toIso8601String(),
            'by' => $actorName,
        ];

        if ($note !== null && $note !== '') {
            $entry['note'] = $note;
        }

        $timeline[] = $entry;

        return $timeline;
    }

    private function normalizeTimeline(mixed $timeline): array
    {
        if ($timeline === null || $timeline === '') {
            return [];
        }

        if (is_array($timeline)) {
            return array_is_list($timeline) ? $timeline : [$timeline];
        }

        if (is_string($timeline)) {
            $decoded = json_decode($timeline, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_is_list($decoded) ? $decoded : [$decoded];
            }
        }

        return [];
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
