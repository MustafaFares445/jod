<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\FanOutNotification;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

class NotificationDistributionService
{
    public function dispatch(Notification $notification): Notification
    {
        $this->ensureSentSource($notification);

        if (blank($notification->distribution_batch_id)) {
            $notification->forceFill([
                'distribution_batch_id' => (string) Str::uuid(),
            ])->saveQuietly();
        }

        FanOutNotification::dispatch(
            (string) $notification->id,
            (string) $notification->distribution_batch_id,
        )->afterCommit();

        return $notification->refresh();
    }

    public function resend(Notification $notification): Notification
    {
        $this->ensureSentSource($notification);

        $notification->forceFill([
            'mailbox' => 'sent',
            'status' => 'sent',
            'read_at' => null,
            'sent_at' => now(),
            'distribution_batch_id' => (string) Str::uuid(),
        ])->saveQuietly();

        FanOutNotification::dispatch(
            (string) $notification->id,
            (string) $notification->distribution_batch_id,
        )->afterCommit();

        return $notification->refresh();
    }

    public function fanOut(string $notificationId, string $distributionBatchId): int
    {
        $source = Notification::query()
            ->whereKey($notificationId)
            ->whereNull('recipient_id')
            ->where('mailbox', 'sent')
            ->where('distribution_batch_id', $distributionBatchId)
            ->first();

        if ($source === null) {
            return 0;
        }

        $created = 0;

        $this->recipientQuery($source)
            ->select('id')
            ->chunkById(200, function ($users) use ($source, $distributionBatchId, &$created): void {
                foreach ($users as $user) {
                    $inbox = Notification::query()->firstOrCreate(
                        [
                            'distribution_batch_id' => $distributionBatchId,
                            'recipient_id' => $user->id,
                        ],
                        [
                            'source_notification_id' => $source->id,
                            'title' => $source->title,
                            'body' => $source->body,
                            'mailbox' => 'inbox',
                            'status' => 'unread',
                            'category' => $source->category,
                            'recipient_scope' => $source->recipient_scope,
                            'recipient_label' => $source->recipient_label,
                            'priority' => $source->priority,
                            'reference_label' => $source->reference_label,
                            'reference_path' => $source->reference_path,
                            'organization_id' => $source->organization_id,
                            'creator_id' => $source->creator_id,
                            'sent_at' => $source->sent_at ?? now(),
                            'read_at' => null,
                        ],
                    );

                    if ($inbox->wasRecentlyCreated) {
                        $created++;
                    }
                }
            });

        return $created;
    }

    /**
     * Admin scopes are platform-wide; organization sources are always constrained to their tenant.
     * recipient_label remains display metadata and never participates in audience selection.
     *
     * @return Builder<User>
     */
    private function recipientQuery(Notification $source): Builder
    {
        $query = User::query()
            ->where('status', 'active')
            ->where(function (Builder $builder): void {
                $builder->whereNull('user_type')
                    ->orWhere('user_type', '!=', 'admin');
            });

        if (filled($source->creator_id)) {
            $query->where('id', '!=', $source->creator_id);
        }

        if (filled($source->organization_id)) {
            return $query->where('organization_id', $source->organization_id);
        }

        return match ($source->recipient_scope) {
            'users' => $query->whereNull('organization_id'),
            'organizations' => $query->whereNotNull('organization_id'),
            default => $query,
        };
    }

    private function ensureSentSource(Notification $notification): void
    {
        if ($notification->recipient_id !== null || $notification->mailbox !== 'sent') {
            throw new InvalidArgumentException('Only shared sent notifications can be distributed.');
        }
    }
}
