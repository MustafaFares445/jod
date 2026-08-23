<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admin can update own name email and phone through me profile endpoint', function () {
    $admin = User::factory()->create([
        'user_type' => 'admin',
        'name' => 'Old Admin Name',
        'email' => 'old-admin@example.com',
        'phone' => '+963900000001',
    ]);

    Sanctum::actingAs($admin);

    $this->patchJson('/api/v1/me/profile', [
        'name' => 'Updated Admin Name',
        'email' => 'updated-admin@example.com',
        'phone' => '+963900000002',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id)
        ->assertJsonPath('data.name', 'Updated Admin Name')
        ->assertJsonPath('data.email', 'updated-admin@example.com')
        ->assertJsonPath('data.phone', '+963900000002')
        ->assertJsonPath('data.userType', 'admin');

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
        'name' => 'Updated Admin Name',
        'email' => 'updated-admin@example.com',
        'phone' => '+963900000002',
    ]);
});

test('admin can update own password through me password endpoint', function () {
    $admin = User::factory()->create([
        'user_type' => 'admin',
        'password' => Hash::make('old-password-123'),
    ]);

    Sanctum::actingAs($admin);

    $this->patchJson('/api/v1/me/password', [
        'currentPassword' => 'old-password-123',
        'newPassword' => 'new-password-456',
        'newPassword_confirmation' => 'new-password-456',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id)
        ->assertJsonPath('data.userType', 'admin')
        ->assertJsonPath('message', 'Password updated successfully.');

    expect(Hash::check('new-password-456', $admin->fresh()->password))->toBeTrue();
});

test('admin password update requires the current password', function () {
    $admin = User::factory()->create([
        'user_type' => 'admin',
        'password' => Hash::make('old-password-123'),
    ]);

    Sanctum::actingAs($admin);

    $this->patchJson('/api/v1/me/password', [
        'currentPassword' => 'wrong-password',
        'newPassword' => 'new-password-456',
        'newPassword_confirmation' => 'new-password-456',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('currentPassword');

    expect(Hash::check('old-password-123', $admin->fresh()->password))->toBeTrue();
});
