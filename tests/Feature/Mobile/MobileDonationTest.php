<?php

declare(strict_types=1);

use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('user creates pending donation intent without changing campaign totals', function () {
    $user = User::factory()->create([
        'name' => 'Mobile Donor',
        'email' => 'mobile.donor@example.com',
        'phone' => '+962790000001',
    ]);
    $campaign = mobile_donation_test_createCampaign([
        'raised_amount' => 100,
        'donors_count' => 3,
    ]);
    Sanctum::actingAs($user);

    $response = $this->postJson("/api/mobile/campaigns/{$campaign->id}/donations", [
        'amount' => 25.50,
        'contactMethod' => 'whatsapp',
        'paymentMethod' => 'bank_transfer',
        'city' => 'Amman',
        'notes' => 'Contact me in the evening.',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.campaignId', $campaign->id)
        ->assertJsonPath('data.amount', 25.5)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.contactMethod', 'whatsapp')
        ->assertJsonPath('data.isAnonymous', false);

    $this->assertDatabaseHas('donations', [
        'id' => $response->json('data.id'),
        'campaign_id' => $campaign->id,
        'organization_id' => $campaign->organization_id,
        'amount_or_type' => '25.50',
        'status' => 'pending',
        'contact_method' => 'whatsapp',
        'created_by' => $user->id,
        'is_anonymous' => false,
    ]);

    $campaign->refresh();
    expect($campaign->raised_amount)->toBe('100.00');
    expect($campaign->donors_count)->toBe(3);
});

test('user can create anonymous donation intent without changing campaign totals', function () {
    $user = User::factory()->create();
    $campaign = mobile_donation_test_createCampaign([
        'raised_amount' => 75,
        'donors_count' => 2,
    ]);
    Sanctum::actingAs($user);

    $response = $this->postJson("/api/mobile/campaigns/{$campaign->id}/donations", [
        'amount' => 50,
        'contactMethod' => 'phone',
        'isAnonymous' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.isAnonymous', true)
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('donations', [
        'id' => $response->json('data.id'),
        'created_by' => $user->id,
        'is_anonymous' => true,
    ]);

    $campaign->refresh();
    expect($campaign->raised_amount)->toBe('75.00');
    expect($campaign->donors_count)->toBe(2);
});

test('donation intent validates anonymous flag as boolean', function () {
    $user = User::factory()->create();
    $campaign = mobile_donation_test_createCampaign();
    Sanctum::actingAs($user);

    $this->postJson("/api/mobile/campaigns/{$campaign->id}/donations", [
        'amount' => 25,
        'contactMethod' => 'phone',
        'isAnonymous' => 'yes',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['isAnonymous'], 'error.details');
});

test('donation intent requires active campaign and valid payload', function () {
    $user = User::factory()->create();
    $activeCampaign = mobile_donation_test_createCampaign();
    $closedCampaign = mobile_donation_test_createCampaign(['status' => 'closed']);
    Sanctum::actingAs($user);

    $this->postJson("/api/mobile/campaigns/{$activeCampaign->id}/donations", [
        'amount' => 0,
        'contactMethod' => 'carrier_pigeon',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['amount', 'contactMethod'], 'error.details');

    $this->postJson("/api/mobile/campaigns/{$closedCampaign->id}/donations", [
        'amount' => 25,
        'contactMethod' => 'phone',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['campaign'], 'error.details');
});

test('donation history includes lifecycle status and remains scoped to user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $firstCampaign = mobile_donation_test_createCampaign(['title' => 'First Campaign']);
    $secondCampaign = mobile_donation_test_createCampaign(['title' => 'Second Campaign']);

    $firstDonation = Donation::factory()->create([
        'organization_id' => $firstCampaign->organization_id,
        'campaign_id' => $firstCampaign->id,
        'campaign_title' => $firstCampaign->title,
        'created_by' => $user->id,
        'status' => 'completed',
        'completed_at' => now()->subDay(),
    ]);
    $secondDonation = Donation::factory()->create([
        'organization_id' => $secondCampaign->organization_id,
        'campaign_id' => $secondCampaign->id,
        'campaign_title' => $secondCampaign->title,
        'created_by' => $user->id,
        'status' => 'pending',
    ]);
    $otherDonation = Donation::factory()->create([
        'organization_id' => $firstCampaign->organization_id,
        'campaign_id' => $firstCampaign->id,
        'campaign_title' => $firstCampaign->title,
        'created_by' => $otherUser->id,
        'status' => 'pending',
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/mobile/me/donations?status=pending')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', (string) $secondDonation->id)
        ->assertJsonPath('data.0.status', 'pending');

    $this->getJson("/api/mobile/me/donations?campaignId={$firstCampaign->id}")
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', (string) $firstDonation->id);

    $this->getJson("/api/mobile/me/donations/{$otherDonation->id}")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'not_found');
});

test('donation endpoints require authentication', function () {
    $campaign = mobile_donation_test_createCampaign();

    $this->postJson("/api/mobile/campaigns/{$campaign->id}/donations", [
        'amount' => 25,
        'contactMethod' => 'phone',
    ])->assertUnauthorized();
    $this->getJson('/api/mobile/me/donations')->assertUnauthorized();
    $this->getJson('/api/mobile/me/donations/1')->assertUnauthorized();
});

/** @param array<string,mixed> $overrides */
function mobile_donation_test_createCampaign(array $overrides = []): Campaign
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
