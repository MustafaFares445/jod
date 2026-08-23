<?php

declare(strict_types=1);
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
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

    $this->managerRole = OrganizationRole::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Manager',
        'is_system' => false,
    ]);
});
test('list organization staff', function () {
    OrganizationStaff::factory()->create(['organization_id' => $this->organization->id]);
    OrganizationStaff::factory()->create(['organization_id' => $this->organization->id]);

    $response = $this->actingAs($this->owner)
        ->getJson('/api/v1/org/staff');

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => [], 'meta'])
        ->assertJsonCount(3, 'data');
});
test('invite staff member', function () {
    $data = [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '0912345678',
        'organizationRoleId' => $this->managerRole->id,
    ];

    $response = $this->actingAs($this->owner)
        ->postJson('/api/v1/org/staff', $data);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'invitedAt']])
        ->assertJson(['data' => ['name' => 'Jane Doe', 'email' => 'jane@example.com']]);

    $this->assertDatabaseHas('organization_staff', ['email' => 'jane@example.com']);
});
test('staff phone must be a Syrian mobile number', function () {
    $this->actingAs($this->owner)
        ->postJson('/api/v1/org/staff', [
            'name' => 'Invalid Staff',
            'email' => 'invalid@example.com',
            'phone' => '1234567890',
            'organizationRoleId' => $this->managerRole->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

test('update staff member', function () {
    $staff = OrganizationStaff::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Old Name',
    ]);

    $response = $this->actingAs($this->owner)
        ->patchJson("/api/v1/org/staff/{$staff->id}", [
            'name' => 'New Name',
            'email' => $staff->email,
            'phone' => '0998765432',
            'organizationRoleId' => $this->managerRole->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.name', 'New Name');
});
test('remove staff member', function () {
    $staff = OrganizationStaff::factory()->create(['organization_id' => $this->organization->id]);

    $response = $this->actingAs($this->owner)
        ->deleteJson("/api/v1/org/staff/{$staff->id}");

    $response->assertOk()
        ->assertJsonPath('statusCode', 200)
        ->assertJsonPath('item', null);
    $this->assertDatabaseMissing('organization_staff', ['id' => $staff->id]);
});
test('staff role must belong to the same organization', function () {
    $otherOrganization = Organization::factory()->create();
    $otherRole = OrganizationRole::factory()->create([
        'organization_id' => $otherOrganization->id,
        'is_system' => false,
    ]);

    $this->actingAs($this->owner)
        ->postJson('/api/v1/org/staff', [
            'name' => 'Cross Org Staff',
            'email' => 'cross-org@example.com',
            'phone' => '0934567890',
            'organizationRoleId' => $otherRole->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('organization_role_id');
});
test('membership from another organization does not grant owner access', function () {
    $otherOrganization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $this->organization->id]);
    $otherOwnerRole = OrganizationRole::factory()->create([
        'organization_id' => $otherOrganization->id,
        'is_active' => true,
        'is_system' => true,
    ]);

    OrganizationStaff::factory()->create([
        'organization_id' => $otherOrganization->id,
        'user_id' => $user->id,
        'organization_role_id' => $otherOwnerRole->id,
        'status' => 'active',
    ]);

    expect($user->fresh()->isOrganizationOwner())->toBeFalse();

    $this->actingAs($user)
        ->getJson('/api/v1/org/staff')
        ->assertForbidden();
});
test('final owner cannot be demoted', function () {
    $ownerMembership = OrganizationStaff::query()
        ->where('user_id', $this->owner->id)
        ->firstOrFail();

    $this->actingAs($this->owner)
        ->patchJson("/api/v1/org/staff/{$ownerMembership->id}", [
            'name' => $ownerMembership->name,
            'email' => $ownerMembership->email,
            'phone' => $ownerMembership->phone ?: '0911111111',
            'organizationRoleId' => $this->managerRole->id,
        ])
        ->assertConflict();

    $this->assertDatabaseHas('organization_staff', [
        'id' => $ownerMembership->id,
        'organization_role_id' => $ownerMembership->organization_role_id,
        'status' => 'active',
    ]);
});
test('final owner cannot be deactivated', function () {
    $ownerMembership = OrganizationStaff::query()
        ->where('user_id', $this->owner->id)
        ->firstOrFail();

    $this->actingAs($this->owner)
        ->patchJson("/api/v1/org/staff/{$ownerMembership->id}", [
            'status' => 'inactive',
        ])
        ->assertConflict();
});
test('owner cannot remove own membership', function () {
    $ownerMembership = OrganizationStaff::query()
        ->where('user_id', $this->owner->id)
        ->firstOrFail();

    $this->actingAs($this->owner)
        ->deleteJson("/api/v1/org/staff/{$ownerMembership->id}")
        ->assertForbidden();
});
test('staff cannot manage staff even with staff permissions', function () {
    $staffUser = User::factory()->create(['organization_id' => $this->organization->id]);
    $staffRole = OrganizationRole::factory()->create([
        'organization_id' => $this->organization->id,
        'name' => 'Malformed Manager',
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
        [PermissionGroup::ORG_STAFF, PermissionAction::VIEW],
        [PermissionGroup::ORG_STAFF, PermissionAction::MANAGE],
        [PermissionGroup::ORG_STAFF, PermissionAction::DELETE],
    ]);

    $this->actingAs($staffUser)
        ->getJson('/api/v1/org/staff')
        ->assertForbidden();
});
test('cannot manage staff from different organization', function () {
    $otherOrg = Organization::factory()->create();
    $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
    $staff = OrganizationStaff::factory()->create(['organization_id' => $this->organization->id]);

    $response = $this->actingAs($otherUser)
        ->deleteJson("/api/v1/org/staff/{$staff->id}");

    $response->assertStatus(403);
});
