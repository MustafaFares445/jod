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
use Illuminate\Http\UploadedFile;

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
        /** @var UploadedFile $logo */
        $logo = $request->file('logo');
        $user = $this->companyRegistrationService->register($request->validated(), $logo);

        $this->organizationPermissionSyncService->syncForUser($user);
        $user->refresh();

        return $this->successResponse([
            ...$this->tokenService->issueTokenPair($user),
            'user' => UserResource::make($user)->resolve(),
            'permissions' => $this->permissionCatalogService->forUser($user),
        ], 'تم تسجيل المنظمة بنجاح', 201);
    }
}
