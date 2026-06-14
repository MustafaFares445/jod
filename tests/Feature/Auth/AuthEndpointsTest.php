<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_a_bearer_token_and_returns_user_data(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'last_active_at' => null,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Logged in successfully');
        $response->assertJsonPath('data.tokenType', 'Bearer');
        $response->assertJsonPath('data.user.id', $user->id);
        $this->assertNotEmpty($response->json('data.token'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'admin@example.com',
        ]);

        $this->assertNotNull($user->fresh()->last_active_at);
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

    public function test_logout_revokes_the_current_access_token(): void
    {
        $user = User::factory()->create();
        $plainTextToken = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->postJson('/api/v1/auth/logout');

        $response->assertOk();
        $response->assertJsonPath('message', 'Logged out successfully');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
        ]);
    }
}
