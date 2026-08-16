<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Mobile\ForgotPasswordRequest;
use App\Http\Requests\Mobile\LoginRequest;
use App\Http\Requests\Mobile\RegisterRequest;
use App\Http\Requests\Mobile\ResetPasswordRequest;
use App\Http\Requests\Mobile\VerifyResetCodeRequest;
use App\Http\Resources\Mobile\UserResource;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Mobile\MobilePasswordResetService;
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
        private readonly MobilePasswordResetService $passwordResetService,
    ) {}

    /**
     * Register a mobile account.
     *
     * Supports the mobile screen aliases `firstName`, `lastName`,
     * `phoneNumber`, and `confirmPassword`. Email is optional for phone-first
     * mobile accounts and can be added later from profile settings.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'password' => $validated['password'],
            'status' => 'active',
            'user_type' => 'general',
        ])->loadMissing('organization');

        return MobileApiResponse::success([
            ...$this->tokenService->issueTokenPair($user),
            'user' => UserResource::make($user)->resolve($request),
        ], 'Registered successfully.');
    }

    /**
     * Log in to the mobile API.
     *
     * Public endpoint that returns a short-lived access token, a rotating refresh token, and the current mobile user profile.
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
            ->with('organization')
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return MobileApiResponse::error(
                'invalid_credentials',
                'The provided credentials are incorrect.',
                null,
                401,
            );
        }

        $user->forceFill([
            'last_active_at' => now(),
        ])->save();

        return MobileApiResponse::success([
            ...$this->tokenService->issueTokenPair($user),
            'user' => UserResource::make($user)->resolve($request),
        ], 'Logged in successfully.');
    }

    /**
     * Rotate a mobile refresh token and issue a new token pair.
     */
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

    /**
     * Request a password reset code by email or phone number.
     *
     * `phoneNumber` is accepted as an alias for `login`.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $login = $request->validated('login');

        if (! $this->passwordResetService->canDeliverTo($login)) {
            return MobileApiResponse::error(
                'reset_delivery_unavailable',
                'Password reset delivery is not configured for this login channel.',
                null,
                503,
            );
        }

        $user = $this->resolveUserByLogin($login);

        // Keep account lookup private: an unknown login receives the same public
        // success shape without creating or sending a reset code.
        if ($user === null) {
            return MobileApiResponse::success([
                'resetCodeSent' => true,
            ], 'If an account matches the provided login, a reset code has been sent.');
        }

        if (! $this->passwordResetService->issue($user, $login)) {
            return MobileApiResponse::error(
                'reset_delivery_failed',
                'The reset code could not be delivered. Please try again later.',
                null,
                503,
            );
        }

        return MobileApiResponse::success([
            'resetCodeSent' => true,
        ], 'Reset code sent successfully.');
    }

    /**
     * Verify a 4-digit mobile password reset code.
     */
    public function verifyResetCode(VerifyResetCodeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $this->resolveUserByLogin($validated['login']);

        if ($user === null || ! $this->passwordResetService->verify($user, $validated['code'])) {
            return MobileApiResponse::error(
                'invalid_reset_code',
                'The provided reset code is invalid or expired.',
                null,
                422,
            );
        }

        return MobileApiResponse::success([
            'resetCodeVerified' => true,
        ], 'Reset code verified successfully.');
    }

    /**
     * Reset a mobile account password using a valid 4-digit reset code.
     * A successful reset revokes all existing API sessions for the account.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $this->resolveUserByLogin($validated['login']);

        if ($user === null || ! $this->passwordResetService->verify($user, $validated['code'])) {
            return MobileApiResponse::error(
                'invalid_reset_code',
                'The provided reset code is invalid or expired.',
                null,
                422,
            );
        }

        DB::transaction(function () use ($user, $validated): void {
            $user->forceFill([
                'password' => $validated['password'],
            ])->save();

            $user->tokens()->delete();
            $this->passwordResetService->consume($user);
        });

        return MobileApiResponse::success([
            'resetPasswordUpdated' => true,
        ], 'Password reset successfully.');
    }

    /**
     * Log out from the mobile API.
     *
     * Requires a mobile access token and revokes both tokens belonging to the current session.
     */
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
}
