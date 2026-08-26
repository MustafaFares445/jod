<?php

declare(strict_types=1);

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Support\Permissions\PermissionNameResolver;
use Database\Seeders\Permissions\PermissionsSeeder;
use Illuminate\Support\Facades\Hash;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

if (! function_exists('authLoginPayload')) {
    function authLoginPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'admin@example.com',
            'password' => 'password',
            'userType' => 'admin',
        ], $overrides);
    }
}

test('login issues access and refresh tokens and returns dashboard permissions', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'admin',
        'last_active_at' => null,
    ]);

    $this->grantPermissions($user, [
        [PermissionGroup::DASHBOARD, PermissionAction::VIEW],
    ]);

    $response = $this->postJson('/api/v1/auth/login', authLoginPayload([
        'email' => 'admin@example.com',
        'password' => 'password',
        'userType' => 'admin',
    ]));

    $response->assertOk();
    $response->assertJsonPath('message', 'Logged in successfully');
    $response->assertJsonPath('data.tokenType', 'Bearer');
    $response->assertJsonPath('data.expiresIn', 3600);
    $response->assertJsonPath('data.refreshExpiresIn', 2592000);
    $response->assertJsonPath('data.user.id', $user->id);
    expect($response->json('data.permissions.flat')['dashboard.view'])->toBeTrue();
    $response->assertJsonPath('data.permissions.granted.0', 'dashboard.view');
    expect($response->json('data.token'))->not->toBeEmpty();
    expect($response->json('data.refreshToken'))->not->toBeEmpty();
    expect($response->json('data.token'))->toMatch('/^[A-Za-z0-9\|]+$/');
    expect($response->json('data.refreshToken'))->toMatch('/^[A-Za-z0-9\|]+$/');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'email' => 'admin@example.com',
    ]);
    $this->assertDatabaseCount('personal_access_tokens', 2);

    expect($user->fresh()->last_active_at)->not->toBeNull();

    $meResponse = $this->withHeader('Authorization', 'Bearer '.$response->json('data.token'))
        ->getJson('/api/v1/me');

    $meResponse->assertOk();
    $meResponse->assertJsonPath('message', 'Data retrieved successfully.');
    $meResponse->assertJsonPath('data.id', $user->id);
    $meResponse->assertJsonPath('data.email', 'admin@example.com');
});

test('login synchronizes permissions from the active organization role', function () {
    $this->seed(PermissionsSeeder::class);

    $organization = Organization::factory()->create();
    $permissionName = PermissionNameResolver::resolve(
        PermissionGroup::ORG_CAMPAIGN,
        PermissionAction::VIEW,
    );

    $role = OrganizationRole::factory()->create([
        'organization_id' => $organization->id,
        'permissions' => [$permissionName],
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'email' => 'manager@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'general',
        'organization_id' => $organization->id,
    ]);

    OrganizationStaff::factory()->create([
        'organization_id' => $organization->id,
        'organization_role_id' => $role->id,
        'user_id' => $user->id,
        'status' => 'active',
    ]);

    $response = $this->postJson('/api/v1/auth/login', authLoginPayload([
        'email' => 'manager@example.com',
        'password' => 'password',
        'userType' => 'companies',
    ]));

    $response->assertOk();
    expect($response->json('data.permissions.flat')[$permissionName])->toBeTrue();
    expect($user->fresh()->can($permissionName))->toBeTrue();
});

test('dashboard login accepts company user on shared endpoint', function () {
    $this->seed(PermissionsSeeder::class);

    $organization = Organization::factory()->create();
    $user = User::factory()->create([
        'email' => 'company@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'general',
        'organization_id' => $organization->id,
        'status' => 'active',
    ]);

    $response = $this->postJson('/api/v1/auth/login', authLoginPayload([
        'email' => 'company@example.com',
        'password' => 'password',
        'userType' => 'companies',
    ]));

    $response->assertOk();
    $response->assertJsonPath('message', 'Logged in successfully');
    $response->assertJsonPath('data.user.id', $user->id);
    expect($response->json('data.token'))->not->toBeEmpty();
    expect($response->json('data.refreshToken'))->not->toBeEmpty();
});

test('dashboard login rejects inactive users and organizations that are not active and verified', function () {
    $inactiveUser = User::factory()->create([
        'email' => 'inactive@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'admin',
        'status' => 'inactive',
    ]);

    $this->postJson('/api/v1/auth/login', authLoginPayload([
        'email' => $inactiveUser->email,
        'password' => 'password',
        'userType' => 'admin',
    ]))->assertForbidden()->assertJsonPath('message', 'This account is not active.');

    $organization = Organization::factory()->create([
        'status' => 'inactive',
        'verification_status' => 'unverified',
    ]);
    $companyUser = User::factory()->create([
        'email' => 'blocked-company@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'general',
        'organization_id' => $organization->id,
        'status' => 'active',
    ]);

    $this->postJson('/api/v1/auth/login', authLoginPayload([
        'email' => $companyUser->email,
        'password' => 'password',
        'userType' => 'companies',
    ]))->assertForbidden()->assertJsonPath('message', 'This organization account must be active and verified before login.');
});

