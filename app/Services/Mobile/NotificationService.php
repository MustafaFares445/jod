<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    /**
     * @param  array{perPage?: int, status?: string, category?: string, priority?: string}  $params
     */
    public function paginateForUser(User $user, array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));

        return $this->inboxForUser($user)
            ->when(
                filled($params['status'] ?? null),
                fn (Builder $query) => $query->where('status', $params['status']),
            )
            ->when(
                filled($params['category'] ?? null),
                fn (Builder $query) => $query->where('category', $params['category']),
            )
            ->when(
                filled($params['priority'] ?? null),
                fn (Builder $query) => $query->where('priority', $params['priority']),
            )
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function unreadCount(User $user): int
    {
        return $this->inboxForUser($user)
            ->where('status', 'unread')
            ->count();
    }

    public function findForUser(User $user, string $notificationId): ?Notification
    {
        return $this->inboxForUser($user)
            ->whereKey($notificationId)
            ->first();
    }

    public function markRead(User $user, string $notificationId): ?Notification
    {
        $notification = $this->findForUser($user, $notificationId);

        if ($notification === null) {
            return null;
        }

        if ($notification->status !== 'read') {
            $notification->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }

        return $notification->refresh();
    }

    public function markUnread(User $user, string $notificationId): ?Notification
    {
        $notification = $this->findForUser($user, $notificationId);

        if ($notification === null) {
            return null;
        }

        if ($notification->status !== 'unread' || $notification->read_at !== null) {
            $notification->update([
                'status' => 'unread',
                'read_at' => null,
            ]);
        }

        return $notification->refresh();
    }

    public function markAllRead(User $user): int
    {
        return $this->inboxForUser($user)
            ->where('status', 'unread')
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
    }

    private function inboxForUser(User $user): Builder
    {
        return Notification::query()
            ->where('recipient_id', $user->id)
            ->where('mailbox', 'inbox');
    }
}
