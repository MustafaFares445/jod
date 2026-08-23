<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationEventType;
use App\Models\Campaign;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NotificationEventService
{
    public function notifyUser(
        User|string|null $user,
        NotificationEventType $eventType,
        string $title,
        string $body,
        string $category,
        string $priority = 'normal',
        ?string $referenceLabel = null,
        ?string $referencePath = null,
        ?string $organizationId = null,
        ?string $creatorId = null,
    ): ?Notification {
        $model = $user instanceof User
            ? $user
            : (filled($user) ? User::query()->find((string) $user) : null);

        if ($model === null || $model->status !== 'active') {
            return null;
        }

        return Notification::query()->create([
            'id' => (string) Str::uuid(),
            'title' => $title,
            'body' => $body,
            'mailbox' => 'inbox',
            'status' => 'unread',
            'category' => $category,
            'event_type' => $eventType->value,
            'recipient_scope' => $model->organization_id === null ? 'users' : 'organizations',
            'recipient_label' => $model->name,
            'priority' => $priority,
            'reference_label' => $referenceLabel,
            'reference_path' => $referencePath,
            'organization_id' => $organizationId ?? $model->organization_id,
            'creator_id' => $creatorId,
            'recipient_id' => $model->id,
            'sent_at' => now(),
            'read_at' => null,
        ]);
    }

    public function notifyOrganization(
        string $organizationId,
        NotificationEventType $eventType,
        string $title,
        string $body,
        string $category,
        string $priority = 'normal',
        ?string $referenceLabel = null,
        ?string $referencePath = null,
        ?string $creatorId = null,
        ?string $excludeUserId = null,
    ): int {
        $query = User::query()
            ->where('status', 'active')
            ->where('organization_id', $organizationId);

        if (filled($excludeUserId)) {
            $query->whereKeyNot($excludeUserId);
        }

        return $this->notifyQuery(
            $query,
            $eventType,
            $title,
            $body,
            $category,
            $priority,
            $referenceLabel,
            $referencePath,
            $organizationId,
            $creatorId,
        );
    }

    public function notifyAdmins(
        NotificationEventType $eventType,
        string $title,
        string $body,
        string $category,
        string $priority = 'normal',
        ?string $referenceLabel = null,
        ?string $referencePath = null,
        ?string $creatorId = null,
    ): int {
        return $this->notifyQuery(
            User::query()->where('status', 'active')->where('user_type', 'admin'),
            $eventType,
            $title,
            $body,
            $category,
            $priority,
            $referenceLabel,
            $referencePath,
            null,
            $creatorId,
        );
    }

    public function notifyCampaignParticipants(
        Campaign $campaign,
        NotificationEventType $eventType,
        string $title,
        string $body,
        string $category = 'campaign',
        string $priority = 'normal',
        ?string $referenceLabel = null,
        ?string $referencePath = null,
        ?string $creatorId = null,
    ): int {
        $userIds = collect()
            ->merge($campaign->donations()->whereNotNull('created_by')->pluck('created_by'))
            ->merge($campaign->applications()->whereNotNull('created_by')->pluck('created_by'))
            ->filter()
            ->unique()
            ->values();

        return $this->notifyUserIds(
            $userIds,
            $eventType,
            $title,
            $body,
            $category,
            $priority,
            $referenceLabel,
            $referencePath,
            (string) $campaign->organization_id,
            $creatorId,
        );
    }

    /** @param Collection<int, string|int> $userIds */
    public function notifyUserIds(
        Collection $userIds,
        NotificationEventType $eventType,
        string $title,
        string $body,
        string $category,
        string $priority = 'normal',
        ?string $referenceLabel = null,
        ?string $referencePath = null,
        ?string $organizationId = null,
        ?string $creatorId = null,
    ): int {
        if ($userIds->isEmpty()) {
            return 0;
        }

        return $this->notifyQuery(
            User::query()->where('status', 'active')->whereIn('id', $userIds->all()),
            $eventType,
            $title,
            $body,
            $category,
            $priority,
            $referenceLabel,
            $referencePath,
            $organizationId,
            $creatorId,
        );
    }

    private function notifyQuery(
        Builder $query,
        NotificationEventType $eventType,
        string $title,
        string $body,
        string $category,
        string $priority,
        ?string $referenceLabel,
        ?string $referencePath,
        ?string $organizationId,
        ?string $creatorId,
    ): int {
        $created = 0;

        $query->select(['id', 'name', 'status', 'organization_id'])
            ->chunkById(200, function ($users) use (
                $eventType,
                $title,
                $body,
                $category,
                $priority,
                $referenceLabel,
                $referencePath,
                $organizationId,
                $creatorId,
                &$created,
            ): void {
                foreach ($users as $user) {
                    if ($this->notifyUser(
                        $user,
                        $eventType,
                        $title,
                        $body,
                        $category,
                        $priority,
                        $referenceLabel,
                        $referencePath,
                        $organizationId,
                        $creatorId,
                    ) !== null) {
                        $created++;
                    }
                }
            });

        return $created;
    }
}
