<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\LoginRequest;
use App\Http\Resources\Mobile\UserResource;
use App\Models\User;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Log in to the mobile API.
     *
     * Public endpoint that returns a Sanctum bearer token and the current mobile user profile.
     *
     * @response array{success: bool, message: string, data: array{token: string, tokenType: string, user: array{id: string, name: string, email: string, phone: string|null, userType: string|null, status: string|null, organizationId: string|null, organization: object{id: string, name: string, email: string|null, phone: string|null, status: string|null, verificationStatus: string|null}|null, createdAt: string|null, lastActiveAt: string|null}}, error: null, meta: object{}}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()
            ->where('email', $validated['email'])
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

        $token = $user->createToken('mobile-token')->plainTextToken;

        return MobileApiResponse::success([
            'token' => $token,
            'tokenType' => 'Bearer',
            'user' => UserResource::make($user)->resolve($request),
        ], 'Logged in successfully.');
    }

    /**
     * Log out from the mobile API.
     *
     * Requires a Sanctum bearer token and revokes the current access token.
     *
     * @response array{success: bool, message: string, data: null, error: null, meta: object{}}
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return MobileApiResponse::success(null, 'Logged out successfully.');
    }
}
