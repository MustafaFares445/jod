<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->grantPermissions($this->user, [
            [PermissionGroup::ORGANIZATION, PermissionAction::VIEW],
            [PermissionGroup::ORGANIZATION, PermissionAction::CREATE],
            [PermissionGroup::ORGANIZATION, PermissionAction::UPDATE],
            [PermissionGroup::ORGANIZATION, PermissionAction::DELETE],
            [PermissionGroup::ORGANIZATION, PermissionAction::VERIFY],
            [PermissionGroup::ORGANIZATION, PermissionAction::ACCEPT],
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_lists_organizations_with_filters(): void
    {
        Organization::query()->create([
            'name' => 'Alpha Org',
            'email' => 'alpha@example.com',
            'location' => 'Amman',
            'status' => 'active',
        ]);

        Organization::query()->create([
            'name' => 'Beta Org',
            'email' => 'beta@example.com',
            'location' => 'Irbid',
            'status' => 'inactive',
        ]);

        $response = $this->getJson('/api/v1/admin/organizations?filter.status=active&filter.location=Amman&sort=name');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Alpha Org');
    }

    public function test_creates_and_updates_organization(): void
    {
        $createResponse = $this->postJson('/api/v1/admin/organizations', [
            'name' => 'New Org',
            'email' => 'new-org@example.com',
            'phone' => '+962790000001',
            'location' => 'Amman',
            'organizationType' => 'NGO',
            'verificationStatus' => 'unverified',
        ]);

        $createResponse->assertCreated();
        $organizationId = $createResponse->json('data.id');

        $updateResponse = $this->patchJson("/api/v1/admin/organizations/{$organizationId}", [
            'name' => 'Updated Org',
            'phone' => '+962790000002',
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('data.name', 'Updated Org');
        $this->assertDatabaseHas('organizations', [
            'id' => $organizationId,
            'name' => 'Updated Org',
            'phone' => '+962790000002',
        ]);
    }

    public function test_updates_status_verification_and_accepts(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Workflow Org',
            'email' => 'workflow@example.com',
            'status' => 'inactive',
            'verification_status' => 'unverified',
        ]);

        $this->patchJson("/api/v1/admin/organizations/{$organization->id}/status", [
            'status' => 'active',
        ])->assertOk()->assertJsonPath('data.status', 'active');

        $this->patchJson("/api/v1/admin/organizations/{$organization->id}/verification", [
            'verificationStatus' => 'verified',
        ])->assertOk()->assertJsonPath('data.verificationStatus', 'verified');

        $this->postJson("/api/v1/admin/organizations/{$organization->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.verificationStatus', 'verified');
    }

    public function test_deletes_organization_softly(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Delete Org',
            'email' => 'delete@example.com',
        ]);

        $this->deleteJson("/api/v1/admin/organizations/{$organization->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('organizations', ['id' => $organization->id]);
    }
}
