<?php

declare(strict_types=1);
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
use App\Support\Permissions\PermissionNameResolver;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
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
});
test('list organization roles', function () {
    OrganizationRole::factory()
        ->count(3)
        ->create(['organization_id' => $this->organization->id]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/org/roles');

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => [], 'meta'])
        ->assertJsonCount(4, 'data');
});
test('create role', function () {
    $data = [
        'name' => 'Custom Role',
        'description' => 'A custom role for testing',
        'permissions' => [
            PermissionNameResolver::resolve(PermissionGroup::ORG_CAMPAIGN, PermissionAction::VIEW),
            PermissionNameResolver::resolve(PermissionGroup::ORG_POST, PermissionAction::VIEW),
        ],
        'isActive' => true,
    ];

    $response = $this->actingAs($this->owner)
        ->postJson('/api/v1/org/roles', $data);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'role', 'description', 'permissions', 'isActive']])
        ->assertJsonPath('data.role', 'Custom Role');

    $this->assertDatabaseHas('organization_roles', ['name' => 'Custom Role']);
});
test('update role', function () {
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
            'isActive' => false,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.role', 'Advanced Editor')
        ->assertJsonPath('data.isActive', false);
});
test('delete role', function () {
    $role = OrganizationRole::factory()->create([
        'organization_id' => $this->organization->id,
        'is_system' => false,
    ]);

    $response = $this->actingAs($this->owner)
        ->deleteJson("/api/v1/org/roles/{$role->id}");

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('item', null);
    $this->assertDatabaseMissing('organization_roles', ['id' => $role->id]);
});
test('cannot delete system role', function () {
    $role = OrganizationRole::factory()->create([
        'organization_id' => $this->organization->id,
        'is_system' => true,
        'name' => 'Protected Owner',
    ]);

    $response = $this->actingAs($this->owner)
        ->deleteJson("/api/v1/org/roles/{$role->id}");

    $response->assertConflict();
    $this->assertDatabaseHas('organization_roles', ['id' => $role->id]);
});
test('permission catalog returns only assignable ready permissions', function () {
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
});
test('role permissions require view permission', function () {
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
});
test('role cannot receive owner only or deferred permissions', function () {
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
});
test('active staff role cannot be deleted', function () {
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
});
test('system role cannot be updated', function () {
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
});
test('staff cannot access role administration or catalog even with permissions', function () {
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
});
test('cannot manage roles from different organization', function () {
    $otherOrg = Organization::factory()->create();
    $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
    $role = OrganizationRole::factory()->create(['organization_id' => $this->organization->id]);

    $response = $this->actingAs($otherUser)
        ->deleteJson("/api/v1/org/roles/{$role->id}");

    $response->assertStatus(403);
});
