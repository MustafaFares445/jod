<?php

declare(strict_types=1);

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create([
        'name' => 'Contract Admin',
        'email' => 'contract-admin@example.com',
    ]);

    $this->grantPermissions($this->admin, [
        [PermissionGroup::USER, PermissionAction::VIEW],
        [PermissionGroup::USER, PermissionAction::CREATE],
        [PermissionGroup::USER, PermissionAction::UPDATE],
        [PermissionGroup::USER, PermissionAction::DELETE],
        [PermissionGroup::USER, PermissionAction::RESET_PASSWORD],
    ]);

    Sanctum::actingAs($this->admin, [TokenService::ACCESS_ABILITY]);
});

test('admin user list matches dashboard nested filters pagination and response envelope', function () {
    User::factory()->create([
        'name' => 'Dashboard Donor Match',
        'email' => 'donor-match@example.com',
        'user_type' => 'donor',
        'status' => 'active',
    ]);

    User::factory()->create([
        'name' => 'Dashboard Volunteer Other',
        'email' => 'volunteer-other@example.com',
        'user_type' => 'volunteer',
        'status' => 'active',
    ]);

    $response = $this->getJson(
        '/api/v1/admin/users?filter%5Bstatus%5D=active&filter%5BuserType%5D=donor&filter%5Bsearch%5D=Donor%20Match&perPage=10&sort=-createdAt'
    );

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Dashboard Donor Match')
        ->assertJsonPath('data.0.userType', 'donor')
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('message', 'Data retrieved successfully.')
        ->assertJsonStructure([
            'data' => [[
                'id', 'name', 'email', 'phone', 'userType', 'status',
                'organizationId', 'postsCount', 'reportsCount', 'createdAt',
                'updatedAt', 'lastActiveAt',
            ]],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta',
            'message',
        ]);
});

test('admin user mutations match dashboard request props and response contracts', function () {
    $createPayload = [
        'name' => 'Dashboard Managed User',
        'email' => 'managed-user@example.com',
        'phone' => '+962790000010',
        'userType' => 'volunteer',
        'role' => 'volunteer',
        'status' => 'active',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $created = $this->postJson('/api/v1/admin/users', $createPayload)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Dashboard Managed User')
        ->assertJsonPath('data.email', 'managed-user@example.com')
        ->assertJsonPath('data.phone', '+962790000010')
        ->assertJsonPath('data.userType', 'volunteer')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('message', 'Data created successfully.')
        ->assertJsonStructure(['data' => ['id', 'createdAt', 'updatedAt'], 'message']);

    $userId = (string) $created->json('data.id');

    $this->getJson("/api/v1/admin/users/{$userId}")
        ->assertOk()
        ->assertJsonPath('data.id', $userId)
        ->assertJsonPath('message', 'Data retrieved successfully.');

    $this->patchJson("/api/v1/admin/users/{$userId}", [
        'name' => 'Dashboard Managed User Updated',
        'email' => 'managed-user@example.com',
        'phone' => '+962790000011',
        'userType' => 'donor',
        'role' => 'donor',
        'status' => 'active',
    ])->assertOk()
        ->assertJsonPath('data.name', 'Dashboard Managed User Updated')
        ->assertJsonPath('data.phone', '+962790000011')
        ->assertJsonPath('data.userType', 'donor')
        ->assertJsonPath('message', 'Data updated successfully.');

    $this->patchJson("/api/v1/admin/users/{$userId}/status", [
        'status' => 'inactive',
    ])->assertOk()
        ->assertJsonPath('data.status', 'inactive')
        ->assertJsonPath('message', 'Data updated successfully.');

    $this->patchJson("/api/v1/admin/users/{$userId}/password", [
        'newPassword' => 'new-password-123',
        'newPassword_confirmation' => 'new-password-123',
    ])->assertOk()
        ->assertJsonPath('data.id', $userId)
        ->assertJsonPath('message', 'Data updated successfully.');

    $managedUser = User::query()->findOrFail($userId);
    expect(Hash::check('new-password-123', $managedUser->password))->toBeTrue();

    $this->deleteJson("/api/v1/admin/users/{$userId}")
        ->assertOk()
        ->assertJsonPath('message', 'Data deleted successfully.');

    $this->assertSoftDeleted('users', ['id' => $userId]);
});
