<?php

declare(strict_types=1);
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Campaign;
use App\Models\Post;
use App\Models\Organization;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
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
});
test('lists organizations with live campaign and post counts', function () {
    $organization = Organization::query()->create([
        'name' => 'Counted Org',
        'email' => 'counted@example.com',
        'status' => 'active',
    ]);

    Campaign::query()->create([
        'title' => 'Counted campaign',
        'organization_id' => $organization->id,
        'status' => 'active',
    ]);
    Post::query()->create([
        'title' => 'Counted post',
        'organization_id' => $organization->id,
        'status' => 'published',
        'type' => 'general',
    ]);

    $response = $this->getJson('/api/v1/admin/organizations?filter.search=Counted');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.campaignsCount', 1)
        ->assertJsonPath('data.0.postsCount', 1);
});
test('lists organizations with filters', function () {

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
});
test('creates and updates organization', function () {
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
});
test('updates status verification and accepts', function () {
    $organization = Organization::query()->create([
        'name' => 'Workflow Org',
        'email' => 'workflow@example.com',
        'status' => 'inactive',
        'verification_status' => 'unverified',
    ]);

    $this->patchJson("/api/v1/admin/organizations/{$organization->id}/status", [
        'status' => 'active',
    ])->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.verificationStatus', 'verified');

    $this->patchJson("/api/v1/admin/organizations/{$organization->id}/verification", [
        'verificationStatus' => 'unverified',
    ])->assertOk()
        ->assertJsonPath('data.status', 'inactive')
        ->assertJsonPath('data.verificationStatus', 'unverified');

    $this->postJson("/api/v1/admin/organizations/{$organization->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.verificationStatus', 'verified');
});
test('deletes organization softly', function () {
    $organization = Organization::query()->create([
        'name' => 'Delete Org',
        'email' => 'delete@example.com',
    ]);

    $this->deleteJson("/api/v1/admin/organizations/{$organization->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Data deleted successfully.');

    $this->assertSoftDeleted('organizations', ['id' => $organization->id]);
});
