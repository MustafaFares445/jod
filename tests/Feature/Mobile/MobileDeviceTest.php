<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileDeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_mobile_device_without_echoing_push_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

        $response = $this->putJson('/api/mobile/me/devices', [
            'pushToken' => 'push-token-1',
            'platform' => 'android',
            'deviceId' => 'installation-1',
            'appVersion' => '1.4.0',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Mobile device registered successfully.')
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.deviceId', 'installation-1')
            ->assertJsonPath('data.appVersion', '1.4.0')
            ->assertJsonMissingPath('data.pushToken');

        $this->assertNotEmpty($response->json('data.id'));
        $this->assertDatabaseHas('mobile_devices', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
            'push_token' => 'push-token-1',
            'platform' => 'android',
            'device_id' => 'installation-1',
            'app_version' => '1.4.0',
        ]);
    }

    public function test_registration_updates_existing_installation_when_push_token_rotates(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

        $first = $this->putJson('/api/mobile/me/devices', [
            'pushToken' => 'push-token-old',
            'platform' => 'ios',
            'deviceId' => 'installation-rotate',
            'appVersion' => '2.0.0',
        ])->assertOk();

        $second = $this->putJson('/api/mobile/me/devices', [
            'pushToken' => 'push-token-new',
            'platform' => 'ios',
            'deviceId' => 'installation-rotate',
            'appVersion' => '2.1.0',
        ])->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('mobile_devices', 1);
        $this->assertDatabaseHas('mobile_devices', [
            'id' => $first->json('data.id'),
            'push_token' => 'push-token-new',
            'app_version' => '2.1.0',
        ]);
        $this->assertDatabaseMissing('mobile_devices', [
            'push_token' => 'push-token-old',
        ]);
    }

    public function test_user_can_unregister_only_their_own_mobile_device(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($owner, [TokenService::ACCESS_ABILITY]);

        $registration = $this->putJson('/api/mobile/me/devices', [
            'pushToken' => 'push-token-owned',
            'platform' => 'android',
            'deviceId' => 'installation-owned',
        ])->assertOk();
        $deviceId = $registration->json('data.id');

        Sanctum::actingAs($otherUser, [TokenService::ACCESS_ABILITY]);
        $this->deleteJson("/api/mobile/me/devices/{$deviceId}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
        $this->assertDatabaseHas('mobile_devices', ['id' => $deviceId]);

        Sanctum::actingAs($owner, [TokenService::ACCESS_ABILITY]);
        $this->deleteJson("/api/mobile/me/devices/{$deviceId}")
            ->assertOk()
            ->assertJsonPath('data', null);
        $this->assertDatabaseMissing('mobile_devices', ['id' => $deviceId]);
    }

    public function test_mobile_device_registration_validates_payload(): void
    {
        Sanctum::actingAs(User::factory()->create(), [TokenService::ACCESS_ABILITY]);

        $this->putJson('/api/mobile/me/devices', [
            'pushToken' => '',
            'platform' => 'windows-phone',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['pushToken', 'platform'], 'error.details');
    }

    public function test_mobile_device_endpoints_require_authentication(): void
    {
        $this->putJson('/api/mobile/me/devices', [
            'pushToken' => 'push-token-1',
            'platform' => 'android',
        ])->assertUnauthorized();

        $this->deleteJson('/api/mobile/me/devices/device-1')->assertUnauthorized();
    }
}
