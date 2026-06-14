<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Campaign;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModerationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->organization = Organization::query()->create([
            'name' => 'Moderation Org',
            'email' => 'moderation@example.com',
        ]);

        $this->grantPermissions($this->user, [
            [PermissionGroup::POST_REVIEW, PermissionAction::VIEW],
            [PermissionGroup::POST_REVIEW, PermissionAction::APPROVE],
            [PermissionGroup::POST_REVIEW, PermissionAction::REJECT],
            [PermissionGroup::CAMPAIGN_REVIEW, PermissionAction::VIEW],
            [PermissionGroup::CAMPAIGN_REVIEW, PermissionAction::APPROVE],
            [PermissionGroup::CAMPAIGN_REVIEW, PermissionAction::REJECT],
            [PermissionGroup::REPORT, PermissionAction::VIEW],
            [PermissionGroup::REPORT, PermissionAction::CLAIM],
            [PermissionGroup::REPORT, PermissionAction::REQUEST_INFO],
            [PermissionGroup::REPORT, PermissionAction::CLOSE],
            [PermissionGroup::NOTIFICATION, PermissionAction::VIEW],
            [PermissionGroup::NOTIFICATION, PermissionAction::CREATE],
            [PermissionGroup::NOTIFICATION, PermissionAction::UPDATE],
            [PermissionGroup::NOTIFICATION, PermissionAction::DELETE],
            [PermissionGroup::NOTIFICATION, PermissionAction::RESEND],
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_reviews_posts(): void
    {
        $post = Post::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Review Post',
            'summary' => 'Summary',
            'type' => 'general',
            'status' => 'pending',
            'author_name' => 'Author',
            'location' => 'Amman',
        ]);

        $this->getJson('/api/v1/admin/review/posts?filter.status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/admin/review/posts/{$post->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $post->refresh();
        $this->assertEquals('approved', $post->status);

        $rejected = Post::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Reject Post',
            'summary' => 'Summary',
            'type' => 'general',
            'status' => 'pending',
            'author_name' => 'Author',
            'location' => 'Amman',
        ]);

        $this->postJson("/api/v1/admin/review/posts/{$rejected->id}/reject", [
            'reason' => 'Does not meet content requirements',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');
    }

    public function test_reviews_campaigns(): void
    {
        $campaign = Campaign::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Review Campaign',
            'summary' => 'Summary',
            'category' => 'health',
            'status' => 'pending',
            'location' => 'Amman',
            'goal_amount' => 1000,
            'beneficiaries_count' => 10,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ]);

        $this->getJson('/api/v1/admin/review/campaigns?filter.status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/admin/review/campaigns/{$campaign->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_manages_reports(): void
    {
        $reporter = User::factory()->create();
        $report = Report::query()->create([
            'title' => 'Report issue',
            'description' => 'Something happened',
            'category' => 'other',
            'status' => 'new',
            'severity' => 'high',
            'entity_type' => 'post',
            'entity_id' => 'post-1',
            'organization_id' => $this->organization->id,
            'reporter_id' => $reporter->id,
        ]);

        $this->getJson('/api/v1/admin/reports?filter.status=new')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/admin/reports/{$report->id}/claim")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->postJson("/api/v1/admin/reports/{$report->id}/request-info", [
            'note' => 'Need more context',
        ])->assertOk()->assertJsonPath('data.status', 'waiting_response');

        $this->postJson("/api/v1/admin/reports/{$report->id}/close", [
            'note' => 'Resolved',
        ])->assertOk()->assertJsonPath('data.status', 'closed');
    }

    public function test_manages_notifications(): void
    {
        $notification = Notification::query()->create([
            'title' => 'Existing notification',
            'body' => 'Body',
            'mailbox' => 'sent',
            'status' => 'sent',
            'category' => 'system',
            'recipient_scope' => 'all',
            'creator_id' => $this->user->id,
            'sent_at' => now(),
        ]);

        $this->getJson('/api/v1/admin/notifications?filter.status=sent')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $created = $this->postJson('/api/v1/admin/notifications', [
            'title' => 'New notification',
            'body' => 'Body text',
            'category' => 'system',
            'recipientScope' => 'all',
            'recipientLabel' => 'Everyone',
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/admin/notifications/{$created}/read-state", [
            'status' => 'read',
        ])->assertOk()->assertJsonPath('data.status', 'read');

        $this->postJson("/api/v1/admin/notifications/{$notification->id}/resend")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent');

        $this->deleteJson("/api/v1/admin/notifications/{$created}")
            ->assertNoContent();
    }
}
