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

class MobileDonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_record_donation_and_campaign_totals_are_updated(): void
    {
        $user = User::factory()->create([
            'name' => 'Mobile Donor',
            'email' => 'mobile.donor@example.com',
            'phone' => '+962790000001',
        ]);
        $campaign = $this->createCampaign([
            'raised_amount' => 100,
            'donors_count' => 3,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/mobile/campaigns/{$campaign->id}/donations", [
            'amount' => 25.50,
            'paymentMethod' => 'bank_transfer',
            'city' => 'Amman',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.campaignId', $campaign->id)
            ->assertJsonPath('data.campaignTitle', $campaign->title)
            ->assertJsonPath('data.organizationName', $campaign->organization->name)
            ->assertJsonPath('data.amount', 25.5)
            ->assertJsonPath('data.paymentMethod', 'bank_transfer')
            ->assertJsonPath('data.source', 'mobile_app');

        $this->assertDatabaseHas('donations', [
            'id' => $response->json('data.id'),
            'campaign_id' => $campaign->id,
            'organization_id' => $campaign->organization_id,
            'name' => 'Mobile Donor',
            'email' => 'mobile.donor@example.com',
            'phone' => '+962790000001',
            'amount_or_type' => '25.50',
            'payment_method' => 'bank_transfer',
            'source' => 'mobile_app',
            'created_by' => $user->id,
        ]);

        $campaign->refresh();
        $this->assertSame('125.50', $campaign->raised_amount);
        $this->assertSame(4, $campaign->donors_count);

        $this->postJson("/api/mobile/campaigns/{$campaign->id}/donations", [
            'amount' => 10,
            'paymentMethod' => 'cash',
        ])->assertOk();

        $campaign->refresh();
        $this->assertSame('135.50', $campaign->raised_amount);
        $this->assertSame(4, $campaign->donors_count);
    }

    public function test_donation_requires_active_campaign_and_valid_payload(): void
    {
        $user = User::factory()->create();
        $activeCampaign = $this->createCampaign();
        $closedCampaign = $this->createCampaign(['status' => 'closed']);
        Sanctum::actingAs($user);

        $this->postJson("/api/mobile/campaigns/{$activeCampaign->id}/donations", [
            'amount' => 0,
            'paymentMethod' => 'crypto',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'paymentMethod'], 'error.details');

        $this->postJson("/api/mobile/campaigns/{$closedCampaign->id}/donations", [
            'amount' => 25,
            'paymentMethod' => 'cash',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['campaign'], 'error.details');
    }

    public function test_donation_history_is_paginated_filterable_and_scoped_to_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $firstCampaign = $this->createCampaign(['title' => 'First Campaign']);
        $secondCampaign = $this->createCampaign(['title' => 'Second Campaign']);

        $firstDonation = Donation::factory()->create([
            'organization_id' => $firstCampaign->organization_id,
            'campaign_id' => $firstCampaign->id,
            'campaign_title' => $firstCampaign->title,
            'created_by' => $user->id,
            'donated_at' => now()->subDay(),
        ]);
        $secondDonation = Donation::factory()->create([
            'organization_id' => $secondCampaign->organization_id,
            'campaign_id' => $secondCampaign->id,
            'campaign_title' => $secondCampaign->title,
            'created_by' => $user->id,
            'donated_at' => now(),
        ]);
        $otherDonation = Donation::factory()->create([
            'organization_id' => $firstCampaign->organization_id,
            'campaign_id' => $firstCampaign->id,
            'campaign_title' => $firstCampaign->title,
            'created_by' => $otherUser->id,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/mobile/me/donations?perPage=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.perPage', 1)
            ->assertJsonPath('data.0.id', (string) $secondDonation->id);

        $this->getJson("/api/mobile/me/donations?campaignId={$firstCampaign->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $firstDonation->id);

        $this->getJson("/api/mobile/me/donations/{$firstDonation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', (string) $firstDonation->id);

        $this->getJson("/api/mobile/me/donations/{$otherDonation->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_donation_endpoints_require_authentication(): void
    {
        $campaign = $this->createCampaign();

        $this->postJson("/api/mobile/campaigns/{$campaign->id}/donations", [
            'amount' => 25,
            'paymentMethod' => 'cash',
        ])->assertUnauthorized();
        $this->getJson('/api/mobile/me/donations')->assertUnauthorized();
        $this->getJson('/api/mobile/me/donations/1')->assertUnauthorized();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCampaign(array $overrides = []): Campaign
    {
        $organization = Organization::factory()->create();

        return Campaign::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'title' => 'Active Donation Campaign',
            'summary' => 'Campaign accepting mobile contributions.',
            'category' => 'emergency',
            'status' => 'active',
            'location' => 'Amman',
            'organization_id' => $organization->id,
            'goal_amount' => 1000,
            'raised_amount' => 0,
            'donors_count' => 0,
        ], $overrides));
    }
}
