<?php

declare(strict_types=1);
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\PlatformSetting;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->grantPermissions($this->user, [
        [PermissionGroup::PLATFORM_SETTINGS, PermissionAction::VIEW],
        [PermissionGroup::PLATFORM_SETTINGS, PermissionAction::UPDATE],
    ]);
    Sanctum::actingAs($this->user);
    PlatformSetting::truncate();
});
test('returns platform settings', function () {
    PlatformSetting::create(['key' => 'siteName', 'value' => json_encode('Test Site')]);
    PlatformSetting::create(['key' => 'allowNewPosts', 'value' => json_encode(true)]);

    $response = $this->getJson('/api/v1/admin/platform-settings');

    $response->assertOk();
    expect($response->json('data.siteName'))->toEqual('Test Site');
    expect($response->json('data.allowNewPosts'))->toBeTrue();
});
test('returns empty settings with defaults', function () {
    $response = $this->getJson('/api/v1/admin/platform-settings');

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveKey('siteName');
    expect($data)->toHaveKey('allowNewPosts');
    expect($data)->toHaveKey('requirePostReview');
});
test('updates platform settings', function () {
    $payload = [
        'siteName' => 'Updated Site',
        'allowNewPosts' => false,
        'requirePostReview' => true,
    ];

    $response = $this->patchJson('/api/v1/admin/platform-settings', $payload);

    $response->assertOk();
    expect($response->json('data.siteName'))->toEqual('Updated Site');
    expect($response->json('data.allowNewPosts'))->toBeFalse();
    expect($response->json('data.requirePostReview'))->toBeTrue();

    $this->assertDatabaseHas('platform_settings', [
        'key' => 'siteName',
        'value' => json_encode('Updated Site'),
    ]);
});
test('updates partial settings', function () {
    PlatformSetting::create(['key' => 'siteName', 'value' => json_encode('Original Site')]);

    $response = $this->patchJson('/api/v1/admin/platform-settings', ['allowNewPosts' => false]);

    $response->assertOk();

    $this->assertDatabaseHas('platform_settings', [
        'key' => 'siteName',
        'value' => json_encode('Original Site'),
    ]);

    $this->assertDatabaseHas('platform_settings', [
        'key' => 'allowNewPosts',
        'value' => json_encode(false),
    ]);
});
test('validates settings update', function () {
    $response = $this->patchJson('/api/v1/admin/platform-settings', [
        'siteName' => str_repeat('a', 256),
    ]);

    $response->assertUnprocessable();
});
