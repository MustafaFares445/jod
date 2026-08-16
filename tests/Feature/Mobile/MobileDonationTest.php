<?php

declare(strict_types=1);
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('user can record donation and campaign totals are updated', function () {
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
    expect($campaign->raised_amount)->toBe('125.50');
    expect($campaign->donors_count)->toBe(4);

    $this->postJson("/api/mobile/campaigns/{$campaign->id}/donations", [
        'amount' => 10,
        'paymentMethod' => 'cash',
    ])->assertOk();

    $campaign->refresh();
    expect($campaign->raised_amount)->toBe('135.50');
    expect($campaign->donors_count)->toBe(4);
});
test('donation requires active campaign and valid payload', function () {
    $user = User::factory()->create();
    $activeCampaign = mobile_donation_test_createCampaign();
    $closedCampaign = mobile_donation_test_createCampaign(['status' => 'closed']);
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
});
test('donation history is paginated filterable and scoped to user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $firstCampaign = mobile_donation_test_createCampaign(['title' => 'First Campaign']);
    $secondCampaign = mobile_donation_test_createCampaign(['title' => 'Second Campaign']);

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
});
test('donation endpoints require authentication', function () {
    $campaign = mobile_donation_test_createCampaign();

    $this->postJson("/api/mobile/campaigns/{$campaign->id}/donations", [
        'amount' => 25,
        'paymentMethod' => 'cash',
    ])->assertUnauthorized();
    $this->getJson('/api/mobile/me/donations')->assertUnauthorized();
    $this->getJson('/api/mobile/me/donations/1')->assertUnauthorized();
});
/**
 * @param  array{
 *     id?: string,
 *     title?: string,
 *     summary?: string|null,
 *     category?: string,
 *     status?: string,
 *     location?: string|null,
 *     organization_id?: string,
 *     goal_amount?: int|float|string,
 *     raised_amount?: int|float|string,
 *     donors_count?: int
 * }  $overrides
 */
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
