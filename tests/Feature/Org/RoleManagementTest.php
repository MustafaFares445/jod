<?php

declare(strict_types=1);

namespace Tests\Feature\Org;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->owner = User::factory()->create(['organization_id' => $this->organization->id]);

        $ownerRole = OrganizationRole::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Owner',
            'is_active' => true,
            'is_system' => true,
        ]);

        OrganizationStaff::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->owner->id,
            'organization_role_id' => $ownerRole->id,
            'status' => 'active',
        ]);
    }

    public function test_list_organization_roles(): void
    {
        OrganizationRole::factory()
            ->count(3)
            ->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/org/roles');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [], 'meta'])
            ->assertJsonCount(4, 'data');
    }

    public function test_create_role(): void
    {
        $data = [
            'name' => 'Custom Role',
            'description' => 'A custom role for testing',
            'permissions' => [
                PermissionNameResolver::resolve(PermissionGroup::ORG_CAMPAIGN, PermissionAction::VIEW),
                PermissionNameResolver::resolve(PermissionGroup::ORG_POST, PermissionAction::VIEW),
            ],
            'is_active' => true,
        ];

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/org/roles', $data);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'role', 'description', 'permissions', 'isActive']])
            ->assertJsonPath('data.role', 'Custom Role');

        $this->assertDatabaseHas('organization_roles', ['name' => 'Custom Role']);
    }

    public function test_update_role(): void
    {
        $role = OrganizationRole::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Editor',
            'is_system' => false,
        ]);

        $response = $this->actingAs($this->owner)
            ->patchJson("/api/v1/org/roles/{$role->id}", [
                'name' => 'Advanced Editor',
                'description' => 'Updated description',
                'permissions' => [
                    PermissionNameResolver::resolve(PermissionGroup::ORG_POST, PermissionAction::VIEW),
                    PermissionNameResolver::resolve(PermissionGroup::ORG_POST, PermissionAction::CREATE),
                    PermissionNameResolver::resolve(PermissionGroup::ORG_POST, PermissionAction::UPDATE),
                ],
                'is_active' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.role', 'Advanced Editor');
    }

    public function test_delete_role(): void
    {
        $role = OrganizationRole::factory()->create([
            'organization_id' => $this->organization->id,
            'is_system' => false,
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/org/roles/{$role->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('organization_roles', ['id' => $role->id]);
    }

    public function test_cannot_delete_system_role(): void
    {
        $role = OrganizationRole::factory()->create([
            'organization_id' => $this->organization->id,
            'is_system' => true,
            'name' => 'Protected Owner',
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/org/roles/{$role->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('organization_roles', ['id' => $role->id]);
    }

    public function test_permission_catalog_returns_only_assignable_ready_permissions(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/org/permissions/catalog');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'name',
                    'group',
                    'description',
                    'action',
                    'requires',
                    'assignable',
                ]],
            ])
            ->assertJsonFragment([
                'id' => PermissionNameResolver::resolve(PermissionGroup::DASHBOARD, PermissionAction::VIEW),
                'assignable' => true,
            ])
            ->assertJsonMissing([
                'id' => PermissionNameResolver::resolve(PermissionGroup::ORG_STAFF, PermissionAction::VIEW),
            ])
            ->assertJsonMissing([
                'id' => PermissionNameResolver::resolve(PermissionGroup::ORG_ROLE, PermissionAction::VIEW),
            ])
            ->assertJsonMissing([
                'id' => PermissionNameResolver::resolve(PermissionGroup::ORG_NOTIFICATION, PermissionAction::VIEW),
            ]);
    }

    public function test_role_permissions_require_view_permission(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/org/roles', [
                'name' => 'Invalid Editor',
                'description' => 'Missing view dependency',
                'permissions' => [
                    PermissionNameResolver::resolve(PermissionGroup::ORG_POST, PermissionAction::UPDATE),
                ],
                'is_active' => true,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('permissions');
    }

    public function test_role_cannot_receive_owner_only_or_deferred_permissions(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/org/roles', [
                'name' => 'Unsafe Role',
                'description' => 'Contains protected permissions',
                'permissions' => [
                    PermissionNameResolver::resolve(PermissionGroup::ORG_STAFF, PermissionAction::VIEW),
                ],
                'is_active' => true,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('permissions.0');
    }

    public function test_active_staff_role_cannot_be_deleted(): void
    {
        $role = OrganizationRole::factory()->create([
            'organization_id' => $this->organization->id,
            'is_system' => false,
        ]);

        OrganizationStaff::factory()->create([
            'organization_id' => $this->organization->id,
            'organization_role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->owner)
            ->deleteJson("/api/v1/org/roles/{$role->id}")
            ->assertConflict();

        $this->assertDatabaseHas('organization_roles', ['id' => $role->id]);
    }

    public function test_system_role_cannot_be_updated(): void
    {
        $systemRole = OrganizationRole::query()
            ->where('organization_id', $this->organization->id)
            ->where('is_system', true)
            ->firstOrFail();

        $this->actingAs($this->owner)
            ->putJson("/api/v1/org/roles/{$systemRole->id}", [
                'name' => 'Changed Owner',
                'description' => 'Unsafe',
                'permissions' => [
                    PermissionNameResolver::resolve(PermissionGroup::DASHBOARD, PermissionAction::VIEW),
                ],
                'is_active' => true,
            ])
            ->assertConflict();
    }

    public function test_staff_cannot_access_role_administration_or_catalog_even_with_permissions(): void
    {
        $staffUser = User::factory()->create(['organization_id' => $this->organization->id]);
        $staffRole = OrganizationRole::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Manager',
            'is_active' => true,
            'is_system' => false,
        ]);

        OrganizationStaff::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $staffUser->id,
            'organization_role_id' => $staffRole->id,
            'status' => 'active',
        ]);

        $this->grantPermissions($staffUser, [
            [PermissionGroup::ORG_ROLE, PermissionAction::VIEW],
            [PermissionGroup::ORG_ROLE, PermissionAction::CREATE],
            [PermissionGroup::ORG_STAFF, PermissionAction::MANAGE],
        ]);

        $this->actingAs($staffUser)
            ->getJson('/api/v1/org/permissions/catalog')
            ->assertForbidden();

        $this->actingAs($staffUser)
            ->getJson('/api/v1/org/roles')
            ->assertForbidden();
    }

    public function test_cannot_manage_roles_from_different_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $role = OrganizationRole::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($otherUser)
            ->deleteJson("/api/v1/org/roles/{$role->id}");

        $response->assertStatus(403);
    }
}
