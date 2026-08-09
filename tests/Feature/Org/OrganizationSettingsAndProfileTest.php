<?php

declare(strict_types=1);

namespace Tests\Feature\Org;

use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrganizationSettingsAndProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_organization_profile_and_bank_account(): void
    {
        [$owner, $organization] = $this->owner();

        $this->actingAs($owner)
            ->patchJson('/api/v1/org/settings/profile', [
                'name' => 'Updated Organization',
                'email' => 'updated-org@example.com',
                'phone' => '+962790000000',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Organization');

        $this->actingAs($owner)
            ->patchJson('/api/v1/org/settings/bank-account', [
                'bankName' => 'JOD Bank',
                'iban' => 'JO94CBJO0010000000000131000302',
            ])
            ->assertOk()
            ->assertJsonPath('data.bankName', 'JOD Bank');

        $this->assertDatabaseHas('organizations', [
            'id' => $organization->id,
            'email' => 'updated-org@example.com',
            'bank_name' => 'JOD Bank',
        ]);
    }

    public function test_authenticated_user_can_update_profile_and_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/me/profile', [
                'name' => 'Updated User',
                'email' => 'updated-user@example.com',
                'phone' => '+962791111111',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated User');

        $this->actingAs($user)
            ->patchJson('/api/v1/me/password', [
                'currentPassword' => 'old-password',
                'newPassword' => 'new-password',
                'newPassword_confirmation' => 'new-password',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_password_change_rejects_invalid_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->actingAs($user)
            ->patchJson('/api/v1/me/password', [
                'currentPassword' => 'wrong-password',
                'newPassword' => 'new-password',
                'newPassword_confirmation' => 'new-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currentPassword');
    }

    /** @return array{User, Organization} */
    private function owner(): array
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create(['organization_id' => $organization->id]);
        $role = OrganizationRole::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
            'is_system' => true,
        ]);

        OrganizationStaff::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'organization_role_id' => $role->id,
            'status' => 'active',
        ]);

        return [$owner, $organization];
    }
}
