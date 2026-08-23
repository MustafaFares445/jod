<?php

declare(strict_types=1);
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

uses(RefreshDatabase::class);

test('owner receives real organization scoped overview', function () {
    [$organization, $owner] = organization_overview_test_organizationUser(true);
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
        ->assertJsonPath('data.stats.0.id', 'campaigns')
        ->assertJsonPath('data.stats.0.value', 1)
        ->assertJsonFragment(['id' => 'posts', 'value' => 1])
        ->assertJsonFragment(['id' => 'staff', 'value' => 1])
        ->assertJsonPath('data.recentActivity.0.entityType', 'Campaign');
});
test('staff receives only permitted stats and activity', function () {
    [$organization, $staff] = organization_overview_test_organizationUser(false);
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
        ->assertJsonCount(1, 'data.recentActivity')
        ->assertJsonPath('data.recentActivity.0.entityType', 'Post');
});
test('staff without dashboard permission is forbidden', function () {
    [, $staff] = organization_overview_test_organizationUser(false);

    $this->actingAs($staff)
        ->getJson('/api/v1/org/dashboard/overview')
        ->assertForbidden();
});
test('organization dashboard APIs require active verified organization', function () {
    [$organization, $owner] = organization_overview_test_organizationUser(true);
    $organization->update(['verification_status' => 'pending']);

    $this->actingAs($owner)
        ->getJson('/api/v1/org/dashboard/overview')
        ->assertForbidden()
        ->assertJsonPath('message', 'Organization must be active and verified to access dashboard APIs.');
});
/** @return array{Organization, User} */
function organization_overview_test_organizationUser(bool $owner): array
{
    $organization = Organization::factory()->create([
        'status' => 'active',
        'verification_status' => 'verified',
    ]);
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
