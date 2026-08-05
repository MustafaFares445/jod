<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_mobile_endpoint_returns_mobile_error_envelope_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/mobile/me');

        $response->assertUnauthorized();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('data', null);
        $response->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_mobile_profile_includes_loaded_organization_without_sensitive_fields(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Relief Org',
            'email' => 'relief@example.com',
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/mobile/me');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('data.organization.id', $organization->id);
        $response->assertJsonMissingPath('data.password');
    }

    public function test_mobile_profile_can_be_updated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/mobile/me/profile', [
            'name' => 'Updated Mobile User',
            'email' => 'updated-mobile@example.com',
            'phone' => '+962790000000',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Updated Mobile User');
        $response->assertJsonPath('data.email', 'updated-mobile@example.com');
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'updated-mobile@example.com',
        ]);
    }

    public function test_mobile_profile_phone_can_be_cleared(): void
    {
        $user = User::factory()->create(['phone' => '+962790000000']);
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/mobile/me/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => null,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.phone', null);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => null,
        ]);
    }

    public function test_mobile_profile_validation_uses_mobile_error_envelope(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->patchJson('/api/mobile/me/profile', [
            'name' => '',
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'validation_error');
        $response->assertJsonValidationErrors(['name', 'email'], 'error.details');
    }

    public function test_mobile_profile_validation_keeps_existing_validation_error_code(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->patchJson('/api/mobile/me/profile', [
            'name' => '',
            'email' => 'invalid-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'validation_error');
    }
}
