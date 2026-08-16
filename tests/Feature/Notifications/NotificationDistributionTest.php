<?php

declare(strict_types=1);
use App\Jobs\DeliverMobilePush;
use App\Jobs\FanOutNotification;
use App\Models\MobileDevice;
use App\Models\MobilePushDelivery;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationDistributionService;
use App\Services\OrgNotificationService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admin all scope fans out to active non admin recipients once', function () {
    Queue::fake();

    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $sender = User::factory()->create(['user_type' => 'admin']);
    $general = User::factory()->create();
    $volunteer = User::factory()->create(['user_type' => 'volunteer']);
    $organizationMemberA = User::factory()->create(['organization_id' => $organizationA->id]);
    $organizationMemberB = User::factory()->create(['organization_id' => $organizationB->id]);
    $inactive = User::factory()->create(['status' => 'inactive']);
    $otherAdmin = User::factory()->create(['user_type' => 'admin']);

    $source = sentSource($sender, 'all');
    $service = app(NotificationDistributionService::class);
    $source = $service->dispatch($source);

    Queue::assertPushed(FanOutNotification::class, function (FanOutNotification $job) use ($source): bool {
        return $job->notificationId === $source->id
            && $job->distributionBatchId === $source->distribution_batch_id;
    });

    expect($service->fanOut($source->id, $source->distribution_batch_id))->toBe(4);
    expect($service->fanOut($source->id, $source->distribution_batch_id))->toBe(0);

    $recipientIds = Notification::query()
        ->where('source_notification_id', $source->id)
        ->pluck('recipient_id')
        ->sort()
        ->values()
        ->all();
    $expectedIds = collect([
        $general->id,
        $volunteer->id,
        $organizationMemberA->id,
        $organizationMemberB->id,
    ])->sort()->values()->all();

    expect($recipientIds)->toBe($expectedIds);
    expect($recipientIds)->not->toContain($sender->id);
    expect($recipientIds)->not->toContain($inactive->id);
    expect($recipientIds)->not->toContain($otherAdmin->id);
});
test('admin user and organization scopes select the expected accounts', function () {
    Queue::fake();

    $organization = Organization::factory()->create();
    $sender = User::factory()->create(['user_type' => 'admin']);
    $general = User::factory()->create();
    $organizationMember = User::factory()->create(['organization_id' => $organization->id]);
    $service = app(NotificationDistributionService::class);

    $usersSource = $service->dispatch(sentSource($sender, 'users'));
    expect($service->fanOut($usersSource->id, $usersSource->distribution_batch_id))->toBe(1);
    $this->assertDatabaseHas('notifications', [
        'source_notification_id' => $usersSource->id,
        'recipient_id' => $general->id,
        'mailbox' => 'inbox',
    ]);
    $this->assertDatabaseMissing('notifications', [
        'source_notification_id' => $usersSource->id,
        'recipient_id' => $organizationMember->id,
    ]);

    $organizationsSource = $service->dispatch(sentSource($sender, 'organizations'));
    expect($service->fanOut($organizationsSource->id, $organizationsSource->distribution_batch_id))->toBe(1);
    $this->assertDatabaseHas('notifications', [
        'source_notification_id' => $organizationsSource->id,
        'recipient_id' => $organizationMember->id,
        'mailbox' => 'inbox',
    ]);
    $this->assertDatabaseMissing('notifications', [
        'source_notification_id' => $organizationsSource->id,
        'recipient_id' => $general->id,
    ]);
});
test('organization distribution never crosses tenant boundaries', function () {
    Queue::fake();

    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $sender = User::factory()->create(['organization_id' => $organizationA->id]);
    $sameOrganization = User::factory()->create(['organization_id' => $organizationA->id]);
    $otherOrganization = User::factory()->create(['organization_id' => $organizationB->id]);
    $unaffiliated = User::factory()->create();
    $inactiveSameOrganization = User::factory()->create([
        'organization_id' => $organizationA->id,
        'status' => 'inactive',
    ]);

    $source = Notification::factory()->create([
        'recipient_id' => null,
        'organization_id' => $organizationA->id,
        'creator_id' => $sender->id,
        'mailbox' => 'sent',
        'status' => 'sent',
        'recipient_scope' => 'all',
        'sent_at' => now(),
    ]);
    $service = app(NotificationDistributionService::class);
    $source = $service->dispatch($source);

    expect($service->fanOut($source->id, $source->distribution_batch_id))->toBe(1);
    $this->assertDatabaseHas('notifications', [
        'source_notification_id' => $source->id,
        'recipient_id' => $sameOrganization->id,
    ]);
    foreach ([$sender, $otherOrganization, $unaffiliated, $inactiveSameOrganization] as $excluded) {
        $this->assertDatabaseMissing('notifications', [
            'source_notification_id' => $source->id,
            'recipient_id' => $excluded->id,
        ]);
    }
});
test('resend creates a fresh batch without mutating existing read history', function () {
    Queue::fake();

    $sender = User::factory()->create(['user_type' => 'admin']);
    $recipient = User::factory()->create();
    $service = app(NotificationDistributionService::class);
    $source = $service->dispatch(sentSource($sender, 'users'));
    $firstBatch = $source->distribution_batch_id;

    $service->fanOut($source->id, $firstBatch);
    $firstInbox = Notification::query()
        ->where('source_notification_id', $source->id)
        ->where('recipient_id', $recipient->id)
        ->firstOrFail();
    $firstInbox->update(['status' => 'read', 'read_at' => now()]);

    $source = $service->resend($source);
    $secondBatch = $source->distribution_batch_id;
    $this->assertNotSame($firstBatch, $secondBatch);
    expect($service->fanOut($source->id, $secondBatch))->toBe(1);

    $copies = Notification::query()
        ->where('source_notification_id', $source->id)
        ->where('recipient_id', $recipient->id)
        ->get()
        ->keyBy('distribution_batch_id');

    expect($copies)->toHaveCount(2);
    expect($copies->get($firstBatch)?->status)->toBe('read');
    expect($copies->get($firstBatch)?->read_at)->not->toBeNull();
    expect($copies->get($secondBatch)?->status)->toBe('unread');
    expect($copies->get($secondBatch)?->read_at)->toBeNull();
});
test('organization dashboard service hides recipient copies and keeps creator', function () {
    Queue::fake();

    $organization = Organization::factory()->create();
    $sender = User::factory()->create(['organization_id' => $organization->id]);
    User::factory()->create(['organization_id' => $organization->id]);
    $service = app(OrgNotificationService::class);

    $source = $service->create([
        'title' => 'Organization update',
        'body' => 'A message for organization members.',
        'category' => 'system',
        'recipientScope' => 'all',
    ], $organization->id, $sender->id);

    app(NotificationDistributionService::class)->fanOut(
        $source->id,
        $source->distribution_batch_id,
    );

    expect($source->creator_id)->toBe($sender->id);
    $page = $service->paginate([], $organization->id);
    expect($page->total())->toBe(1);
    expect($page->items()[0]->id)->toBe($source->id);
});
test('fanout hands recipient inbox rows to mobile push delivery', function () {
    Queue::fake();
    config([
        'mobile_push.enabled' => true,
        'mobile_push.provider' => 'fcm',
    ]);

    $sender = User::factory()->create(['user_type' => 'admin']);
    $recipient = User::factory()->create();
    $device = MobileDevice::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $recipient->id,
        'push_token' => 'phase-12-device-token',
        'push_target_type' => 'token',
        'platform' => 'android',
        'device_id' => (string) Str::uuid(),
        'app_version' => '1.0.0',
        'last_seen_at' => now(),
    ]);
    $service = app(NotificationDistributionService::class);
    $source = $service->dispatch(sentSource($sender, 'users'));

    $service->fanOut($source->id, $source->distribution_batch_id);

    $inbox = Notification::query()
        ->where('source_notification_id', $source->id)
        ->where('recipient_id', $recipient->id)
        ->firstOrFail();
    $delivery = MobilePushDelivery::query()
        ->where('notification_id', $inbox->id)
        ->where('mobile_device_id', $device->id)
        ->first();

    expect($delivery)->not->toBeNull();
    expect($delivery->status)->toBe('pending');
    Queue::assertPushed(DeliverMobilePush::class);
});
function sentSource(User $sender, string $scope): Notification
{
    return Notification::factory()->create([
        'recipient_id' => null,
        'organization_id' => null,
        'creator_id' => $sender->id,
        'mailbox' => 'sent',
        'status' => 'sent',
        'recipient_scope' => $scope,
        'sent_at' => now(),
    ]);
}
