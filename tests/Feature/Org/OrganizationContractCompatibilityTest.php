<?php

declare(strict_types=1);

namespace Tests\Feature\Org;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Campaign;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationContractCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_paginated_responses_include_contract_envelope_without_removing_legacy_data(): void
    {
        $campaign = $this->campaign(['status' => 'active']);

        $this->getJson('/api/v1/org/campaigns')
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('item.total', 1)
            ->assertJsonPath('item.page', 1)
            ->assertJsonPath('item.perPage', 10)
            ->assertJsonPath('item.data.0.id', $campaign->id)
            ->assertJsonPath('data.0.id', $campaign->id);
    }

    public function test_campaign_update_accepts_status_and_enforces_lifecycle(): void
    {
        $campaign = $this->campaign(['status' => 'draft']);

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
    }

    public function test_campaign_update_still_works_without_status(): void
    {
        $campaign = $this->campaign(['status' => 'draft']);

        $this->patchJson("/api/v1/org/campaigns/{$campaign->id}", [
            'title' => 'Updated Campaign',
        ])
            ->assertOk()
            ->assertJsonPath('item.title', 'Updated Campaign')
            ->assertJsonPath('item.status', 'draft');
    }

    public function test_campaign_update_rejects_invalid_status(): void
    {
        $campaign = $this->campaign(['status' => 'draft']);

        $this->patchJson("/api/v1/org/campaigns/{$campaign->id}", [
            'status' => 'pending',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_old_campaign_status_endpoint_is_not_registered(): void
    {
        $route = collect(app('router')->getRoutes())
            ->first(fn ($route): bool => in_array('PATCH', $route->methods(), true)
                && $route->uri() === 'api/v1/org/campaigns/{campaign}/status');

        $this->assertNull($route);
    }

    public function test_post_status_contract_endpoint_uses_existing_transition_rules(): void
    {
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
    }

    public function test_notification_read_contract_endpoint_requires_no_request_body(): void
    {
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

        $this->assertNotNull($notification->refresh()->read_at);
    }

    private function campaign(array $overrides = []): Campaign
    {
        return Campaign::query()->create([
            'organization_id' => $this->organization->id,
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
}
