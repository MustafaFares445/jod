<?php

declare(strict_types=1);

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
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
        [PermissionGroup::REPORT, PermissionAction::VIEW],
        [PermissionGroup::REPORT, PermissionAction::CLAIM],
        [PermissionGroup::REPORT, PermissionAction::CLOSE],
        [PermissionGroup::NOTIFICATION, PermissionAction::VIEW],
        [PermissionGroup::NOTIFICATION, PermissionAction::CREATE],
        [PermissionGroup::NOTIFICATION, PermissionAction::UPDATE],
        [PermissionGroup::NOTIFICATION, PermissionAction::DELETE],
        [PermissionGroup::NOTIFICATION, PermissionAction::RESEND],
    ]);

    Sanctum::actingAs($this->user);
});

test('admin review queue contains only normal user posts', function () {
    $owner = User::factory()->create(['name' => 'Post Owner']);
    $organizationOwner = User::factory()->create([
        'name' => 'Organization Owner',
        'organization_id' => $this->organization->id,
    ]);

    $userPost = Post::query()->create([
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

    $organizationPost = Post::query()->create([
        'organization_id' => $this->organization->id,
        'author_id' => $organizationOwner->id,
        'title' => 'Organization post',
        'content' => 'Organization content',
        'status' => 'pending',
        'type' => 'general',
    ]);

    $adminPost = Post::query()->create([
        'author_id' => $this->user->id,
        'title' => 'Admin post',
        'content' => 'Admin content',
        'status' => 'pending',
        'type' => 'general',
    ]);

    $this->getJson('/api/posts?filter%5Bstatus%5D=pending&filter%5Blocation%5D=Amman')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $userPost->id)
        ->assertJsonPath('data.0.author.name', 'Post Owner');

    $this->patchJson("/api/posts/{$organizationPost->id}", [
        'status' => 'rejected',
        'rejectionReason' => 'Not reviewable.',
    ])->assertForbidden();

    $this->patchJson("/api/posts/{$adminPost->id}", [
        'status' => 'rejected',
        'rejectionReason' => 'Not reviewable.',
    ])->assertForbidden();
});

test('rejected user post can be edited resubmitted and reviewed again', function () {
    $owner = User::factory()->create(['name' => 'Post Owner']);
    $post = Post::query()->create([
        'title' => 'Review Post',
        'summary' => 'Summary',
        'content' => 'Post body',
        'type' => 'help_request',
        'status' => 'pending',
        'author_id' => $owner->id,
        'updated_by' => $owner->id,
        'submitted_at' => now()->subMinute(),
        'location' => 'Amman',
    ]);

    $this->patchJson("/api/posts/{$post->id}", [
        'status' => 'rejected',
        'rejectionReason' => 'Please clarify the details.',
    ])->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejectionReason', 'Please clarify the details.');

    Sanctum::actingAs($owner);

    $this->patchJson("/api/mobile/posts/{$post->id}", [
        'details' => 'Updated details with the requested clarification.',
    ])->assertOk();

    $this->postJson("/api/mobile/posts/{$post->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.rejectionReason', null);

    Sanctum::actingAs($this->user);

    $this->patchJson("/api/posts/{$post->id}", [
        'status' => 'approved',
    ])->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.approvedBy.id', $this->user->id)
        ->assertJsonStructure(['data' => ['approvedAt', 'publishedAt']]);
});

test('campaign admin review routes are not registered', function () {
    $this->getJson('/api/v1/admin/review/campaigns')->assertNotFound();
    $this->postJson('/api/v1/admin/review/campaigns/example/approve')->assertNotFound();
    $this->postJson('/api/v1/admin/review/campaigns/example/reject')->assertNotFound();
});

test('manages reports with reporter target full entity and three-state lifecycle', function () {
    $reporter = User::factory()->create(['name' => 'Reporter User']);
    $reportedUser = User::factory()->create(['name' => 'Reported User']);
    $reportedPost = Post::query()->create([
        'title' => 'Reported post',
        'content' => 'Reported content',
        'status' => 'published',
        'author_id' => $reportedUser->id,
    ]);
    $report = Report::query()->create([
        'title' => 'Report issue',
        'description' => 'Something happened',
        'category' => 'other',
        'status' => 'new',
        'severity' => 'high',
        'entity_type' => 'post',
        'entity_id' => $reportedPost->id,
        'reporter_id' => $reporter->id,
    ]);

    $this->getJson('/api/v1/admin/reports?filter%5Bstatus%5D=new')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reporter.id', $reporter->id)
        ->assertJsonPath('data.0.reporter.name', 'Reporter User')
        ->assertJsonPath('data.0.reportedTarget.id', $reportedUser->id)
        ->assertJsonPath('data.0.reportedTarget.name', 'Reported User')
        ->assertJsonPath('data.0.reportedTarget.type', 'user')
        ->assertJsonPath('data.0.entity.type', 'post')
        ->assertJsonPath('data.0.entity.data.id', $reportedPost->id)
        ->assertJsonPath('data.0.entity.data.title', 'Reported post')
        ->assertJsonPath('data.0.entity.data.content', 'Reported content');

    $this->postJson("/api/v1/admin/reports/{$report->id}/claim")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->postJson("/api/v1/admin/reports/{$report->id}/request-info", [
        'note' => 'Need more context',
    ])->assertNotFound();

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
