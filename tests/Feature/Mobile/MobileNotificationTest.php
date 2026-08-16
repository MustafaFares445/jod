<?php

declare(strict_types=1);
use App\Models\Notification;
use App\Models\User;
use App\Services\Auth\TokenService;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('notifications are paginated filterable and scoped to personal inbox', function () {
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
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

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
});
test('unread count is scoped to personal inbox', function () {
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
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->getJson('/api/mobile/me/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unreadCount', 2);
});
test('notification detail is scoped to authenticated user and inbox', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $own = Notification::factory()->create(['recipient_id' => $user->id]);
    $other = Notification::factory()->create(['recipient_id' => $otherUser->id]);
    $sent = Notification::factory()->create([
        'recipient_id' => $user->id,
        'mailbox' => 'sent',
        'status' => 'sent',
    ]);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->getJson("/api/mobile/me/notifications/{$own->id}")
        ->assertOk()
        ->assertJsonPath('data.id', (string) $own->id);

    $this->getJson("/api/mobile/me/notifications/{$other->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');

    $this->getJson("/api/mobile/me/notifications/{$sent->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});
test('notification can be marked read and unread idempotently', function () {
    $user = User::factory()->create();
    $notification = Notification::factory()->create(['recipient_id' => $user->id]);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->patchJson("/api/mobile/me/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.status', 'read')
        ->assertJsonPath('data.isRead', true);

    $notification->refresh();
    expect($notification->read_at)->not->toBeNull();
    $readAt = $notification->read_at?->toIso8601String();

    $this->patchJson("/api/mobile/me/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.status', 'read');
    expect($notification->refresh()->read_at?->toIso8601String())->toBe($readAt);

    $this->patchJson("/api/mobile/me/notifications/{$notification->id}/unread")
        ->assertOk()
        ->assertJsonPath('data.status', 'unread')
        ->assertJsonPath('data.isRead', false)
        ->assertJsonPath('data.readAt', null);

    $this->patchJson("/api/mobile/me/notifications/{$notification->id}/unread")
        ->assertOk()
        ->assertJsonPath('data.status', 'unread');
    expect($notification->refresh()->read_at)->toBeNull();
});
test('mark all read only updates authenticated users unread inbox notifications', function () {
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
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->patchJson('/api/mobile/me/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.updatedCount', 2)
        ->assertJsonPath('data.unreadCount', 0);

    expect($first->refresh()->status)->toBe('read');
    expect($first->read_at)->not->toBeNull();
    expect($second->refresh()->status)->toBe('read');
    expect($alreadyRead->refresh()->status)->toBe('read');
    expect($other->refresh()->status)->toBe('unread');
    expect($sent->refresh()->status)->toBe('unread');
});
test('notification filters are validated', function () {
    Sanctum::actingAs(User::factory()->create(), [TokenService::ACCESS_ABILITY]);

    $this->getJson('/api/mobile/me/notifications?perPage=0&status=sent&category=unknown&priority=urgent')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['perPage', 'status', 'category', 'priority'], 'error.details');
});
test('notification endpoints require authentication', function () {
    $this->getJson('/api/mobile/me/notifications')->assertUnauthorized();
    $this->getJson('/api/mobile/me/notifications/unread-count')->assertUnauthorized();
    $this->getJson('/api/mobile/me/notifications/notification-id')->assertUnauthorized();
    $this->patchJson('/api/mobile/me/notifications/notification-id/read')->assertUnauthorized();
    $this->patchJson('/api/mobile/me/notifications/notification-id/unread')->assertUnauthorized();
    $this->patchJson('/api/mobile/me/notifications/read-all')->assertUnauthorized();
});
