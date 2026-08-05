<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_login_issues_token_with_mobile_envelope(): void
    {
        $user = User::factory()->create([
            'email' => 'mobile@example.com',
            'password' => Hash::make('password'),
            'last_active_at' => null,
        ]);

        $response = $this->postJson('/api/mobile/auth/login', [
            'email' => 'mobile@example.com',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Logged in successfully.');
        $response->assertJsonPath('data.tokenType', 'Bearer');
        $response->assertJsonPath('data.user.id', $user->id);
        $response->assertJsonPath('error', null);
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotNull($user->fresh()->last_active_at);
    }

    public function test_mobile_login_rejects_invalid_credentials_with_mobile_error_envelope(): void
    {
        User::factory()->create([
            'email' => 'mobile@example.com',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/mobile/auth/login', [
            'email' => 'mobile@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('data', null);
        $response->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_mobile_logout_revokes_current_token(): void
    {
        $loginResponse = $this->postJson('/api/mobile/auth/login', [
            'email' => User::factory()->create([
                'password' => Hash::make('password'),
            ])->email,
            'password' => 'password',
        ]);

        $plainTextToken = $loginResponse->json('data.token');
        $tokenId = explode('|', $plainTextToken, 2)[0];

        $response = $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->postJson('/api/mobile/auth/logout');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Logged out successfully.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenId,
        ]);
    }
}
