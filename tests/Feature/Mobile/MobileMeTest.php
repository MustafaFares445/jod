<?php

declare(strict_types=1);
use App\Models\Organization;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('protected mobile endpoint returns mobile error envelope when unauthenticated', function () {
    $response = $this->getJson('/api/mobile/me');

    $response->assertUnauthorized();
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('data', null);
    $response->assertJsonPath('error.code', 'unauthenticated');
});
test('mobile profile includes loaded organization without sensitive fields', function () {
    $organization = Organization::query()->create([
        'name' => 'Relief Org',
        'email' => 'relief@example.com',
    ]);
    $user = User::factory()->create(['organization_id' => $organization->id]);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $response = $this->getJson('/api/mobile/me');

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.id', $user->id);
    $response->assertJsonPath('data.organization.id', $organization->id);
    $response->assertJsonMissingPath('data.password');
});
test('mobile profile can be updated', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

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
});
test('mobile profile phone can be cleared', function () {
    $user = User::factory()->create(['phone' => '+962790000000']);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

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
});
test('mobile password can be changed with sanctum authentication', function () {
    $user = User::factory()->create([
        'password' => bcrypt('current-password'),
    ]);
    Sanctum::actingAs($user, [TokenService::ACCESS_ABILITY]);

    $response = $this->patchJson('/api/mobile/me/change-password', [
        'currentPassword' => 'current-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('data.passwordChanged', true);
    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});
test('mobile profile validation uses mobile error envelope', function () {
    Sanctum::actingAs(User::factory()->create(), [TokenService::ACCESS_ABILITY]);

    $response = $this->patchJson('/api/mobile/me/profile', [
        'name' => '',
        'email' => 'not-an-email',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('success', false);
    $response->assertJsonPath('error.code', 'validation_error');
    $response->assertJsonValidationErrors(['name', 'email'], 'error.details');
});
test('mobile profile validation keeps existing validation error code', function () {
    Sanctum::actingAs(User::factory()->create(), [TokenService::ACCESS_ABILITY]);

    $response = $this->patchJson('/api/mobile/me/profile', [
        'name' => '',
        'email' => 'invalid-email',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'validation_error');
});
