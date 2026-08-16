<?php

declare(strict_types=1);
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Badge;
use App\Models\User;
use App\Services\Auth\TokenService;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->grantPermissions($this->user, [
        [PermissionGroup::BADGE, PermissionAction::VIEW],
        [PermissionGroup::BADGE, PermissionAction::CREATE],
        [PermissionGroup::BADGE, PermissionAction::UPDATE],
        [PermissionGroup::BADGE, PermissionAction::DELETE],
    ]);
    Sanctum::actingAs($this->user, [TokenService::ACCESS_ABILITY]);
});
test('lists badges', function () {
    Badge::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/admin/badges');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});
test('creates a badge', function () {
    $payload = [
        'name' => 'Test Badge',
        'description' => 'This is a test badge',
        'criteria' => 'Complete 10 posts',
        'iconName' => 'star',
        'isActive' => true,
    ];

    $response = $this->postJson('/api/v1/admin/badges', $payload);

    $response->assertCreated();
    expect($response->json('data.name'))->toEqual('Test Badge');
    expect($response->json('data.isActive'))->toBeTrue();
    $this->assertDatabaseHas('badges', ['name' => 'Test Badge']);
});
test('shows a single badge', function () {
    $badge = Badge::factory()->create();

    $response = $this->getJson("/api/v1/admin/badges/{$badge->id}");

    $response->assertOk();
    expect($response->json('data.id'))->toEqual($badge->id);
    expect($response->json('data.name'))->toEqual($badge->name);
});
test('updates a badge', function () {
    $badge = Badge::factory()->create();

    $payload = [
        'name' => 'Updated Badge',
        'description' => 'Updated description',
        'criteria' => 'Updated criteria',
        'iconName' => 'heart',
        'isActive' => false,
    ];

    $response = $this->patchJson("/api/v1/admin/badges/{$badge->id}", $payload);

    $response->assertOk();
    expect($response->json('data.name'))->toEqual('Updated Badge');
    expect($response->json('data.isActive'))->toBeFalse();
});
test('updates badge status', function () {
    $badge = Badge::factory()->create(['is_active' => true]);

    $response = $this->patchJson("/api/v1/admin/badges/{$badge->id}/status", ['isActive' => false]);

    $response->assertOk();
    expect($response->json('data.isActive'))->toBeFalse();
});
test('deletes a badge', function () {
    $badge = Badge::factory()->create();

    $response = $this->deleteJson("/api/v1/admin/badges/{$badge->id}");

    $response->assertOk()->assertJsonPath('message', 'Data deleted successfully.');
    $this->assertSoftDeleted('badges', ['id' => $badge->id]);
});
test('filters badges by search', function () {
    Badge::factory()->create(['name' => 'Popular Badge']);
    Badge::factory()->create(['name' => 'Rare Badge']);

    $response = $this->getJson('/api/v1/admin/badges?filter.search=Popular');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toEqual('Popular Badge');
});
test('filters badges by active status', function () {
    Badge::factory()->create(['is_active' => true]);
    Badge::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/v1/admin/badges?filter.isActive=true');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.isActive'))->toBeTrue();
});
