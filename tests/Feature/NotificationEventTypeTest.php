<?php

declare(strict_types=1);

use App\Enums\NotificationEventType;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationDistributionService;
use App\Services\NotificationEventService;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('event dispatcher creates a personal unread inbox notification', function () {
    $user = User::factory()->create();

    $notification = app(NotificationEventService::class)->notifyUser(
        $user,
        NotificationEventType::DonationCompleted,
        'Donation completed',
        'Your donation was recorded.',
        'donation',
        'normal',
        'Campaign',
        '/campaigns/campaign-1',
    );

    expect($notification)->not->toBeNull();
    expect($notification?->event_type)->toBe('donation.completed');
    expect($notification?->mailbox)->toBe('inbox');
    expect($notification?->status)->toBe('unread');
    expect((string) $notification?->recipient_id)->toBe((string) $user->id);
});

test('organization event routing only notifies active users in that organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $first = User::factory()->create(['organization_id' => $organization->id]);
    $second = User::factory()->create(['organization_id' => $organization->id]);
    $inactive = User::factory()->create([
        'organization_id' => $organization->id,
        'status' => 'inactive',
    ]);
    $other = User::factory()->create(['organization_id' => $otherOrganization->id]);

    $created = app(NotificationEventService::class)->notifyOrganization(
        (string) $organization->id,
        NotificationEventType::DonationReceived,
        'Donation received',
        'A new donation was received.',
        'donation',
        'high',
    );

    expect($created)->toBe(2);
    $this->assertDatabaseHas('notifications', [
        'recipient_id' => $first->id,
        'event_type' => 'donation.received',
    ]);
    $this->assertDatabaseHas('notifications', [
        'recipient_id' => $second->id,
        'event_type' => 'donation.received',
    ]);
    $this->assertDatabaseMissing('notifications', [
        'recipient_id' => $inactive->id,
        'event_type' => 'donation.received',
    ]);
    $this->assertDatabaseMissing('notifications', [
        'recipient_id' => $other->id,
        'event_type' => 'donation.received',
    ]);
});

test('fan out preserves event type on recipient copies', function () {
    $recipient = User::factory()->create();
    $batchId = (string) Str::uuid();
    $source = Notification::factory()->create([
        'recipient_id' => null,
        'mailbox' => 'sent',
        'status' => 'sent',
        'category' => 'system',
        'event_type' => NotificationEventType::SystemMaintenance->value,
        'recipient_scope' => 'users',
        'distribution_batch_id' => $batchId,
    ]);

    $created = app(NotificationDistributionService::class)->fanOut(
        (string) $source->id,
        $batchId,
    );

    expect($created)->toBeGreaterThanOrEqual(1);
    $this->assertDatabaseHas('notifications', [
        'source_notification_id' => $source->id,
        'recipient_id' => $recipient->id,
        'mailbox' => 'inbox',
        'event_type' => 'system.maintenance',
    ]);
});
