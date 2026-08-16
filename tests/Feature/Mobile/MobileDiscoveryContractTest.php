<?php

declare(strict_types=1);
use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('discovery search matches content and publisher and supports engagement sort', function () {
    $organization = Organization::factory()->create([
        'name' => 'Hope Response Team',
    ]);
    $mostEngaged = mobile_discovery_contract_test_createPost($organization, [
        'title' => 'Second title alphabetically',
        'content' => 'Emergency food boxes for families.',
        'reactions_count' => 25,
    ]);
    mobile_discovery_contract_test_createPost($organization, [
        'title' => 'First title alphabetically',
        'content' => 'General community update.',
        'reactions_count' => 3,
    ]);
    $otherOrganization = Organization::factory()->create(['name' => 'Different Publisher']);
    mobile_discovery_contract_test_createPost($otherOrganization, [
        'content' => 'Unrelated update.',
        'reactions_count' => 100,
    ]);

    $publisherSearch = $this->getJson('/api/mobile/discovery/posts?search=Hope%20Response&sort=most_engaged');

    $publisherSearch->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $mostEngaged->id);

    $contentSearch = $this->getJson('/api/mobile/discovery/posts?search=food%20boxes');

    $contentSearch->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $mostEngaged->id);
});
test('action state filters follow campaign and viewer application state', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $activeVolunteerCampaign = mobile_discovery_contract_test_createCampaign($organization, 'Volunteer', 'active');
    $activeDonationCampaign = mobile_discovery_contract_test_createCampaign($organization, 'Donation', 'active');
    $closedCampaign = mobile_discovery_contract_test_createCampaign($organization, 'Closed donation', 'closed');

    $submittedPost = mobile_discovery_contract_test_createPost($organization, [
        'type' => 'volunteer_opportunity',
        'campaign_id' => $activeVolunteerCampaign->id,
    ]);
    $openPost = mobile_discovery_contract_test_createPost($organization, [
        'type' => 'donation_campaign',
        'campaign_id' => $activeDonationCampaign->id,
    ]);
    $closedPost = mobile_discovery_contract_test_createPost($organization, [
        'type' => 'donation_campaign',
        'campaign_id' => $closedCampaign->id,
    ]);

    CampaignApplication::query()->create([
        'organization_id' => $organization->id,
        'campaign_id' => $activeVolunteerCampaign->id,
        'name' => $user->name,
        'email' => $user->email,
        'campaign_title' => $activeVolunteerCampaign->title,
        'applicant_status' => 'pending',
        'applied_at' => now(),
        'created_by' => $user->id,
    ]);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $this->getJson('/api/mobile/discovery/posts?actionState=submitted')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $submittedPost->id)
        ->assertJsonPath('data.0.cta.state', 'submitted');

    $this->getJson('/api/mobile/discovery/posts?actionState=open')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $openPost->id)
        ->assertJsonPath('data.0.cta.state', 'open');

    $this->getJson('/api/mobile/discovery/posts?actionState=closed')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $closedPost->id)
        ->assertJsonPath('data.0.cta.state', 'closed');
});
function mobile_discovery_contract_test_createCampaign(Organization $organization, string $title, string $status): Campaign
{
    return Campaign::query()->create([
        'id' => (string) Str::uuid(),
        'title' => $title,
        'category' => 'health',
        'status' => $status,
        'organization_id' => $organization->id,
    ]);
}
/**
 * @param  array<string, mixed>  $overrides
 */
function mobile_discovery_contract_test_createPost(Organization $organization, array $overrides = []): Post
{
    return Post::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'title' => 'Public post',
        'summary' => 'Public summary',
        'content' => 'Public mobile content.',
        'type' => 'awareness',
        'status' => 'published',
        'location' => 'Damascus',
        'organization_id' => $organization->id,
        'reactions_count' => 0,
        'published_at' => now(),
    ], $overrides));
}
