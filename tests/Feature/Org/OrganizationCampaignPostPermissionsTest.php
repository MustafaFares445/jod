<?php

declare(strict_types=1);

namespace Tests\Feature\Org;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationCampaignPostPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_campaign_and_post_without_assignable_permissions(): void
    {
        [$owner, $organization] = $this->organizationUser(true);

        $this->actingAs($owner)
            ->postJson('/api/v1/org/campaigns', $this->campaignPayload())
            ->assertCreated();

        $this->actingAs($owner)
            ->postJson('/api/v1/org/posts', $this->postPayload())
            ->assertCreated();

        $this->assertDatabaseHas('campaigns', ['organization_id' => $organization->id, 'status' => 'draft']);
        $this->assertDatabaseHas('posts', ['organization_id' => $organization->id, 'status' => 'draft']);
    }

    public function test_staff_requires_action_specific_campaign_permission(): void
    {
        [$staff, $organization] = $this->organizationUser(false);
        $campaign = Campaign::factory()->create(['organization_id' => $organization->id, 'status' => 'draft']);

        $this->grantPermissions($staff, [[PermissionGroup::ORG_CAMPAIGN, PermissionAction::VIEW]]);

        $this->actingAs($staff)->getJson('/api/v1/org/campaigns')->assertOk();
        $this->actingAs($staff)
            ->patchJson("/api/v1/org/campaigns/{$campaign->id}", ['status' => 'active'])
            ->assertForbidden();
    }

    public function test_campaign_update_accepts_status_while_post_updates_cannot_bypass_lifecycle(): void
    {
        [$owner, $organization] = $this->organizationUser(true);
        $campaign = Campaign::factory()->create(['organization_id' => $organization->id, 'status' => 'draft']);
        $post = Post::factory()->create(['organization_id' => $organization->id, 'status' => 'draft']);

        $this->actingAs($owner)
            ->putJson("/api/v1/org/campaigns/{$campaign->id}", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->actingAs($owner)
            ->putJson("/api/v1/org/posts/{$post->id}", ['status' => 'published'])
            ->assertUnprocessable();
    }

    public function test_campaign_related_post_rejects_other_organization_campaign(): void
    {
        [$owner] = $this->organizationUser(true);
        $otherOrganization = Organization::factory()->create();
        $campaign = Campaign::factory()->create([
            'organization_id' => $otherOrganization->id,
            'title' => 'Other Campaign',
        ]);

        $payload = $this->postPayload();
        $payload['type'] = 'campaign_update';
        $payload['campaignTitle'] = $campaign->title;

        $this->actingAs($owner)
            ->postJson('/api/v1/org/posts', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('campaignTitle');
    }

    /** @return array{User, Organization} */
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

        return [$user, $organization];
    }

    private function campaignPayload(): array
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

    private function postPayload(): array
    {
        return [
            'title' => 'Post',
            'summary' => 'Summary',
            'type' => 'general',
            'authorName' => 'Author',
            'location' => 'Amman',
        ];
    }
}
