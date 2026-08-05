<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_registration_issues_token_with_mobile_envelope(): void
    {
        $response = $this->postJson('/api/mobile/auth/register', [
            'name' => 'Mobile Register User',
            'email' => 'register@example.com',
            'phone' => '+962790000111',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Registered successfully.');
        $response->assertJsonPath('data.tokenType', 'Bearer');
        $response->assertJsonPath('data.user.email', 'register@example.com');
        $this->assertNotEmpty($response->json('data.token'));
        $this->assertDatabaseHas('users', [
            'email' => 'register@example.com',
            'phone' => '+962790000111',
        ]);
    }

    public function test_mobile_login_issues_token_with_mobile_envelope(): void
    {
        $user = User::factory()->create([
            'email' => 'mobile@example.com',
            'phone' => '+962790000222',
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

    public function test_mobile_login_accepts_phone_credentials(): void
    {
        $user = User::factory()->create([
            'phone' => '+962790000333',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/mobile/auth/login', [
            'phone' => '+962790000333',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.user.id', $user->id);
        $this->assertNotEmpty($response->json('data.token'));
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

    public function test_mobile_forgot_password_generates_reset_code_for_email_or_phone_login(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'phone' => '+962790000444',
        ]);

        $response = $this->postJson('/api/mobile/auth/forgot-password', [
            'login' => '+962790000444',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.resetCodeSent', true);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_mobile_verify_reset_code_accepts_the_existing_password_reset_token_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'verify@example.com',
            'password' => Hash::make('password'),
        ]);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => '123456', 'created_at' => now()]
        );

        $response = $this->postJson('/api/mobile/auth/verify-reset-code', [
            'login' => 'verify@example.com',
            'code' => '123456',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.resetCodeVerified', true);
    }

    public function test_mobile_reset_password_updates_the_password_and_clears_the_reset_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset-password@example.com',
            'password' => Hash::make('password'),
        ]);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => '654321', 'created_at' => now()]
        );

        $response = $this->postJson('/api/mobile/auth/reset-password', [
            'login' => 'reset-password@example.com',
            'code' => '654321',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.resetPasswordUpdated', true);
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
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
