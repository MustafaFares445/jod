<?php

declare(strict_types=1);

namespace Tests\Feature\Org;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_receives_real_organization_scoped_overview(): void
    {
        [$organization, $owner] = $this->organizationUser(true);
        $otherOrganization = Organization::factory()->create();

        Campaign::factory()->create(['organization_id' => $organization->id, 'status' => 'active']);
        Campaign::factory()->create(['organization_id' => $otherOrganization->id, 'status' => 'active']);
        Post::factory()->create(['organization_id' => $organization->id, 'status' => 'published']);
        Post::factory()->create(['organization_id' => $otherOrganization->id, 'status' => 'published']);

        AuditLog::factory()->create([
            'actor_user_id' => $owner->id,
            'action' => 'campaign.created',
            'entity_type' => 'Campaign',
            'metadata' => ['title' => 'Local Campaign'],
        ]);

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/org/dashboard/overview');

        $response->assertOk()
            ->assertJsonPath('item.stats.0.id', 'campaigns')
            ->assertJsonPath('item.stats.0.value', 1)
            ->assertJsonFragment(['id' => 'posts', 'value' => 1])
            ->assertJsonFragment(['id' => 'staff', 'value' => 1])
            ->assertJsonPath('item.activity.0.entityType', 'Campaign');
    }

    public function test_staff_receives_only_permitted_stats_and_activity(): void
    {
        [$organization, $staff] = $this->organizationUser(false);
        $this->grantPermissions($staff, [
            [PermissionGroup::DASHBOARD, PermissionAction::VIEW],
            [PermissionGroup::ORG_POST, PermissionAction::VIEW],
        ]);

        Campaign::factory()->create(['organization_id' => $organization->id]);
        Post::factory()->create(['organization_id' => $organization->id]);

        AuditLog::factory()->create([
            'actor_user_id' => $staff->id,
            'action' => 'campaign.created',
            'entity_type' => 'Campaign',
        ]);
        AuditLog::factory()->create([
            'actor_user_id' => $staff->id,
            'action' => 'post.created',
            'entity_type' => 'Post',
        ]);

        $response = $this->actingAs($staff)
            ->getJson('/api/v1/org/dashboard/overview');

        $response->assertOk()
            ->assertJsonFragment(['id' => 'posts', 'value' => 1])
            ->assertJsonMissing(['id' => 'campaigns'])
            ->assertJsonMissing(['id' => 'staff'])
            ->assertJsonCount(1, 'item.activity')
            ->assertJsonPath('item.activity.0.entityType', 'Post');
    }

    public function test_staff_without_dashboard_permission_is_forbidden(): void
    {
        [, $staff] = $this->organizationUser(false);

        $this->actingAs($staff)
            ->getJson('/api/v1/org/dashboard/overview')
            ->assertForbidden();
    }

    /** @return array{Organization, User} */
    private function organizationUser(bool $owner): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $role = OrganizationRole::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
            'is_system' => $owner,
        ]);

        OrganizationStaff::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'organization_role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$organization, $user];
    }
}
