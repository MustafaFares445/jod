<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileCampaignApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_apply_idempotently_to_an_active_volunteer_campaign(): void
    {
        [$campaign, $post] = $this->volunteerCampaign();
        $user = User::factory()->create([
            'phone' => '0999999999',
            'city' => 'Damascus',
        ]);
        Sanctum::actingAs($user);

        $first = $this->postJson("/api/mobile/campaigns/{$campaign->id}/applications", [])
            ->assertOk()
            ->assertJsonPath('data.campaignId', $campaign->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.phone', '0999999999')
            ->assertJsonPath('data.city', 'Damascus');

        $applicationId = $first->json('data.id');

        $this->postJson("/api/mobile/campaigns/{$campaign->id}/applications", [])
            ->assertOk()
            ->assertJsonPath('data.id', $applicationId)
            ->assertJsonPath('data.status', 'pending');

        $this->assertSame(1, CampaignApplication::query()
            ->where('campaign_id', $campaign->id)
            ->where('created_by', $user->id)
            ->where('source', 'mobile_app')
            ->count());
        $this->assertSame(1, (int) $campaign->refresh()->applicants_count);

        $this->getJson("/api/mobile/discovery/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.cta.state', 'submitted');
    }

    public function test_application_history_is_scoped_to_authenticated_user(): void
    {
        [$campaign] = $this->volunteerCampaign();
        $user = User::factory()->create();
        $other = User::factory()->create();

        CampaignApplication::query()->create([
            'organization_id' => $campaign->organization_id,
            'campaign_id' => $campaign->id,
            'name' => $other->name,
            'email' => $other->email,
            'campaign_title' => $campaign->title,
            'applicant_status' => 'pending',
            'applied_at' => now(),
            'source' => 'mobile_app',
            'created_by' => $other->id,
        ]);

        Sanctum::actingAs($user);
        $created = $this->postJson("/api/mobile/campaigns/{$campaign->id}/applications", [])
            ->assertOk()
            ->json('data.id');

        $this->getJson('/api/mobile/me/applications?perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $created);

        $otherApplication = CampaignApplication::query()
            ->where('created_by', $other->id)
            ->firstOrFail();

        $this->getJson("/api/mobile/me/applications/{$otherApplication->id}")
            ->assertNotFound();
    }

    public function test_withdrawn_application_reopens_cta_and_can_be_reapplied(): void
    {
        [$campaign, $post] = $this->volunteerCampaign();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $applicationId = $this->postJson("/api/mobile/campaigns/{$campaign->id}/applications", [])
            ->assertOk()
            ->json('data.id');

        $this->deleteJson("/api/mobile/me/applications/{$applicationId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'withdrawn');

        $this->assertSame(0, (int) $campaign->refresh()->applicants_count);

        $this->getJson("/api/mobile/discovery/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.cta.state', 'open');

        $this->postJson("/api/mobile/campaigns/{$campaign->id}/applications", [
            'phone' => '0911111111',
            'city' => 'Homs',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $applicationId)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.phone', '0911111111')
            ->assertJsonPath('data.city', 'Homs');

        $this->assertSame(1, (int) $campaign->refresh()->applicants_count);
    }

    public function test_campaign_without_published_volunteer_post_is_not_available_for_application(): void
    {
        $organization = Organization::factory()->create();
        $campaign = Campaign::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Donation only campaign',
            'category' => 'donation',
            'status' => 'active',
            'organization_id' => $organization->id,
        ]);
        Post::factory()->published()->create([
            'campaign_id' => $campaign->id,
            'organization_id' => $organization->id,
            'type' => 'donation_campaign',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/mobile/campaigns/{$campaign->id}/applications", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['campaign'], 'error.details');
    }

    public function test_application_routes_require_authentication(): void
    {
        [$campaign] = $this->volunteerCampaign();

        $this->postJson("/api/mobile/campaigns/{$campaign->id}/applications", [])->assertUnauthorized();
        $this->getJson('/api/mobile/me/applications')->assertUnauthorized();
    }

    /**
     * @return array{0: Campaign, 1: Post}
     */
    private function volunteerCampaign(): array
    {
        $organization = Organization::factory()->create();
        $campaign = Campaign::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Volunteer Program',
            'summary' => 'Help the community',
            'category' => 'volunteer',
            'status' => 'active',
            'organization_id' => $organization->id,
            'applicants_count' => 0,
        ]);
        $post = Post::factory()->published()->create([
            'campaign_id' => $campaign->id,
            'organization_id' => $organization->id,
            'type' => 'volunteer_opportunity',
        ]);

        return [$campaign, $post];
    }
}