test('dashboard login rejects cross type requests', function () {
    $organization = Organization::factory()->create();

    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'admin',
        'organization_id' => null,
    ]);

    User::factory()->create([
        'email' => 'company@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'general',
        'organization_id' => $organization->id,
    ]);

    $this->postJson('/api/v1/auth/login', authLoginPayload([
        'email' => 'admin@example.com',
        'password' => 'password',
        'userType' => 'companies',
    ]))
        ->assertUnauthorized()
        ->assertJsonPath('message', 'The provided credentials are incorrect.');

    $this->postJson('/api/v1/auth/login', authLoginPayload([
        'email' => 'company@example.com',
        'password' => 'password',
        'userType' => 'admin',
    ]))
        ->assertUnauthorized()
        ->assertJsonPath('message', 'The provided credentials are incorrect.');
});

test('old company login endpoint is not registered', function () {
    $route = collect(app('router')->getRoutes())
        ->first(fn ($route): bool => in_array('POST', $route->methods(), true)
            && $route->uri() === 'api/v1/company/auth/login');

    expect($route)->toBeNull();
});

test('login rejects invalid credentials', function () {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'admin',
    ]);

    $response = $this->postJson('/api/v1/auth/login', authLoginPayload([
        'email' => 'admin@example.com',
        'password' => 'wrong-password',
        'userType' => 'admin',
    ]));

    $response->assertUnauthorized();
    $response->assertJsonPath('message', 'The provided credentials are incorrect.');
});

test('login rejects invalid payloads', function (array $payload, string $expectedField) {
    $response = $this->postJson('/api/v1/auth/login', $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors([$expectedField]);
})->with('provideInvalidLoginPayloads');

test('refresh rotates the token pair and revokes the previous tokens', function () {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'admin',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', authLoginPayload([
        'email' => 'admin@example.com',
        'password' => 'password',
        'userType' => 'admin',
    ]));

    $oldAccessToken = $loginResponse->json('data.token');
    $oldRefreshToken = $loginResponse->json('data.refreshToken');
    $oldAccessTokenId = explode('|', $oldAccessToken, 2)[0];
    $oldRefreshTokenId = explode('|', $oldRefreshToken, 2)[0];

    $response = $this->postJson('/api/v1/auth/refresh', [
        'refreshToken' => $oldRefreshToken,
    ]);

    $response->assertOk();
    $response->assertJsonPath('message', 'Token refreshed successfully');
    $response->assertJsonPath('data.tokenType', 'Bearer');
    $this->assertNotSame($oldAccessToken, $response->json('data.token'));
    $this->assertNotSame($oldRefreshToken, $response->json('data.refreshToken'));

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldAccessTokenId]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldRefreshTokenId]);
    $this->assertDatabaseCount('personal_access_tokens', 2);

    $this->withHeader('Authorization', 'Bearer '.$oldAccessToken)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();

    $this->withHeader('Authorization', 'Bearer '.$response->json('data.token'))
        ->getJson('/api/v1/me')
        ->assertOk();
});

test('refresh token cannot access protected api routes', function () {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'admin',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', authLoginPayload([
        'email' => 'admin@example.com',
        'password' => 'password',
        'userType' => 'admin',
    ]));

    $this->withHeader('Authorization', 'Bearer '.$loginResponse->json('data.refreshToken'))
        ->getJson('/api/v1/me')
        ->assertForbidden()
        ->assertJsonPath('message', 'An access token is required.');
});

test('refresh rejects invalid or expired tokens', function () {
    $this->postJson('/api/v1/auth/refresh', [
        'refreshToken' => 'invalid-token',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'The refresh token is invalid or expired.');

    $user = User::factory()->create();
    $expiredRefreshToken = $user->createToken(
        'refresh-token:expired-session',
        [TokenService::REFRESH_ABILITY],
        now()->subMinute(),
    )->plainTextToken;

    $this->postJson('/api/v1/auth/refresh', [
        'refreshToken' => $expiredRefreshToken,
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'The refresh token is invalid or expired.');
});

test('refresh validates the request payload', function () {
    $this->postJson('/api/v1/auth/refresh')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['refreshToken']);
});

test('logout revokes the current access and refresh token pair', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'admin',
    ]);

    $loginResponse = $this->postJson('/api/v1/auth/login', authLoginPayload([
        'email' => 'admin@example.com',
        'password' => 'password',
        'userType' => 'admin',
    ]));

    $plainTextToken = $loginResponse->json('data.token');
    $tokenId = explode('|', $plainTextToken, 2)[0];

    $response = $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
        ->postJson('/api/v1/auth/logout');

    $response->assertOk();
    $response->assertJsonPath('message', 'Logged out successfully');

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $tokenId,
        'tokenable_type' => User::class,
        'tokenable_id' => $user->id,
    ]);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

dataset('provideInvalidLoginPayloads', function () {
    return [
        'missing email' => [
            ['password' => 'password', 'userType' => 'admin'],
            'email',
        ],
        'invalid email format' => [
            ['email' => 'not-an-email', 'password' => 'password', 'userType' => 'admin'],
            'email',
        ],
        'short password' => [
            ['email' => 'admin@example.com', 'password' => 'short', 'userType' => 'admin'],
            'password',
        ],
        'missing user type' => [
            ['email' => 'admin@example.com', 'password' => 'password'],
            'userType',
        ],
        'invalid user type' => [
            ['email' => 'admin@example.com', 'password' => 'password', 'userType' => 'company'],
            'userType',
        ],
    ];
});
