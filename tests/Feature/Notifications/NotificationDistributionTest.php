<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Jobs\DeliverMobilePush;
use App\Jobs\FanOutNotification;
use App\Models\MobileDevice;
use App\Models\MobilePushDelivery;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use App\Services\NotificationDistributionService;
use App\Services\OrgNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_all_scope_fans_out_to_active_non_admin_recipients_once(): void
    {
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

        $source = $this->sentSource($sender, 'all');
        $service = app(NotificationDistributionService::class);
        $source = $service->dispatch($source);

        Queue::assertPushed(FanOutNotification::class, function (FanOutNotification $job) use ($source): bool {
            return $job->notificationId === $source->id
                && $job->distributionBatchId === $source->distribution_batch_id;
        });

        $this->assertSame(4, $service->fanOut($source->id, $source->distribution_batch_id));
        $this->assertSame(0, $service->fanOut($source->id, $source->distribution_batch_id));

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

        $this->assertSame($expectedIds, $recipientIds);
        $this->assertNotContains($sender->id, $recipientIds);
        $this->assertNotContains($inactive->id, $recipientIds);
        $this->assertNotContains($otherAdmin->id, $recipientIds);
    }

    public function test_admin_user_and_organization_scopes_select_the_expected_accounts(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();
        $sender = User::factory()->create(['user_type' => 'admin']);
        $general = User::factory()->create();
        $organizationMember = User::factory()->create(['organization_id' => $organization->id]);
        $service = app(NotificationDistributionService::class);

        $usersSource = $service->dispatch($this->sentSource($sender, 'users'));
        $this->assertSame(1, $service->fanOut($usersSource->id, $usersSource->distribution_batch_id));
        $this->assertDatabaseHas('notifications', [
            'source_notification_id' => $usersSource->id,
            'recipient_id' => $general->id,
            'mailbox' => 'inbox',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'source_notification_id' => $usersSource->id,
            'recipient_id' => $organizationMember->id,
        ]);

        $organizationsSource = $service->dispatch($this->sentSource($sender, 'organizations'));
        $this->assertSame(1, $service->fanOut($organizationsSource->id, $organizationsSource->distribution_batch_id));
        $this->assertDatabaseHas('notifications', [
            'source_notification_id' => $organizationsSource->id,
            'recipient_id' => $organizationMember->id,
            'mailbox' => 'inbox',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'source_notification_id' => $organizationsSource->id,
            'recipient_id' => $general->id,
        ]);
    }

    public function test_organization_distribution_never_crosses_tenant_boundaries(): void
    {
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

        $this->assertSame(1, $service->fanOut($source->id, $source->distribution_batch_id));
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
    }

    public function test_resend_creates_a_fresh_batch_without_mutating_existing_read_history(): void
    {
        Queue::fake();

        $sender = User::factory()->create(['user_type' => 'admin']);
        $recipient = User::factory()->create();
        $service = app(NotificationDistributionService::class);
        $source = $service->dispatch($this->sentSource($sender, 'users'));
        $firstBatch = $source->distribution_batch_id;

        $service->fanOut($source->id, $firstBatch);
        $firstInbox = Notification::query()
            ->where('source_notification_id', $source->id)
            ->where('recipient_id', $recipient->id)
            ->firstOrFail();
        $firstInbox->update(['status' => 'read', 'read_at' => now()]);

        $source = $service->resend($source);
        $this->assertNotSame($firstBatch, $source->distribution_batch_id);
        $this->assertSame(1, $service->fanOut($source->id, $source->distribution_batch_id));

        $copies = Notification::query()
            ->where('source_notification_id', $source->id)
            ->where('recipient_id', $recipient->id)
            ->orderBy('created_at')
            ->get();

        $this->assertCount(2, $copies);
        $this->assertSame('read', $copies->first()->status);
        $this->assertNotNull($copies->first()->read_at);
        $this->assertSame('unread', $copies->last()->status);
        $this->assertNull($copies->last()->read_at);
    }

    public function test_organization_dashboard_service_hides_recipient_copies_and_keeps_creator(): void
    {
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

        $this->assertSame($sender->id, $source->creator_id);
        $page = $service->paginate([], $organization->id);
        $this->assertSame(1, $page->total());
        $this->assertSame($source->id, $page->items()[0]->id);
    }

    public function test_fanout_hands_recipient_inbox_rows_to_mobile_push_delivery(): void
    {
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
        $source = $service->dispatch($this->sentSource($sender, 'users'));

        $service->fanOut($source->id, $source->distribution_batch_id);

        $inbox = Notification::query()
            ->where('source_notification_id', $source->id)
            ->where('recipient_id', $recipient->id)
            ->firstOrFail();
        $delivery = MobilePushDelivery::query()
            ->where('notification_id', $inbox->id)
            ->where('mobile_device_id', $device->id)
            ->first();

        $this->assertNotNull($delivery);
        $this->assertSame('pending', $delivery->status);
        Queue::assertPushed(DeliverMobilePush::class);
    }

    private function sentSource(User $sender, string $scope): Notification
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
}
