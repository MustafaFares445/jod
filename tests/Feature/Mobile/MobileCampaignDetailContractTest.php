<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Campaign;
use App\Models\CampaignApplication;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileCampaignDetailContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_donation_campaign_returns_mobile_donation_detail_fields(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'JOD Relief',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);
        $campaign = $this->campaign($organization, [
            'title' => 'Food Support',
            'summary' => 'Food campaign description.',
            'category' => 'food',
            'goal_amount' => 100000,
            'raised_amount' => 85000,
            'end_date' => now()->addDays(10)->toDateString(),
        ]);
        $this->publishedCampaignPost($campaign, 'donation_campaign');

        $this->getJson("/api/mobile/discovery/campaigns/{$campaign->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $campaign->id)
            ->assertJsonPath('data.campaignType', 'donation')
            ->assertJsonPath('data.publisherId', $organization->id)
            ->assertJsonPath('data.orgName', 'JOD Relief')
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.goalAmount', 100000)
            ->assertJsonPath('data.raisedAmount', 85000)
            ->assertJsonPath('data.statusTag', 'اقتربت من الاكتمال');
    }

    public function test_volunteer_campaign_returns_capacity_schedule_and_joined_count(): void
    {
        $organization = Organization::factory()->create(['status' => 'active']);
        $campaign = $this->campaign($organization, [
            'title' => 'Community Volunteers',
            'category' => 'employment',
            'required_volunteers' => 20,
            'applicants_count' => 4,
            'start_date' => now()->addDays(5)->toDateString(),
            'event_time' => '10:30:00',
            'end_date' => now()->addDays(12)->toDateString(),
        ]);
        $this->publishedCampaignPost($campaign, 'volunteer_opportunity');

        $this->application($campaign, 'accepted');
        $this->application($campaign, 'approved');
        $this->application($campaign, 'pending');
        $this->application($campaign, 'rejected');

        $this->getJson("/api/mobile/discovery/campaigns/{$campaign->id}")
            ->assertOk()
            ->assertJsonPath('data.campaignType', 'volunteering')
            ->assertJsonPath('data.date', $campaign->start_date->toDateString())
            ->assertJsonPath('data.time', '10:30')
            ->assertJsonPath('data.requiredVolunteers', 20)
            ->assertJsonPath('data.joinedVolunteers', 2)
            ->assertJsonPath('data.applicantsCount', 4);
    }

    public function test_campaign_list_uses_same_mobile_contract(): void
    {
        $organization = Organization::factory()->create(['status' => 'active']);
        $campaign = $this->campaign($organization, [
            'required_volunteers' => 8,
            'event_time' => '09:15:00',
            'end_date' => now()->addDays(10)->toDateString(),
        ]);
        $this->publishedCampaignPost($campaign, 'volunteer_opportunity');

        $this->getJson('/api/mobile/discovery/campaigns?perPage=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', $campaign->id)
            ->assertJsonPath('data.0.campaignType', 'volunteering')
            ->assertJsonPath('data.0.requiredVolunteers', 8)
            ->assertJsonPath('data.0.time', '09:15')
            ->assertJsonStructure([
                'data' => [[
                    'id', 'title', 'description', 'city', 'status', 'statusTag',
                    'category', 'campaignType', 'publisherId', 'orgName', 'verified',
                    'endDate', 'goalAmount', 'raisedAmount', 'date', 'time',
                    'requiredVolunteers', 'joinedVolunteers', 'applicantsCount', 'donorsCount',
                ]],
            ]);
    }

    public function test_inactive_campaign_remains_hidden_from_mobile_detail(): void
    {
        $organization = Organization::factory()->create();
        $campaign = $this->campaign($organization, ['status' => 'closed']);

        $this->getJson("/api/mobile/discovery/campaigns/{$campaign->id}")
            ->assertNotFound();
    }

    /** @param array<string, mixed> $overrides */
    private function campaign(Organization $organization, array $overrides = []): Campaign
    {
        return Campaign::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'title' => fake()->sentence(3),
            'summary' => fake()->sentence(),
            'content' => fake()->paragraph(),
            'category' => 'employment',
            'status' => 'active',
            'location' => 'Damascus',
            'organization_id' => $organization->id,
            'goal_amount' => 0,
            'raised_amount' => 0,
            'beneficiaries_count' => 0,
            'donors_count' => 0,
            'applicants_count' => 0,
            'required_volunteers' => 0,
            'start_date' => now()->addDays(5)->toDateString(),
            'event_time' => null,
            'end_date' => now()->addDays(10)->toDateString(),
        ], $overrides));
    }

    private function publishedCampaignPost(Campaign $campaign, string $type): Post
    {
        return Post::query()->create([
            'id' => (string) Str::uuid(),
            'title' => fake()->sentence(4),
            'summary' => fake()->sentence(),
            'content' => fake()->paragraph(),
            'type' => $type,
            'status' => 'published',
            'organization_id' => $campaign->organization_id,
            'campaign_id' => $campaign->id,
            'published_at' => now(),
        ]);
    }

    private function application(Campaign $campaign, string $status): CampaignApplication
    {
        $user = User::factory()->create();

        return CampaignApplication::query()->create([
            'organization_id' => $campaign->organization_id,
            'campaign_id' => $campaign->id,
            'name' => $user->name,
            'email' => $user->email,
            'campaign_title' => $campaign->title,
            'applicant_status' => $status,
            'applied_at' => now(),
            'source' => 'mobile_app',
            'created_by' => $user->id,
        ]);
    }
}
