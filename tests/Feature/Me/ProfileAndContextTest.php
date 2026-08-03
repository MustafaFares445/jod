<?php

declare(strict_types=1);

namespace Tests\Feature\Me;

use App\Models\Campaign;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAndContextTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private OrganizationRole $staffRole;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::query()->create([
            'name' => 'Relief Org',
            'email' => 'relief@example.com',
        ]);

        $this->staffRole = OrganizationRole::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Editor',
            'is_active' => true,
            'is_system' => false,
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        OrganizationStaff::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'organization_role_id' => $this->staffRole->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_returns_profile_bootstrap_data(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response->assertOk();
        $response->assertJsonPath('data.id', $this->user->id);
        $response->assertJsonPath('data.organizationId', $this->user->organization_id);
    }

    public function test_updates_profile(): void
    {
        $response = $this->patchJson('/api/v1/me/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '+962790000000',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated Name');
        $response->assertJsonPath('data.email', 'updated@example.com');
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => 'updated@example.com',
        ]);
    }

    public function test_returns_staff_dashboard_role_and_organization_context(): void
    {
        $response = $this->getJson('/api/v1/me/dashboard-context');

        $response->assertOk()
            ->assertJsonPath('data.profile.dashboardRole', 'org_staff')
            ->assertJsonPath('data.organization.id', $this->organization->id)
            ->assertJsonPath('data.organization.name', $this->organization->name)
            ->assertJsonPath('data.staffRole.id', $this->staffRole->id)
            ->assertJsonPath('data.staffRole.name', $this->staffRole->name)
            ->assertJsonPath('data.staffRole.isSystem', false);
    }

    public function test_returns_owner_dashboard_role_for_active_system_role(): void
    {
        $ownerRole = OrganizationRole::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Owner',
            'is_active' => true,
            'is_system' => true,
        ]);

        OrganizationStaff::query()
            ->where('user_id', $this->user->id)
            ->update(['organization_role_id' => $ownerRole->id]);

        $this->getJson('/api/v1/me/dashboard-context')
            ->assertOk()
            ->assertJsonPath('data.profile.dashboardRole', 'org_owner')
            ->assertJsonPath('data.staffRole.id', $ownerRole->id)
            ->assertJsonPath('data.staffRole.isSystem', true);
    }

    public function test_returns_dashboard_context_counters_scoped_to_the_user_organization(): void
    {
        $otherOrganization = Organization::factory()->create();

        Notification::query()->create([
            'title' => 'Unread for user',
            'body' => 'Body',
            'mailbox' => 'inbox',
            'status' => 'unread',
            'category' => 'system',
            'recipient_scope' => 'users',
            'recipient_id' => $this->user->id,
        ]);

        Notification::query()->create([
            'title' => 'Unread for org',
            'body' => 'Body',
            'mailbox' => 'inbox',
            'status' => 'unread',
            'category' => 'system',
            'recipient_scope' => 'organizations',
            'organization_id' => $this->organization->id,
        ]);

        Notification::query()->create([
            'title' => 'Unread for other org',
            'body' => 'Body',
            'mailbox' => 'inbox',
            'status' => 'unread',
            'category' => 'system',
            'recipient_scope' => 'organizations',
            'organization_id' => $otherOrganization->id,
        ]);

        Post::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Pending post',
            'status' => 'pending',
            'type' => 'general',
        ]);

        Post::query()->create([
            'organization_id' => $otherOrganization->id,
            'title' => 'Other pending post',
            'status' => 'pending',
            'type' => 'general',
        ]);

        Campaign::query()->create([
            'title' => 'Pending campaign',
            'status' => 'pending',
            'organization_id' => $this->organization->id,
        ]);

        Campaign::query()->create([
            'title' => 'Other pending campaign',
            'status' => 'pending',
            'organization_id' => $otherOrganization->id,
        ]);

        Report::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Open report',
            'description' => 'Issue',
            'status' => 'new',
            'severity' => 'medium',
            'entity_type' => 'post',
        ]);

        Report::query()->create([
            'organization_id' => $otherOrganization->id,
            'title' => 'Other open report',
            'description' => 'Issue',
            'status' => 'new',
            'severity' => 'medium',
            'entity_type' => 'post',
        ]);

        $response = $this->getJson('/api/v1/me/dashboard-context');

        $response->assertOk();
        $this->assertSame(2, $response->json('data.counters.unreadNotifications'));
        $this->assertSame(2, $response->json('data.counters.pendingReviews'));
        $this->assertSame(1, $response->json('data.counters.openReports'));
    }
}
