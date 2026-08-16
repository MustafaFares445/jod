<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompanyRegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\CompanyRegistrationService;
use App\Services\Auth\TokenService;
use App\Services\Permissions\OrganizationPermissionSyncService;
use App\Services\Permissions\PermissionCatalogService;
use Illuminate\Http\JsonResponse;

class CompanyAuthController extends Controller
{
    public function __construct(
        private readonly CompanyRegistrationService $companyRegistrationService,
        private readonly PermissionCatalogService $permissionCatalogService,
        private readonly OrganizationPermissionSyncService $organizationPermissionSyncService,
        private readonly TokenService $tokenService,
    ) {}

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
