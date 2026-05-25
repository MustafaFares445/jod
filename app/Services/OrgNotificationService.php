<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class OrgNotificationService
{
    public function paginate(array $params, int $organizationId): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $sort = (string) ($params['sort'] ?? '-sentAt');

        $query = Notification::query()
            ->with('createdBy')
            ->where('organization_id', $organizationId)
            ->when(($mailbox = $this->param($params, 'filter.mailbox')) && $mailbox !== 'all', fn (Builder $builder) => $builder->where('mailbox', $mailbox))
            ->when(($status = $this->param($params, 'filter.status')) && $status !== 'all', fn (Builder $builder) => $builder->where('status', $status))
            ->when(($category = $this->param($params, 'filter.category')) && $category !== 'all', fn (Builder $builder) => $builder->where('category', $category))
            ->when(($scope = $this->param($params, 'filter.recipientScope')) && $scope !== 'all', fn (Builder $builder) => $builder->where('recipient_scope', $scope))
            ->when(($date = $this->param($params, 'filter.date')) && $date !== 'all', function (Builder $builder) use ($date): void {
                if ($date === 'today') {
                    $builder->whereDate('created_at', now()->toDateString());
                    return;
                }

                if ($date === 'last_7_days') {
                    $builder->where('created_at', '>=', now()->subDays(7));
                }
            });

        match ($sort) {
            'sentAt' => $query->orderBy('sent_at'),
            '-sentAt' => $query->orderByDesc('sent_at'),
            default => $query->orderByDesc('sent_at'),
        };

        return $query->paginate($perPage);
    }

    public function create(array $attributes, int $organizationId, int $userId): Notification
    {
        return Notification::create([
            'organization_id' => $organizationId,
            'title' => $attributes['title'],
            'body' => $attributes['body'],
            'mailbox' => 'sent',
            'status' => 'sent',
            'category' => $attributes['category'],
            'recipient_scope' => $attributes['recipientScope'] ?? 'organizations',
            'recipient_label' => $attributes['recipientLabel'] ?? null,
            'priority' => $attributes['priority'] ?? 'normal',
            'reference_label' => $attributes['referenceLabel'] ?? null,
            'reference_path' => $attributes['referencePath'] ?? null,
            'created_by' => $userId,
            'sent_at' => now(),
        ]);
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
