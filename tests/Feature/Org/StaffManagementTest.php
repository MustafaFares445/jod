<?php

declare(strict_types=1);

namespace Tests\Feature\Org;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Organization $organization;

    private OrganizationRole $managerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->managerRole = OrganizationRole::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Manager',
        ]);

        $this->grantPermissions($this->owner, [
            [PermissionGroup::ORG_STAFF, PermissionAction::VIEW],
            [PermissionGroup::ORG_STAFF, PermissionAction::CREATE],
            [PermissionGroup::ORG_STAFF, PermissionAction::UPDATE],
            [PermissionGroup::ORG_STAFF, PermissionAction::DELETE],
        ]);
    }

    public function test_list_organization_staff(): void
    {
        $staff1 = OrganizationStaff::factory()->create(['organization_id' => $this->organization->id]);
        $staff2 = OrganizationStaff::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/org/staff');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [], 'meta'])
            ->assertJsonCount(2, 'data');
    }

    public function test_invite_staff_member(): void
    {
        $data = [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '1234567890',
            'organization_role_id' => $this->managerRole->id,
        ];

        $response = $this->actingAs($this->owner)
            ->postJson('/api/v1/org/staff', $data);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role', 'invitedAt']])
            ->assertJson(['data' => ['name' => 'Jane Doe', 'email' => 'jane@example.com']]);

        $this->assertDatabaseHas('organization_staff', ['email' => 'jane@example.com']);
    }

    public function test_update_staff_member(): void
    {
        $staff = OrganizationStaff::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($this->owner)
            ->patchJson("/api/v1/org/staff/{$staff->id}", [
                'name' => 'New Name',
                'email' => $staff->email,
                'organization_role_id' => $this->managerRole->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');
    }

    public function test_remove_staff_member(): void
    {
        $staff = OrganizationStaff::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/v1/org/staff/{$staff->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('organization_staff', ['id' => $staff->id]);
    }

    public function test_cannot_manage_staff_from_different_organization(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $staff = OrganizationStaff::factory()->create(['organization_id' => $this->organization->id]);

        $response = $this->actingAs($otherUser)
            ->deleteJson("/api/v1/org/staff/{$staff->id}");

        $response->assertStatus(403);
    }
}
