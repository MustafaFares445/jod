<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompanyRegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\CompanyRegistrationService;
use App\Services\Auth\TokenService;
use App\Services\Permissions\OrganizationPermissionSyncService;
use App\Services\Permissions\PermissionCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class CompanyAuthController extends Controller
{
    public function __construct(
        private readonly CompanyRegistrationService $companyRegistrationService,
        private readonly PermissionCatalogService $permissionCatalogService,
        private readonly OrganizationPermissionSyncService $organizationPermissionSyncService,
        private readonly TokenService $tokenService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::query()
            ->where('email', $validated['email'])
            ->whereNotNull('organization_id')
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('The provided company credentials are incorrect.', 401);
        }

        if ($user->status !== 'active') {
            return $this->errorResponse('This company account is not active.', 403);
        }

        $user->forceFill(['last_active_at' => now()])->save();
        $this->organizationPermissionSyncService->syncForUser($user);
        $user->refresh();

        return $this->successResponse([
            ...$this->tokenService->issueTokenPair($user),
            'user' => UserResource::make($user)->resolve(),
            'permissions' => $this->permissionCatalogService->forUser($user),
        ], 'Company logged in successfully');
    }

    public function register(CompanyRegisterRequest $request): JsonResponse
    {
        $user = $this->companyRegistrationService->register($request->validated());

        $this->organizationPermissionSyncService->syncForUser($user);
        $user->refresh();

        return $this->successResponse([
            ...$this->tokenService->issueTokenPair($user),
            'user' => UserResource::make($user)->resolve(),
            'permissions' => $this->permissionCatalogService->forUser($user),
        ], 'Company registered successfully', 201);
    }
}
