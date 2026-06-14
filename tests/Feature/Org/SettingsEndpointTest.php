<?php

declare(strict_types=1);

namespace Tests\Feature\Org;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsEndpointTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::query()->create([
            'name' => 'Settings Org',
            'email' => 'settings@example.com',
            'bank_name' => 'Initial Bank',
            'iban' => 'JO00TEST0000000000000000000',
        ]);

        $this->user = User::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->grantPermissions($this->user, [
            [PermissionGroup::ORG_SETTINGS, PermissionAction::VIEW],
            [PermissionGroup::ORG_SETTINGS, PermissionAction::UPDATE],
        ]);

        Sanctum::actingAs($this->user);
    }

    public function test_returns_organization_profile_and_bank_account(): void
    {
        $this->getJson('/api/v1/org/settings/profile')
            ->assertOk()
            ->assertJsonPath('data.name', 'Settings Org');

        $this->getJson('/api/v1/org/settings/bank-account')
            ->assertOk()
            ->assertJsonPath('data.bankName', 'Initial Bank');
    }

    public function test_updates_organization_profile_and_bank_account(): void
    {
        $this->patchJson('/api/v1/org/settings/profile', [
            'name' => 'Updated Settings Org',
            'email' => 'updated-settings@example.com',
            'phone' => '+962790000010',
        ])->assertOk()->assertJsonPath('data.name', 'Updated Settings Org');

        $this->patchJson('/api/v1/org/settings/bank-account', [
            'bankName' => 'Updated Bank',
            'iban' => 'JO94CBJO0010000000000131000302',
        ])->assertOk()->assertJsonPath('data.bankName', 'Updated Bank');

        $this->assertDatabaseHas('organizations', [
            'id' => $this->organization->id,
            'bank_name' => 'Updated Bank',
            'iban' => 'JO94CBJO0010000000000131000302',
        ]);
    }
}
