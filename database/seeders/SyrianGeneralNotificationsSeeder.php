<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\NotificationEventType;
use App\Models\Notification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class SyrianGeneralNotificationsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasTable('users')) {
            return;
        }

        $data = json_decode(
            (string) file_get_contents(database_path('data/syrian_real_notifications.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->purgeExistingDemoNotifications();

        $users = DB::table('users')
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('user_type')->orWhere('user_type', '!=', 'admin');
            })
            ->orderBy('id')
            ->get(['id', 'name', 'organization_id']);

        if ($users->isEmpty()) {
            return;
        }

        foreach ($data['notifications'] as $index => $row) {
            $eventType = NotificationEventType::tryFrom((string) $row['event_type']);
            if ($eventType === null) {
                throw new \RuntimeException('Unknown notification event type: '.$row['event_type']);
            }
            if ($eventType->category() !== $row['category']) {
                throw new \RuntimeException('Notification category/event mismatch for '.$row['key']);
            }

            $reference = $this->resolveReference($row['entity'] ?? null);
            if (($row['entity'] ?? null) !== null && $reference === null) {
                throw new \RuntimeException('Unable to resolve notification entity: '.$row['key']);
            }

            $sourceId = $this->id('source:'.$row['key']);
            $batchId = $this->uuid('batch:'.$row['key']);
            $sentAt = now()->subDays((int) ($row['age_days'] ?? $index + 1));
            $scope = (string) ($row['scope'] ?? 'all');

            DB::table('notifications')->updateOrInsert(
                ['id' => $sourceId],
                $this->columns([
                    'id' => $sourceId,
                    'title' => $row['title'],
                    'body' => $row['body'],
                    'mailbox' => 'sent',
                    'status' => 'sent',
                    'category' => $row['category'],
                    'event_type' => $row['event_type'],
                    'recipient_scope' => $scope,
                    'recipient_label' => $this->scopeLabel($scope),
                    'priority' => $row['priority'] ?? 'normal',
                    'reference_label' => $row['reference_label'] ?? null,
                    'reference_path' => $reference['path'] ?? null,
                    'organization_id' => $reference['organization_id'] ?? null,
                    'creator_id' => null,
                    'recipient_id' => null,
                    'source_notification_id' => null,
                    'distribution_batch_id' => $batchId,
                    'sent_at' => $sentAt,
                    'read_at' => null,
                    'deleted_at' => null,
                    'created_at' => $sentAt,
                    'updated_at' => $sentAt,
                ]),
            );

            $eligible = $users->filter(function ($user) use ($scope): bool {
                return match ($scope) {
                    'users' => $user->organization_id === null,
                    'organizations' => $user->organization_id !== null,
                    default => true,
                };
            })->values();

            foreach ($eligible as $userIndex => $user) {
                $read = (($index + $userIndex) % 3) === 0;
                $copyId = $this->id('copy:'.$row['key'].':'.$user->id);
                $readAt = $read ? $sentAt->copy()->addHours(2 + (($userIndex + $index) % 18)) : null;

                DB::table('notifications')->updateOrInsert(
                    ['id' => $copyId],
                    $this->columns([
                        'id' => $copyId,
                        'title' => $row['title'],
                        'body' => $row['body'],
                        'mailbox' => 'inbox',
                        'status' => $read ? 'read' : 'unread',
                        'category' => $row['category'],
                        'event_type' => $row['event_type'],
                        'recipient_scope' => $scope,
                        'recipient_label' => $user->name,
                        'priority' => $row['priority'] ?? 'normal',
                        'reference_label' => $row['reference_label'] ?? null,
                        'reference_path' => $reference['path'] ?? null,
                        'organization_id' => $reference['organization_id'] ?? $user->organization_id,
                        'creator_id' => null,
                        'recipient_id' => $user->id,
                        'source_notification_id' => $sourceId,
                        'distribution_batch_id' => $batchId,
                        'sent_at' => $sentAt,
                        'read_at' => $readAt,
                        'deleted_at' => null,
                        'created_at' => $sentAt,
                        'updated_at' => $readAt ?? $sentAt,
                    ]),
                );
            }
        }
    }

    private function purgeExistingDemoNotifications(): void
    {
        if (Schema::hasTable('mobile_push_deliveries')) {
            DB::table('mobile_push_deliveries')->delete();
        }
        DB::table('notifications')->delete();
    }

    private function resolveReference(?array $entity): ?array
    {
        if ($entity === null) {
            return null;
        }

        $type = (string) ($entity['type'] ?? '');
        $title = (string) ($entity['title'] ?? '');

        if ($type === 'campaign') {
            $query = DB::table('campaigns')->where('title', $title);
            if (filled($entity['organization'] ?? null)) {
                $query->whereIn('organization_id', DB::table('organizations')
                    ->where('name', $entity['organization'])
                    ->select('id'));
            }
            $campaign = $query->first(['id', 'organization_id']);
            return $campaign === null ? null : [
                'path' => '/campaigns/'.$campaign->id,
                'organization_id' => $campaign->organization_id,
            ];
        }

        if ($type === 'post') {
            $post = DB::table('posts')->where('title', $title)->where('status', 'published')->first(['id', 'organization_id']);
            return $post === null ? null : [
                'path' => '/posts/'.$post->id,
                'organization_id' => $post->organization_id,
            ];
        }

        return null;
    }

    private function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'users' => 'المستخدمون',
            'organizations' => 'المنظمات',
            default => 'الجميع',
        };
    }

    private function columns(array $attributes): array
    {
        $columns = array_flip(Schema::getColumnListing('notifications'));
        return array_intersect_key($attributes, $columns);
    }

    private function id(string $key): string
    {
        return 'jod-notification-'.substr(hash('sha256', $key), 0, 32);
    }

    private function uuid(string $key): string
    {
        $hex = substr(hash('sha256', 'jod-notification-batch:'.$key), 0, 32);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-5'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }
}
