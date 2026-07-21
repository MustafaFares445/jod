<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Organization;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
use App\Support\Permissions\PermissionNameResolver;
use Database\Seeders\Permissions\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_a_bearer_token_and_returns_dashboard_permissions(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'last_active_at' => null,
        ]);

        $this->grantPermissions($user, [
            [PermissionGroup::DASHBOARD, PermissionAction::VIEW],
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Logged in successfully');
        $response->assertJsonPath('data.tokenType', 'Bearer');
        $response->assertJsonPath('data.user.id', $user->id);
        $this->assertTrue($response->json('data.permissions.flat')['dashboard.view']);
        $response->assertJsonPath('data.permissions.granted.0', 'dashboard.view');
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\|]+$/', $response->json('data.token'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'admin@example.com',
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
        ]);

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

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'manager@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('data.permissions.flat')[$permissionName]);
        $this->assertTrue($user->fresh()->can($permissionName));
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized();
        $response->assertJsonPath('message', 'The provided credentials are incorrect.');
    }

    #[DataProvider('provideInvalidLoginPayloads')]
    public function test_login_rejects_invalid_payloads(array $payload, string $expectedField): void
    {
        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([$expectedField]);
    }

    public function test_logout_revokes_the_current_access_token(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

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
    }

    public static function provideInvalidLoginPayloads(): array
    {
        return [
            'missing email' => [
                ['password' => 'password'],
                'email',
            ],
            'invalid email format' => [
                ['email' => 'not-an-email', 'password' => 'password'],
                'email',
            ],
            'short password' => [
                ['email' => 'admin@example.com', 'password' => 'short'],
                'password',
            ],
        ];
    }
}
