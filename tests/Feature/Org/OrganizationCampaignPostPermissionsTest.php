<?php

declare(strict_types=1);
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner can create campaign and post without assignable permissions', function () {
    [$owner, $organization] = organization_campaign_post_permissions_test_organizationUser(true);

    $this->actingAs($owner)
        ->postJson('/api/v1/org/campaigns', campaignPayload())
        ->assertCreated();

    $this->actingAs($owner)
        ->postJson('/api/v1/org/posts', postPayload())
        ->assertCreated();

    $this->assertDatabaseHas('campaigns', ['organization_id' => $organization->id, 'status' => 'active']);
    $this->assertDatabaseHas('posts', ['organization_id' => $organization->id, 'status' => 'published']);
});
test('owner can still create draft campaign and post explicitly', function () {
    [$owner, $organization] = organization_campaign_post_permissions_test_organizationUser(true);

    $this->actingAs($owner)
        ->postJson('/api/v1/org/campaigns', [
            ...campaignPayload(),
            'status' => 'draft',
        ])
        ->assertCreated();

    $this->actingAs($owner)
        ->postJson('/api/v1/org/posts', [
            ...postPayload(),
            'status' => 'draft',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('campaigns', ['organization_id' => $organization->id, 'status' => 'draft']);
    $this->assertDatabaseHas('posts', ['organization_id' => $organization->id, 'status' => 'draft']);
});
test('staff requires action specific campaign permission', function () {
    [$staff, $organization] = organization_campaign_post_permissions_test_organizationUser(false);
    $campaign = Campaign::factory()->create(['organization_id' => $organization->id, 'status' => 'draft']);

    $this->grantPermissions($staff, [[PermissionGroup::ORG_CAMPAIGN, PermissionAction::VIEW]]);

    $this->actingAs($staff)->getJson('/api/v1/org/campaigns')->assertOk();
    $this->actingAs($staff)
        ->patchJson("/api/v1/org/campaigns/{$campaign->id}", ['status' => 'active'])
        ->assertForbidden();
});
test('campaign_update_accepts_status_while_post updates cannot bypass lifecycle', function () {
    [$owner, $organization] = organization_campaign_post_permissions_test_organizationUser(true);
    $campaign = Campaign::factory()->create(['organization_id' => $organization->id, 'status' => 'draft']);
    $post = Post::factory()->create(['organization_id' => $organization->id, 'status' => 'draft']);

    $this->actingAs($owner)
        ->putJson("/api/v1/org/campaigns/{$campaign->id}", ['status' => 'active'])
        ->assertUnprocessable();

    $this->actingAs($owner)
        ->putJson("/api/v1/org/posts/{$post->id}", ['status' => 'published'])
        ->assertUnprocessable();
});
test('campaign related post rejects other organization campaign', function () {
    [$owner] = organization_campaign_post_permissions_test_organizationUser(true);
    $otherOrganization = Organization::factory()->create();
    $campaign = Campaign::factory()->create([
        'organization_id' => $otherOrganization->id,
        'title' => 'Other Campaign',
    ]);

    $payload = postPayload();
    $payload['type'] = 'campaign_update';
    $payload['campaignTitle'] = $campaign->title;

    $this->actingAs($owner)
        ->postJson('/api/v1/org/posts', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('campaignTitle');
});
/** @return array{User, Organization} */
function organization_campaign_post_permissions_test_organizationUser(bool $owner): array
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

    return [$user, $organization];
}
function campaignPayload(): array
{
    return [
        'title' => 'Campaign',
        'summary' => 'Summary',
        'category' => 'health',
        'location' => 'Amman',
        'goalAmount' => 1000,
        'beneficiariesCount' => 10,
        'startDate' => now()->toDateString(),
        'endDate' => now()->addMonth()->toDateString(),
    ];
}
function postPayload(): array
{
    $category = Category::factory()->create(['status' => 'active']);

    return [
        'title' => 'Post',
        'summary' => 'Summary',
        'type' => 'general',
        'categoryId' => $category->id,
        'location' => 'Amman',
    ];
}
