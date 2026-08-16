<?php

declare(strict_types=1);
use App\Models\User;
use App\Services\Auth\TokenService;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('user can register mobile device without echoing push token', function () {
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
        ->assertJsonPath('data.pushTargetType', 'token')
        ->assertJsonPath('data.platform', 'android')
        ->assertJsonPath('data.deviceId', 'installation-1')
        ->assertJsonPath('data.appVersion', '1.4.0')
        ->assertJsonMissingPath('data.pushToken');

    expect($response->json('data.id'))->not->toBeEmpty();
    $this->assertDatabaseHas('mobile_devices', [
        'id' => $response->json('data.id'),
        'user_id' => $user->id,
        'push_token' => 'push-token-1',
        'push_target_type' => 'token',
        'platform' => 'android',
        'device_id' => 'installation-1',
        'app_version' => '1.4.0',
    ]);
});
test('user can register firebase installation id target', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $response = $this->putJson('/api/mobile/me/devices', [
        'pushToken' => 'firebase-installation-id',
        'pushTargetType' => 'fid',
        'platform' => 'ios',
        'deviceId' => 'installation-fid',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.pushTargetType', 'fid')
        ->assertJsonPath('data.platform', 'ios')
        ->assertJsonMissingPath('data.pushToken');

    $this->assertDatabaseHas('mobile_devices', [
        'id' => $response->json('data.id'),
        'user_id' => $user->id,
        'push_token' => 'firebase-installation-id',
        'push_target_type' => 'fid',
        'platform' => 'ios',
    ]);
});
test('registration updates existing installation when push token rotates', function () {
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

    expect($second->json('data.id'))->toBe($first->json('data.id'));
    $this->assertDatabaseCount('mobile_devices', 1);
    $this->assertDatabaseHas('mobile_devices', [
        'id' => $first->json('data.id'),
        'push_token' => 'push-token-new',
        'push_target_type' => 'token',
        'app_version' => '2.1.0',
    ]);
    $this->assertDatabaseMissing('mobile_devices', [
        'push_token' => 'push-token-old',
    ]);
});
test('user can unregister only their own mobile device', function () {
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
});
test('mobile device registration validates payload', function () {
    Sanctum::actingAs(User::factory()->create(), [TokenService::ACCESS_ABILITY]);

    $this->putJson('/api/mobile/me/devices', [
        'pushToken' => '',
        'pushTargetType' => 'apns-token',
        'platform' => 'windows-phone',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['pushToken', 'pushTargetType', 'platform'], 'error.details');
});
test('mobile device endpoints require authentication', function () {
    $this->putJson('/api/mobile/me/devices', [
        'pushToken' => 'push-token-1',
        'platform' => 'android',
    ])->assertUnauthorized();

    $this->deleteJson('/api/mobile/me/devices/device-1')->assertUnauthorized();
});
