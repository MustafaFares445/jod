<?php

declare(strict_types=1);

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Campaign;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['user_type' => 'admin']);
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
});

test('manages feed posts through the unified CRUD endpoint', function () {
    $owner = User::factory()->create(['name' => 'Post Owner']);
    $post = Post::query()->create([
        'title' => 'Review Post',
        'summary' => 'Summary',
        'content' => 'Post body',
        'type' => 'general',
        'status' => 'pending',
        'author_id' => $owner->id,
        'updated_by' => $owner->id,
        'submitted_at' => now()->subMinute(),
        'location' => 'Amman',
    ]);

    $this->getJson('/api/posts?filter%5Bstatus%5D=pending&filter%5Blocation%5D=Amman')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.author.name', 'Post Owner')
        ->assertJsonPath('data.0.updatedBy.name', 'Post Owner');

    $this->patchJson("/api/posts/{$post->id}", [
        'status' => 'approved',
    ])->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.approvedBy.id', $this->user->id)
        ->assertJsonStructure(['data' => ['approvedAt', 'publishedAt']]);

    $post->refresh();
    expect($post->status)->toEqual('approved');
    expect($post->approved_by)->toEqual($this->user->id);

    $this->getJson('/api/mobile/discovery/posts')
        ->assertOk()
        ->assertJsonPath('data.0.id', $post->id);

    $this->patchJson("/api/posts/{$post->id}", [
        'status' => 'archived',
    ])->assertOk()->assertJsonPath('data.status', 'archived');

    $this->getJson('/api/mobile/discovery/posts')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->postJson("/api/v1/admin/review/posts/{$post->id}/approve")
        ->assertNotFound();
});

test('reviews campaigns', function () {
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
});

test('manages reports with three states and reported entity details', function () {
    $reporter = User::factory()->create();
    $reportedPost = Post::query()->create([
        'title' => 'Reported post',
        'content' => 'Reported content',
        'status' => 'published',
        'author_id' => $reporter->id,
    ]);
    $report = Report::query()->create([
        'title' => 'Report issue',
        'description' => 'Something happened',
        'category' => 'other',
        'status' => 'new',
        'severity' => 'high',
        'entity_type' => 'post',
        'entity_id' => $reportedPost->id,
        'organization_id' => $this->organization->id,
        'reporter_id' => $reporter->id,
    ]);

    $this->getJson('/api/v1/admin/reports?filter%5Bstatus%5D=new')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.entity.type', 'post')
        ->assertJsonPath('data.0.entity.data.id', $reportedPost->id)
        ->assertJsonPath('data.0.entity.data.title', 'Reported post');

    $this->postJson("/api/v1/admin/reports/{$report->id}/claim")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->postJson("/api/v1/admin/reports/{$report->id}/request-info", [
        'note' => 'Need more context',
    ])->assertOk()->assertJsonPath('data.status', 'in_progress');

    $this->postJson("/api/v1/admin/reports/{$report->id}/close", [
        'note' => 'Resolved',
    ])->assertOk()->assertJsonPath('data.status', 'closed');
});

test('manages notifications', function () {
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
        ->assertOk()
        ->assertJsonPath('message', 'Data deleted successfully.');
});
