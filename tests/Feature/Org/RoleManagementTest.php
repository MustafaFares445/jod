<?php

declare(strict_types=1);

namespace Tests\Feature\Org;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Organization;
use App\Models\OrganizationRole;
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
        $this->grantPermissions($this->owner, [
            [PermissionGroup::ORG_ROLE, PermissionAction::VIEW],
            [PermissionGroup::ORG_ROLE, PermissionAction::CREATE],
            [PermissionGroup::ORG_ROLE, PermissionAction::UPDATE],
            [PermissionGroup::ORG_ROLE, PermissionAction::DELETE],
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
            ->assertJsonCount(3, 'data');
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

        $response->assertStatus(204);
        $this->assertDatabaseMissing('organization_roles', ['id' => $role->id]);
    }

    public function test_cannot_delete_system_role(): void
    {
        $role = OrganizationRole::factory()->create([
            'organization_id' => $this->organization->id,
            'is_system' => true,
            'name' => 'Owner',
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/org/roles/{$role->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('organization_roles', ['id' => $role->id]);
    }

    public function test_permission_catalog(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/org/permissions/catalog');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'group']]]);
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
