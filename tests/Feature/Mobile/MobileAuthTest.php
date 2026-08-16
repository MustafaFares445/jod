<?php

declare(strict_types=1);
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('mobile registration issues rotating token pair with mobile envelope', function () {
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
    expect($response->json('data.token'))->not->toBeEmpty();
    expect($response->json('data.refreshToken'))->not->toBeEmpty();
    expect($response->json('data.expiresIn'))->toBeInt();
    expect($response->json('data.refreshExpiresIn'))->toBeInt();

    $accessToken = PersonalAccessToken::findToken($response->json('data.token'));
    $refreshToken = PersonalAccessToken::findToken($response->json('data.refreshToken'));
    expect($accessToken?->can(TokenService::ACCESS_ABILITY) ?? false)->toBeTrue();
    expect($refreshToken?->can(TokenService::REFRESH_ABILITY) ?? false)->toBeTrue();

    $this->assertDatabaseHas('users', [
        'email' => 'register@example.com',
        'phone' => '+962790000111',
    ]);
});
test('mobile login issues rotating token pair with mobile envelope', function () {
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
    expect($response->json('data.token'))->not->toBeEmpty();
    expect($response->json('data.refreshToken'))->not->toBeEmpty();
    expect($user->fresh()->last_active_at)->not->toBeNull();
});
test('mobile login accepts phone credentials', function () {
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
    expect($response->json('data.token'))->not->toBeEmpty();
    expect($response->json('data.refreshToken'))->not->toBeEmpty();
});
test('mobile login rejects invalid credentials with mobile error envelope', function () {
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
});
test('mobile refresh rotates once and revokes previous session pair', function () {
    $user = User::factory()->create([
        'email' => 'rotate@example.com',
        'password' => Hash::make('password'),
    ]);
    $login = $this->postJson('/api/mobile/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);
    $oldAccessToken = $login->json('data.token');
    $oldRefreshToken = $login->json('data.refreshToken');
    $oldAccessId = explode('|', $oldAccessToken, 2)[0];
    $oldRefreshId = explode('|', $oldRefreshToken, 2)[0];

    $response = $this->postJson('/api/mobile/auth/refresh', [
        'refreshToken' => $oldRefreshToken,
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Token refreshed successfully.')
        ->assertJsonPath('data.tokenType', 'Bearer');
    expect($response->json('data.token'))->not->toBeEmpty();
    expect($response->json('data.refreshToken'))->not->toBeEmpty();
    $this->assertNotSame($oldAccessToken, $response->json('data.token'));
    $this->assertNotSame($oldRefreshToken, $response->json('data.refreshToken'));
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldAccessId]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldRefreshId]);

    $this->postJson('/api/mobile/auth/refresh', [
        'refreshToken' => $oldRefreshToken,
    ])->assertUnauthorized()
        ->assertJsonPath('error.code', 'invalid_refresh_token');
});
test('mobile refresh token cannot access normal authenticated routes', function () {
    $user = User::factory()->create([
        'email' => 'refresh-only@example.com',
        'password' => Hash::make('password'),
    ]);
    $login = $this->postJson('/api/mobile/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$login->json('data.refreshToken'))
        ->getJson('/api/mobile/me')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'access_token_required');
});
test('mobile forgot password generates reset code for email or phone login', function () {
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
});
test('mobile verify reset code accepts the existing password reset token flow', function () {
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
});
test('mobile reset password updates password clears code and revokes sessions', function () {
    $user = User::factory()->create([
        'email' => 'reset-password@example.com',
        'password' => Hash::make('password'),
    ]);
    $tokens = app(TokenService::class)->issueTokenPair($user);
    $accessId = explode('|', (string) $tokens['token'], 2)[0];
    $refreshId = explode('|', (string) $tokens['refreshToken'], 2)[0];

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
    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
    $this->assertDatabaseMissing('password_reset_tokens', [
        'email' => $user->email,
    ]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $accessId]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $refreshId]);
});
test('mobile logout revokes current access and refresh tokens', function () {
    $loginResponse = $this->postJson('/api/mobile/auth/login', [
        'email' => User::factory()->create([
            'password' => Hash::make('password'),
        ])->email,
        'password' => 'password',
    ]);

    $plainTextToken = $loginResponse->json('data.token');
    $refreshToken = $loginResponse->json('data.refreshToken');
    $tokenId = explode('|', $plainTextToken, 2)[0];
    $refreshTokenId = explode('|', $refreshToken, 2)[0];

    $response = $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
        ->postJson('/api/mobile/auth/logout');

    $response->assertOk();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('message', 'Logged out successfully.');

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $refreshTokenId]);
});
