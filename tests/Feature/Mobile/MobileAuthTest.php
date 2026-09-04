<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Notifications\AccountVerificationCodeNotification;
use App\Services\Auth\TokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('mobile registration rejects non Syrian mobile numbers', function () {
    $this->postJson('/api/mobile/auth/register', [
        'name' => 'Invalid Phone User',
        'email' => 'invalid-phone@example.com',
        'phone' => '0991000100',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['phone'], 'error.details');
});

test('mobile registration creates a pending account and sends verification code without issuing tokens', function () {
    Notification::fake();

    $response = $this->postJson('/api/mobile/auth/register', [
        'name' => 'Mobile Register User',
        'email' => 'register@example.com',
        'phone' => '+963991000111',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Registration started. Verify your account to continue.')
        ->assertJsonPath('data.verificationRequired', true)
        ->assertJsonPath('data.verificationCodeSent', true)
        ->assertJsonPath('data.verificationChannel', 'email')
        ->assertJsonPath('data.user.email', 'register@example.com')
        ->assertJsonPath('data.user.status', 'pending_verification')
        ->assertJsonPath('data.user.verified', false);

    expect($response->json('data.token'))->toBeNull();
    expect($response->json('data.refreshToken'))->toBeNull();

    $user = User::query()->where('email', 'register@example.com')->firstOrFail();

    expect($user->email_verified_at)->toBeNull();
    expect($user->status)->toBe('pending_verification');

    $this->assertDatabaseHas('account_verification_tokens', [
        'email' => 'register@example.com',
        'attempts' => 0,
    ]);

    Notification::assertSentTo($user, AccountVerificationCodeNotification::class);
});

test('retrying registration for the same pending account resends instead of creating a duplicate', function () {
    Notification::fake();

    $payload = [
        'name' => 'Pending User',
        'email' => 'pending-register@example.com',
        'phone' => '+963991000112',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $this->postJson('/api/mobile/auth/register', $payload)->assertOk();

    DB::table('account_verification_tokens')
        ->where('email', $payload['email'])
        ->update(['last_sent_at' => now()->subMinutes(2)]);

    $this->postJson('/api/mobile/auth/register', $payload)
        ->assertOk()
        ->assertJsonPath('message', 'Verification code resent successfully.')
        ->assertJsonPath('data.verificationRequired', true);

    expect(User::query()->where('email', $payload['email'])->count())->toBe(1);
});

test('retrying registration too quickly is throttled', function () {
    Notification::fake();

    $payload = [
        'name' => 'Pending User',
        'email' => 'pending-throttle@example.com',
        'phone' => '+963991000113',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    $this->postJson('/api/mobile/auth/register', $payload)->assertOk();

    $this->postJson('/api/mobile/auth/register', $payload)
        ->assertStatus(429)
        ->assertJsonPath('error.code', 'verification_throttled');
});

test('registration rejects an already verified account', function () {
    User::factory()->create([
        'email' => 'existing@example.com',
        'phone' => '+963991000114',
    ]);

    $this->postJson('/api/mobile/auth/register', [
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'phone' => '+963991000114',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(409)
        ->assertJsonPath('error.code', 'account_already_exists');
});

test('mobile login rejects unverified accounts with verification required', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'unverified-login@example.com',
        'phone' => '+963991000115',
        'password' => Hash::make('password123'),
        'status' => 'pending_verification',
    ]);

    $this->postJson('/api/mobile/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
    ])->assertForbidden()
        ->assertJsonPath('error.code', 'verification_required')
        ->assertJsonPath('error.details.verificationRequired', true)
        ->assertJsonPath('error.details.verificationChannel', 'email');
});

test('mobile account verification activates account and issues rotating token pair', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'verify-account@example.com',
        'phone' => '+963991000116',
        'status' => 'pending_verification',
    ]);

    DB::table('account_verification_tokens')->insert([
        'email' => $user->email,
        'token' => Hash::make('123456'),
        'attempts' => 0,
        'created_at' => now(),
        'last_sent_at' => now(),
    ]);

    $response = $this->postJson('/api/mobile/auth/verify-account', [
        'login' => $user->email,
        'code' => '123456',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Account verified successfully.')
        ->assertJsonPath('data.verificationRequired', false)
        ->assertJsonPath('data.user.verified', true)
        ->assertJsonPath('data.user.status', 'active')
        ->assertJsonPath('data.tokenType', 'Bearer');

    expect($response->json('data.token'))->not->toBeEmpty();
    expect($response->json('data.refreshToken'))->not->toBeEmpty();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
    expect($user->fresh()->status)->toBe('active');

    $this->assertDatabaseMissing('account_verification_tokens', ['email' => $user->email]);

    $accessToken = PersonalAccessToken::findToken($response->json('data.token'));
    $refreshToken = PersonalAccessToken::findToken($response->json('data.refreshToken'));
    expect($accessToken?->can(TokenService::ACCESS_ABILITY) ?? false)->toBeTrue();
    expect($refreshToken?->can(TokenService::REFRESH_ABILITY) ?? false)->toBeTrue();
});

test('mobile account verification invalidates code after max failed attempts', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'verify-attempts@example.com',
        'status' => 'pending_verification',
    ]);

    DB::table('account_verification_tokens')->insert([
        'email' => $user->email,
        'token' => Hash::make('123456'),
        'attempts' => 0,
        'created_at' => now(),
        'last_sent_at' => now(),
    ]);

    for ($attempt = 1; $attempt <= 4; $attempt++) {
        $this->postJson('/api/mobile/auth/verify-account', [
            'login' => $user->email,
            'code' => '999999',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'invalid_verification_code');
    }

    $this->postJson('/api/mobile/auth/verify-account', [
        'login' => $user->email,
        'code' => '999999',
    ])->assertStatus(429)
        ->assertJsonPath('error.code', 'verification_attempts_exceeded');

    $this->assertDatabaseMissing('account_verification_tokens', ['email' => $user->email]);
});

