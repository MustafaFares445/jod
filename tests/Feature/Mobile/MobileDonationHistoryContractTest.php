<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileDonationHistoryContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_contributed_history_returns_mobile_screen_fields(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $campaign = $this->campaign($organization, [
            'goal_amount' => 500000,
            'status' => 'active',
        ]);
        $donation = Donation::factory()->create([
            'organization_id' => $organization->id,
            'campaign_id' => $campaign->id,
            'campaign_title' => $campaign->title,
            'amount_or_type' => 25000,
            'created_by' => $user->id,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/mobile/me/donations?flow=contributed&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $donation->id)
            ->assertJsonPath('data.0.flow', 'contributed')
            ->assertJsonPath('data.0.donatedAmount', 25000)
            ->assertJsonPath('data.0.targetAmount', 500000)
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.organization', $organization->name);
    }

    public function test_received_history_is_scoped_to_users_organization(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $staff = User::factory()->create(['organization_id' => $organization->id]);
        $donor = User::factory()->create();
        $campaign = $this->campaign($organization);
        $otherCampaign = $this->campaign($otherOrganization);

        $received = Donation::factory()->create([
            'organization_id' => $organization->id,
            'campaign_id' => $campaign->id,
            'campaign_title' => $campaign->title,
            'created_by' => $donor->id,
        ]);
        Donation::factory()->create([
            'organization_id' => $otherOrganization->id,
            'campaign_id' => $otherCampaign->id,
            'campaign_title' => $otherCampaign->title,
            'created_by' => $donor->id,
        ]);

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/mobile/me/donations?flow=received&perPage=10');
        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $received->id)
            ->assertJsonPath('data.0.flow', 'received');
    }

    public function test_received_history_is_empty_for_user_without_organization(): void
    {
        $user = User::factory()->create(['organization_id' => null]);
        Donation::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/mobile/me/donations?flow=received')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_organization_user_can_show_received_donation_but_outsider_cannot(): void
    {
        $organization = Organization::factory()->create();
        $staff = User::factory()->create(['organization_id' => $organization->id]);
        $outsider = User::factory()->create();
        $donor = User::factory()->create();
        $campaign = $this->campaign($organization);
        $donation = Donation::factory()->create([
            'organization_id' => $organization->id,
            'campaign_id' => $campaign->id,
            'campaign_title' => $campaign->title,
            'created_by' => $donor->id,
        ]);

        Sanctum::actingAs($staff);
        $this->getJson("/api/mobile/me/donations/{$donation->id}")
            ->assertOk()
            ->assertJsonPath('data.flow', 'received');

        Sanctum::actingAs($outsider);
        $this->getJson("/api/mobile/me/donations/{$donation->id}")
            ->assertNotFound();
    }

    public function test_recorded_donation_defaults_city_from_profile(): void
    {
        $user = User::factory()->create(['city' => 'Aleppo']);
        $organization = Organization::factory()->create();
        $campaign = $this->campaign($organization);
        Sanctum::actingAs($user);

        $this->postJson("/api/mobile/campaigns/{$campaign->id}/donations", [
            'amount' => 100,
            'paymentMethod' => 'cash',
        ])
            ->assertOk()
            ->assertJsonPath('data.city', 'Aleppo');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function campaign(Organization $organization, array $overrides = []): Campaign
    {
        return Campaign::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'title' => fake()->sentence(3),
            'category' => 'donation',
            'status' => 'active',
            'organization_id' => $organization->id,
            'goal_amount' => 1000,
            'raised_amount' => 0,
        ], $overrides));
    }
}
