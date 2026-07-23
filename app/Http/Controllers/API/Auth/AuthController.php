<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Permissions\OrganizationPermissionSyncService;
use App\Services\Permissions\PermissionCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly PermissionCatalogService $permissionCatalogService,
        private readonly OrganizationPermissionSyncService $organizationPermissionSyncService,
        private readonly TokenService $tokenService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()
            ->where('email', $validated['email'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('The provided credentials are incorrect.', 401);
        }

        $user->forceFill([
            'last_active_at' => now(),
        ])->save();

        $this->organizationPermissionSyncService->syncForUser($user);
        $user->refresh();

        return $this->successResponse([
            ...$this->tokenService->issueTokenPair($user),
            'user' => UserResource::make($user)->resolve(),
            'permissions' => $this->permissionCatalogService->forUser($user),
        ], 'Logged in successfully');
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $tokens = $this->tokenService->rotateRefreshToken(
            $request->validated('refreshToken'),
        );

        if ($tokens === null) {
            return $this->errorResponse('The refresh token is invalid or expired.', 401);
        }

        return $this->successResponse($tokens, 'Token refreshed successfully');
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user?->currentAccessToken();

        if ($user instanceof User && $currentToken instanceof PersonalAccessToken) {
            $this->tokenService->revokeTokenSession($user, $currentToken);
        }

        return $this->successResponse(message: 'Logged out successfully');
    }
}
