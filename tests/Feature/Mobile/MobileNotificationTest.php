<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_are_paginated_filterable_and_scoped_to_personal_inbox(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $unread = Notification::factory()->create([
            'recipient_id' => $user->id,
            'title' => 'Donation received',
            'category' => 'donation',
            'priority' => 'high',
            'sent_at' => now(),
        ]);
        $read = Notification::factory()->read()->create([
            'recipient_id' => $user->id,
            'title' => 'System update',
            'category' => 'system',
            'priority' => 'normal',
            'sent_at' => now()->subMinute(),
        ]);
        $sent = Notification::factory()->create([
            'recipient_id' => $user->id,
            'mailbox' => 'sent',
            'status' => 'sent',
            'title' => 'Sent copy',
        ]);
        $other = Notification::factory()->create([
            'recipient_id' => $otherUser->id,
            'title' => 'Other user',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/mobile/me/notifications?perPage=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.perPage', 10)
            ->assertJsonPath('data.0.id', (string) $unread->id)
            ->assertJsonPath('data.0.isRead', false)
            ->assertJsonPath('data.1.id', (string) $read->id)
            ->assertJsonMissing(['id' => (string) $sent->id])
            ->assertJsonMissing(['id' => (string) $other->id]);

        $this->getJson('/api/mobile/me/notifications?status=unread&category=donation&priority=high')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $unread->id)
            ->assertJsonPath('data.0.category', 'donation')
            ->assertJsonPath('data.0.priority', 'high');
    }

    public function test_unread_count_is_scoped_to_personal_inbox(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Notification::factory()->count(2)->create(['recipient_id' => $user->id]);
        Notification::factory()->read()->create(['recipient_id' => $user->id]);
        Notification::factory()->create([
            'recipient_id' => $user->id,
            'mailbox' => 'sent',
            'status' => 'unread',
        ]);
        Notification::factory()->create(['recipient_id' => $otherUser->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/mobile/me/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unreadCount', 2);
    }

    public function test_notification_detail_is_scoped_to_authenticated_user_and_inbox(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $own = Notification::factory()->create(['recipient_id' => $user->id]);
        $other = Notification::factory()->create(['recipient_id' => $otherUser->id]);
        $sent = Notification::factory()->create([
            'recipient_id' => $user->id,
            'mailbox' => 'sent',
            'status' => 'sent',
        ]);
        Sanctum::actingAs($user);

        $this->getJson("/api/mobile/me/notifications/{$own->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $own->id);

        $this->getJson("/api/mobile/me/notifications/{$other->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');

        $this->getJson("/api/mobile/me/notifications/{$sent->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_notification_can_be_marked_read_and_unread_idempotently(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create(['recipient_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/mobile/me/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.status', 'read')
            ->assertJsonPath('data.isRead', true);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
        $readAt = $notification->read_at?->toIso8601String();

        $this->patchJson("/api/mobile/me/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.status', 'read');
        $this->assertSame($readAt, $notification->refresh()->read_at?->toIso8601String());

        $this->patchJson("/api/mobile/me/notifications/{$notification->id}/unread")
            ->assertOk()
            ->assertJsonPath('data.status', 'unread')
            ->assertJsonPath('data.isRead', false)
            ->assertJsonPath('data.readAt', null);

        $this->patchJson("/api/mobile/me/notifications/{$notification->id}/unread")
            ->assertOk()
            ->assertJsonPath('data.status', 'unread');
        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_mark_all_read_only_updates_authenticated_users_unread_inbox_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $first = Notification::factory()->create(['recipient_id' => $user->id]);
        $second = Notification::factory()->create(['recipient_id' => $user->id]);
        $alreadyRead = Notification::factory()->read()->create(['recipient_id' => $user->id]);
        $other = Notification::factory()->create(['recipient_id' => $otherUser->id]);
        $sent = Notification::factory()->create([
            'recipient_id' => $user->id,
            'mailbox' => 'sent',
            'status' => 'unread',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/mobile/me/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updatedCount', 2)
            ->assertJsonPath('data.unreadCount', 0);

        $this->assertSame('read', $first->refresh()->status);
        $this->assertNotNull($first->read_at);
        $this->assertSame('read', $second->refresh()->status);
        $this->assertSame('read', $alreadyRead->refresh()->status);
        $this->assertSame('unread', $other->refresh()->status);
        $this->assertSame('unread', $sent->refresh()->status);
    }

    public function test_notification_filters_are_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/mobile/me/notifications?perPage=0&status=sent&category=unknown&priority=urgent')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['perPage', 'status', 'category', 'priority'], 'error.details');
    }

    public function test_notification_endpoints_require_authentication(): void
    {
        $this->getJson('/api/mobile/me/notifications')->assertUnauthorized();
        $this->getJson('/api/mobile/me/notifications/unread-count')->assertUnauthorized();
        $this->getJson('/api/mobile/me/notifications/notification-id')->assertUnauthorized();
        $this->patchJson('/api/mobile/me/notifications/notification-id/read')->assertUnauthorized();
        $this->patchJson('/api/mobile/me/notifications/notification-id/unread')->assertUnauthorized();
        $this->patchJson('/api/mobile/me/notifications/read-all')->assertUnauthorized();
    }
}
