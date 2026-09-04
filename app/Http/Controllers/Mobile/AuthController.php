<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Mobile\ForgotPasswordRequest;
use App\Http\Requests\Mobile\LoginRequest;
use App\Http\Requests\Mobile\RegisterRequest;
use App\Http\Requests\Mobile\ResendAccountVerificationRequest;
use App\Http\Requests\Mobile\ResetPasswordRequest;
use App\Http\Requests\Mobile\VerifyAccountRequest;
use App\Http\Requests\Mobile\VerifyResetCodeRequest;
use App\Http\Resources\Mobile\UserResource;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Mobile\AccountVerificationService;
use App\Services\Mobile\MobileDeviceService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly TokenService $tokenService,
        private readonly MobileDeviceService $mobileDeviceService,
        private readonly AccountVerificationService $accountVerificationService,
    ) {}

    /**
     * Register a mobile account and send an account-verification OTP.
     *
     * Registration does not issue API tokens until verification succeeds.
     * Retrying registration with the same email and phone for an unverified
     * account resends the verification code instead of creating a duplicate.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $emailUser = User::query()->where('email', $validated['email'])->first();
        $phoneUser = User::query()->where('phone', $validated['phone'])->first();

        if ($emailUser !== null && $phoneUser !== null && $emailUser->id !== $phoneUser->id) {
            return MobileApiResponse::error(
                'account_identity_conflict',
                'The provided email and phone belong to different accounts.',
                null,
                409,
            );
        }

        $existingUser = $emailUser ?? $phoneUser;

        if ($existingUser !== null) {
            $sameIdentity = $existingUser->email === $validated['email']
                && $existingUser->phone === $validated['phone'];

            if (! $sameIdentity || $existingUser->email_verified_at !== null) {
                return MobileApiResponse::error(
                    'account_already_exists',
                    'An account already exists with the provided email or phone.',
                    null,
                    409,
                );
            }

            if (! in_array($existingUser->status, ['active', 'pending_verification'], true)) {
                return MobileApiResponse::error(
                    'account_inactive',
                    'This account is not active.',
                    null,
                    403,
                );
            }

            $verification = $this->accountVerificationService->issue($existingUser);

            if (! $verification['sent']) {
                return MobileApiResponse::error(
                    'verification_throttled',
                    'A verification code was sent recently. Please wait before requesting another.',
                    ['retryAfter' => $verification['retryAfter']],
                    429,
                );
            }

            return MobileApiResponse::success([
                'verificationRequired' => true,
                'verificationCodeSent' => true,
                'verificationChannel' => 'email',
                'expiresIn' => $verification['expiresIn'],
                'resendAvailableIn' => $verification['retryAfter'],
                'user' => UserResource::make($existingUser->loadMissing(['organization', 'avatarMedia']))->resolve($request),
            ], 'Verification code resent successfully.');
        }

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],
            'status' => 'pending_verification',
            'user_type' => 'general',
        ])->loadMissing(['organization', 'avatarMedia']);

        $verification = $this->accountVerificationService->issue($user);

        return MobileApiResponse::success([
            'verificationRequired' => true,
            'verificationCodeSent' => $verification['sent'],
            'verificationChannel' => 'email',
            'expiresIn' => $verification['expiresIn'],
            'resendAvailableIn' => $verification['retryAfter'],
            'user' => UserResource::make($user)->resolve($request),
        ], 'Registration started. Verify your account to continue.');
    }

    /**
     * Verify the registration OTP, activate the account, and issue tokens.
     */
    public function verifyAccount(VerifyAccountRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $this->resolveUserByLogin($validated['login']);

        if ($user === null) {
            return MobileApiResponse::error('not_found', 'No mobile account matches the provided login.', null, 404);
        }

        if ($user->email_verified_at !== null) {
            return MobileApiResponse::error(
                'account_already_verified',
                'This account is already verified. Please log in.',
                null,
                409,
            );
        }

        if (! in_array($user->status, ['active', 'pending_verification'], true)) {
            return MobileApiResponse::error('account_inactive', 'This account is not active.', null, 403);
        }

        $result = $this->accountVerificationService->consume($user, $validated['code']);

        if ($result === 'too_many_attempts') {
            return MobileApiResponse::error(
                'verification_attempts_exceeded',
                'Too many invalid verification attempts. Request a new code.',
                null,
                429,
            );
        }

        if (in_array($result, ['missing', 'expired'], true)) {
            return MobileApiResponse::error(
                'verification_code_expired',
                'The verification code is missing or expired. Request a new code.',
                null,
                422,
            );
        }

        if ($result !== 'verified') {
            return MobileApiResponse::error(
                'invalid_verification_code',
                'The provided verification code is invalid.',
                null,
                422,
            );
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'status' => 'active',
            'last_active_at' => now(),
        ])->save();

        $user->loadMissing(['organization', 'avatarMedia']);

        return MobileApiResponse::success([
            ...$this->tokenService->issueTokenPair($user),
            'verificationRequired' => false,
            'user' => UserResource::make($user)->resolve($request),
        ], 'Account verified successfully.');
    }

    /**
     * Resend a registration verification OTP.
     */
    public function resendAccountVerification(ResendAccountVerificationRequest $request): JsonResponse
    {
        $user = $this->resolveUserByLogin($request->validated('login'));

        if ($user === null) {
            return MobileApiResponse::error('not_found', 'No mobile account matches the provided login.', null, 404);
        }

        if ($user->email_verified_at !== null) {
            return MobileApiResponse::error(
                'account_already_verified',
                'This account is already verified. Please log in.',
                null,
                409,
            );
        }

        if (! in_array($user->status, ['active', 'pending_verification'], true)) {
            return MobileApiResponse::error('account_inactive', 'This account is not active.', null, 403);
        }

        $verification = $this->accountVerificationService->issue($user);

        if (! $verification['sent']) {
            return MobileApiResponse::error(
                'verification_throttled',
                'A verification code was sent recently. Please wait before requesting another.',
                ['retryAfter' => $verification['retryAfter']],
                429,
            );
        }

        return MobileApiResponse::success([
            'verificationRequired' => true,
            'verificationCodeSent' => true,
            'verificationChannel' => 'email',
            'expiresIn' => $verification['expiresIn'],
            'resendAvailableIn' => $verification['retryAfter'],
        ], 'Verification code resent successfully.');
    }

    /**
     * Log in to the mobile API.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()
            ->where(function (Builder $builder) use ($validated): void {
                if (filled($validated['email'] ?? null)) {
                    $builder->where('email', $validated['email']);
                }

                if (filled($validated['phone'] ?? null)) {
                    $builder->orWhere('phone', $validated['phone']);
                }
            })
            ->with(['organization', 'avatarMedia'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return MobileApiResponse::error(
                'invalid_credentials',
                'The provided credentials are incorrect.',
                null,
                401,
            );
        }

        if ($user->email_verified_at === null) {
            return MobileApiResponse::error(
                'verification_required',
                'Account verification is required before login.',
                [
                    'verificationRequired' => true,
                    'verificationChannel' => 'email',
                ],
                403,
            );
        }

        if ($user->status !== 'active') {
            return MobileApiResponse::error(
                'account_inactive',
                'This account is not active.',
                null,
                403,
            );
        }

        if ($user->organization_id !== null && ! $user->organization?->isActiveAndVerified()) {
            return MobileApiResponse::error(
                'organization_inactive',
                'This organization account must be active and verified before login.',
                null,
                403,
            );
        }

        $user->forceFill([
            'last_active_at' => now(),
        ])->save();

        if (filled($validated['fcmToken'] ?? null)) {
            $this->mobileDeviceService->register($user, [
                'pushToken' => $validated['fcmToken'],
                'pushTargetType' => 'token',
                'platform' => $validated['fcmPlatform'] ?? 'mobile',
                'deviceId' => $validated['deviceId'] ?? null,
                'appVersion' => $validated['appVersion'] ?? null,
            ]);
        }

        return MobileApiResponse::success([
            ...$this->tokenService->issueTokenPair($user),
            'user' => UserResource::make($user)->resolve($request),
        ], 'Logged in successfully.');
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $tokens = $this->tokenService->rotateRefreshToken(
            $request->validated('refreshToken'),
        );

        if ($tokens === null) {
            return MobileApiResponse::error(
                'invalid_refresh_token',
                'The refresh token is invalid or expired.',
                null,
                401,
            );
        }

        return MobileApiResponse::success($tokens, 'Token refreshed successfully.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = $this->resolveUserByLogin($request->validated('login'));

        if (! $user) {
            return MobileApiResponse::error('not_found', 'No mobile account matches the provided login.', null, 404);
        }

        $code = $this->generateResetCode();

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => $code, 'created_at' => now()]
        );

        return MobileApiResponse::success([
            'resetCodeSent' => true,
        ], 'Reset code generated successfully.');
    }

    public function verifyResetCode(VerifyResetCodeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $this->resolveUserByLogin($validated['login']);

        if (! $user || ! $this->isValidResetCode($user->email, $validated['code'])) {
            return MobileApiResponse::error('invalid_reset_code', 'The provided reset code is invalid or expired.', null, 422);
        }

        return MobileApiResponse::success([
            'resetCodeVerified' => true,
        ], 'Reset code verified successfully.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $this->resolveUserByLogin($validated['login']);

        if (! $user || ! $this->isValidResetCode($user->email, $validated['code'])) {
            return MobileApiResponse::error('invalid_reset_code', 'The provided reset code is invalid or expired.', null, 422);
        }

        DB::transaction(function () use ($user, $validated): void {
            $user->forceFill([
                'password' => $validated['password'],
            ])->save();

            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
        });

        return MobileApiResponse::success([
            'resetPasswordUpdated' => true,
        ], 'Password reset successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user?->currentAccessToken();

        if ($user instanceof User && $currentToken instanceof PersonalAccessToken) {
            $this->tokenService->revokeTokenSession($user, $currentToken);
        }

        return MobileApiResponse::success(null, 'Logged out successfully.');
    }

    private function resolveUserByLogin(string $login): ?User
    {
        return User::query()
            ->where('email', $login)
            ->orWhere('phone', $login)
            ->first();
    }

    private function generateResetCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function isValidResetCode(string $email, string $code): bool
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (! $record) {
            return false;
        }

        if (! isset($record->created_at) || now()->diffInMinutes($record->created_at) > 15) {
            return false;
        }

        return hash_equals((string) $record->token, $code);
    }
}
