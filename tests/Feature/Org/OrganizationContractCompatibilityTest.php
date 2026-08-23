<?php

declare(strict_types=1);

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Campaign;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->organization = Organization::query()->create([
        'name' => 'Contract Org',
        'email' => 'contract@example.com',
        'status' => 'active',
        'verification_status' => 'verified',
    ]);

    $this->user = User::factory()->create([
        'organization_id' => $this->organization->id,
    ]);

    $this->grantPermissions($this->user, [
        [PermissionGroup::ORG_CAMPAIGN, PermissionAction::VIEW],
        [PermissionGroup::ORG_CAMPAIGN, PermissionAction::UPDATE],
        [PermissionGroup::ORG_CAMPAIGN, PermissionAction::CLOSE],
        [PermissionGroup::ORG_POST, PermissionAction::VIEW],
        [PermissionGroup::ORG_POST, PermissionAction::PUBLISH],
        [PermissionGroup::ORG_POST, PermissionAction::ARCHIVE],
        [PermissionGroup::ORG_POST, PermissionAction::RESTORE],
        [PermissionGroup::ORG_NOTIFICATION, PermissionAction::VIEW],
        [PermissionGroup::ORG_NOTIFICATION, PermissionAction::UPDATE],
    ]);

    Sanctum::actingAs($this->user);
});

test('paginated responses include contract envelope without removing legacy data', function () {
    $campaign = contract_campaign($this->organization->id, ['status' => 'active']);

    $this->getJson('/api/v1/org/campaigns')
        ->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('item.total', 1)
        ->assertJsonPath('item.page', 1)
        ->assertJsonPath('item.perPage', 10)
        ->assertJsonPath('item.data.0.id', $campaign->id)
        ->assertJsonPath('data.0.id', $campaign->id);
});

test('campaign status contract endpoint enforces lifecycle', function () {
    $campaign = contract_campaign($this->organization->id, ['status' => 'draft']);

    $this->patchJson("/api/v1/org/campaigns/{$campaign->id}", [
        'status' => 'active',
    ])
        ->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('item.status', 'active')
        ->assertJsonPath('data.status', 'active');

    $this->patchJson("/api/v1/org/campaigns/{$campaign->id}", [
        'status' => 'closed',
        'closedReason' => 'Campaign objectives were completed.',
    ])
        ->assertOk()
        ->assertJsonPath('item.status', 'closed')
        ->assertJsonPath('item.closedReason', 'Campaign objectives were completed.');
});

test('campaign update still works without status', function () {
    $campaign = contract_campaign($this->organization->id, ['status' => 'draft']);

    $this->patchJson("/api/v1/org/campaigns/{$campaign->id}", [
        'title' => 'Updated Campaign',
    ])
        ->assertOk()
        ->assertJsonPath('item.title', 'Updated Campaign')
        ->assertJsonPath('item.status', 'draft');
});

test('campaign update rejects invalid status', function () {
    $campaign = contract_campaign($this->organization->id, ['status' => 'draft']);

    $this->patchJson("/api/v1/org/campaigns/{$campaign->id}", [
        'status' => 'pending',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('old campaign status endpoint is not registered', function () {
    $route = collect(app('router')->getRoutes())
        ->first(fn($route): bool => in_array('PATCH', $route->methods(), true)
            && $route->uri() === 'api/v1/org/campaigns/{campaign}/status');

    $this->assertNull($route);
});

test('post status contract endpoint uses existing transition rules', function () {
    $post = Post::query()->create([
        'organization_id' => $this->organization->id,
        'title' => 'Contract Post',
        'summary' => 'Summary',
        'type' => 'general',
        'status' => 'draft',
        'author_name' => 'Author',
        'location' => 'Riyadh',
    ]);

    $this->patchJson("/api/v1/org/posts/{$post->id}/status", ['status' => 'published'])
        ->assertOk()
        ->assertJsonPath('item.status', 'published');

    $this->patchJson("/api/v1/org/posts/{$post->id}/status", ['status' => 'archived'])
        ->assertOk()
        ->assertJsonPath('item.status', 'archived');

    $this->patchJson("/api/v1/org/posts/{$post->id}/status", ['status' => 'draft'])
        ->assertOk()
        ->assertJsonPath('item.status', 'draft');
});

test('notification read contract endpoint requires no request body', function () {
    $notification = Notification::query()->create([
        'organization_id' => $this->organization->id,
        'title' => 'Campaign update',
        'body' => 'A campaign was updated.',
        'mailbox' => 'inbox',
        'status' => 'unread',
        'category' => 'campaign',
        'recipient_scope' => 'organizations',
        'priority' => 'normal',
    ]);

    $this->patchJson("/api/v1/org/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('item.status', 'read');

    expect($notification->refresh()->read_at)->not->toBeNull();
});

function contract_campaign(string $organizationId, array $overrides = []): Campaign
{
    return Campaign::query()->create([
        'organization_id' => $organizationId,
        'title' => 'Contract Campaign',
        'summary' => 'Summary',
        'category' => 'health',
        'status' => 'draft',
        'location' => 'Riyadh',
        'goal_amount' => 1000,
        'beneficiaries_count' => 2,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addWeek()->toDateString(),
        ...$overrides,
    ]);
}
