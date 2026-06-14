<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function paginate(array $params, ?string $organizationId = null): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $sort = (string) ($this->param($params, 'sort') ?? '-createdAt');

        $query = Report::query()
            ->with(['organization', 'reporter', 'assignee']);

        if ($organizationId !== null && $organizationId !== '') {
            $query->where('organization_id', $organizationId);
        }

        $query
            ->when(($status = $this->param($params, 'filter.status')) && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when(($severity = $this->param($params, 'filter.severity')) && $severity !== 'all', fn (Builder $builder) => $builder->where('severity', $severity))
            ->when(($entityType = $this->param($params, 'filter.entityType')) && $entityType !== 'all', fn (Builder $builder) => $builder->where('entity_type', $entityType))
            ->when(($category = $this->param($params, 'filter.category')) && $category !== 'all', fn (Builder $builder) => $builder->where('category', $category));

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
            'status' => 'waiting_response',
            'timeline' => $this->appendTimeline($report->timeline, 'request_info', 'Additional information requested', $actorName, $note),
        ]);

        return $report;
    }

    public function close(Report $report, ?string $note, string $actorName): Report
    {
        if (! in_array($report->status, ['in_progress', 'waiting_response'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Only active reports can be closed.'],
            ]);
        }

        $report->update([
            'status' => 'closed',
            'closed_at' => now(),
            'timeline' => $this->appendTimeline($report->timeline, 'close', 'Report closed', $actorName, $note),
        ]);

        return $report;
    }

    private function appendTimeline(?array $timeline, string $action, string $label, string $actorName, ?string $note = null): array
    {
        $timeline ??= [];
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
