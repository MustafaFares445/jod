<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Campaign;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MobileDonationHistoryContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_contributed_history_returns_mobile_screen_fields(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $campaign = $this->campaign($organization, ['goal_amount' => 500000, 'status' => 'active']);
        $donation = Donation::factory()->create([
            'organization_id' => $organization->id,
            'campaign_id' => $campaign->id,
            'campaign_title' => $campaign->title,
            'amount_or_type' => 25000,
            'created_by' => $user->id,
            'status' => 'pending',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/mobile/me/donations?flow=contributed&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $donation->id)
            ->assertJsonPath('data.0.flow', 'contributed')
            ->assertJsonPath('data.0.donatedAmount', 25000)
            ->assertJsonPath('data.0.targetAmount', 500000)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.organization', $organization->name);
    }

    public function test_received_history_is_scoped_to_users_organization_and_donor_permission(): void
    {
        $organization = Organization::factory()->create();
        $otherOrganization = Organization::factory()->create();
        $staff = User::factory()->create(['organization_id' => $organization->id]);
        $this->grantDonorView($staff);
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
        $this->getJson('/api/mobile/me/donations?flow=received&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $received->id)
            ->assertJsonPath('data.0.flow', 'received');
    }

    public function test_received_history_is_empty_without_donor_permission(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $organization->id]);
        Donation::factory()->create(['organization_id' => $organization->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/mobile/me/donations?flow=received')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_organization_user_can_show_received_donation_with_permission_but_outsider_cannot(): void
    {
        $organization = Organization::factory()->create();
        $staff = User::factory()->create(['organization_id' => $organization->id]);
        $this->grantDonorView($staff);
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
        $this->getJson("/api/mobile/me/donations/{$donation->id}")->assertNotFound();
    }

    public function test_recorded_donation_intent_defaults_city_from_profile(): void
    {
        $user = User::factory()->create(['city' => 'Aleppo']);
        $organization = Organization::factory()->create();
        $campaign = $this->campaign($organization);
        Sanctum::actingAs($user);

        $this->postJson("/api/mobile/campaigns/{$campaign->id}/donations", [
            'amount' => 100,
            'contactMethod' => 'phone',
            'paymentMethod' => 'cash',
        ])
            ->assertOk()
            ->assertJsonPath('data.city', 'Aleppo')
            ->assertJsonPath('data.status', 'pending');
    }

    private function grantDonorView(User $user): void
    {
        $name = PermissionNameResolver::resolve(PermissionGroup::ORG_DONOR, PermissionAction::VIEW);
        Permission::findOrCreate($name, 'web');
        $user->givePermissionTo($name);
    }

    /** @param array<string, mixed> $overrides */
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
