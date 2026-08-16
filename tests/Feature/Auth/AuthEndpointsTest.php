<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Support\Permissions\PermissionNameResolver;
use Database\Seeders\Permissions\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_access_and_refresh_tokens_and_returns_dashboard_permissions(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'user_type' => 'admin',
            'last_active_at' => null,
        ]);

        $this->grantPermissions($user, [
            [PermissionGroup::DASHBOARD, PermissionAction::VIEW],
        ]);

        $response = $this->postJson('/api/v1/auth/login', $this->loginPayload([
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
        $this->assertTrue($response->json('data.permissions.flat')['dashboard.view']);
        $response->assertJsonPath('data.permissions.granted.0', 'dashboard.view');
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotEmpty($response->json('data.refreshToken'));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\|]+$/', $response->json('data.token'));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\|]+$/', $response->json('data.refreshToken'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'admin@example.com',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->assertNotNull($user->fresh()->last_active_at);

        $meResponse = $this->withHeader('Authorization', 'Bearer '.$response->json('data.token'))
            ->getJson('/api/v1/me');

        $meResponse->assertOk();
        $meResponse->assertJsonPath('message', 'Data retrieved successfully.');
        $meResponse->assertJsonPath('data.id', $user->id);
        $meResponse->assertJsonPath('data.email', 'admin@example.com');
    }

    public function test_login_synchronizes_permissions_from_the_active_organization_role(): void
    {
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

        $response = $this->postJson('/api/v1/auth/login', $this->loginPayload([
            'email' => 'manager@example.com',
            'password' => 'password',
            'userType' => 'companies',
        ]));

        $response->assertOk();
        $this->assertTrue($response->json('data.permissions.flat')[$permissionName]);
        $this->assertTrue($user->fresh()->can($permissionName));
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'user_type' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/auth/login', $this->loginPayload([
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
            'userType' => 'admin',
        ]));

        $response->assertUnauthorized();
        $response->assertJsonPath('message', 'The provided credentials are incorrect.');
    }

    public function test_dashboard_login_accepts_company_user_on_shared_endpoint(): void
    {
        $this->seed(PermissionsSeeder::class);

        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'email' => 'company@example.com',
            'password' => Hash::make('password'),
            'user_type' => 'general',
            'organization_id' => $organization->id,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/login', $this->loginPayload([
            'email' => 'company@example.com',
            'password' => 'password',
            'userType' => 'companies',
        ]));

        $response->assertOk();
        $response->assertJsonPath('message', 'Logged in successfully');
        $response->assertJsonPath('data.user.id', $user->id);
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotEmpty($response->json('data.refreshToken'));
    }

    public function test_dashboard_login_rejects_cross_type_requests(): void
    {
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

        $this->postJson('/api/v1/auth/login', $this->loginPayload([
            'email' => 'admin@example.com',
            'password' => 'password',
            'userType' => 'companies',
        ]))
            ->assertUnauthorized()
            ->assertJsonPath('message', 'The provided credentials are incorrect.');

        $this->postJson('/api/v1/auth/login', $this->loginPayload([
            'email' => 'company@example.com',
            'password' => 'password',
            'userType' => 'admin',
        ]))
            ->assertUnauthorized()
            ->assertJsonPath('message', 'The provided credentials are incorrect.');
    }

    public function test_old_company_login_endpoint_is_not_registered(): void
    {
        $route = collect(app('router')->getRoutes())
            ->first(fn ($route): bool => in_array('POST', $route->methods(), true)
                && $route->uri() === 'api/v1/company/auth/login');

        $this->assertNull($route);
    }

    #[DataProvider('provideInvalidLoginPayloads')]
    public function test_login_rejects_invalid_payloads(array $payload, string $expectedField): void
    {
        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([$expectedField]);
    }

    public function test_refresh_rotates_the_token_pair_and_revokes_the_previous_tokens(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'user_type' => 'admin',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', $this->loginPayload([
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
    }

    public function test_refresh_token_cannot_access_protected_api_routes(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'user_type' => 'admin',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', $this->loginPayload([
            'email' => 'admin@example.com',
            'password' => 'password',
            'userType' => 'admin',
        ]));

        $this->withHeader('Authorization', 'Bearer '.$loginResponse->json('data.refreshToken'))
            ->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'An access token is required.');
    }

    public function test_refresh_rejects_invalid_or_expired_tokens(): void
    {
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
    }

    public function test_refresh_validates_the_request_payload(): void
    {
        $this->postJson('/api/v1/auth/refresh')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['refreshToken']);
    }

    public function test_logout_revokes_the_current_access_and_refresh_token_pair(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'user_type' => 'admin',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', $this->loginPayload([
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
    }

    public static function provideInvalidLoginPayloads(): array
    {
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
    }

    private function loginPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'admin@example.com',
            'password' => 'password',
            'userType' => 'admin',
        ], $overrides);
    }
}