test('mobile resend verification enforces cooldown', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create([
        'email' => 'resend@example.com',
        'status' => 'pending_verification',
    ]);

    $this->postJson('/api/mobile/auth/resend-verification', [
        'login' => $user->email,
    ])->assertOk()
        ->assertJsonPath('data.verificationCodeSent', true);

    $this->postJson('/api/mobile/auth/resend-verification', [
        'login' => $user->email,
    ])->assertStatus(429)
        ->assertJsonPath('error.code', 'verification_throttled');
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

test('mobile login rejects inactive users and unverified organization accounts', function () {
    $inactiveUser = User::factory()->create([
        'email' => 'inactive-mobile@example.com',
        'password' => Hash::make('password'),
        'status' => 'inactive',
    ]);

    $this->postJson('/api/mobile/auth/login', [
        'email' => $inactiveUser->email,
        'password' => 'password',
    ])->assertForbidden()->assertJsonPath('error.code', 'account_inactive');

    $organization = Organization::factory()->create([
        'status' => 'inactive',
        'verification_status' => 'unverified',
    ]);
    $organizationUser = User::factory()->create([
        'email' => 'organization-mobile@example.com',
        'password' => Hash::make('password'),
        'organization_id' => $organization->id,
        'status' => 'active',
    ]);

    $this->postJson('/api/mobile/auth/login', [
        'email' => $organizationUser->email,
        'password' => 'password',
    ])->assertForbidden()->assertJsonPath('error.code', 'organization_inactive');
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

test('mobile login rejects sending email and phone together', function () {
    $this->postJson('/api/mobile/auth/login', [
        'email' => 'mobile@example.com',
        'phone' => '+963991000117',
        'password' => 'password123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'phone'], 'error.details');
});

test('mobile login rejects invalid credentials with mobile error envelope', function () {
    User::factory()->create([
        'email' => 'invalid-login@example.com',
        'password' => Hash::make('password'),
    ]);

    $response = $this->postJson('/api/mobile/auth/login', [
        'email' => 'invalid-login@example.com',
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
