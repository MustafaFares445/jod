<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\ProfileRequest;
use App\Http\Resources\Mobile\UserResource;
use App\Services\Permissions\PermissionCatalogService;
use App\Services\UserService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct(private UserService $userService) {}

    /**
     * Get the authenticated mobile user profile.
     *
     * Requires a Sanctum bearer token.
     *
     * @response array{success: bool, message: string, data: array{id: string, name: string, email: string, phone: string|null, userType: string|null, status: string|null, organizationId: string|null, organization: object{id: string, name: string, email: string|null, phone: string|null, status: string|null, verificationStatus: string|null}|null, createdAt: string|null, lastActiveAt: string|null}, error: null, meta: object{}}
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('organization');

        return MobileApiResponse::success(
            UserResource::make($user)->resolve($request),
            'Profile retrieved successfully.',
        );
    }

    /**
     * Update the authenticated mobile user profile.
     *
     * Requires a Sanctum bearer token.
     *
     * @response array{success: bool, message: string, data: array{id: string, name: string, email: string, phone: string|null, userType: string|null, status: string|null, organizationId: string|null, organization: object{id: string, name: string, email: string|null, phone: string|null, status: string|null, verificationStatus: string|null}|null, createdAt: string|null, lastActiveAt: string|null}, error: null, meta: object{}}
     */
    public function updateProfile(ProfileRequest $request): JsonResponse
    {
        $user = $this->userService->update(
            UserData::from($request->validated()),
            $request->user(),
            $request->has('phone') ? ['phone'] : [],
        )->loadMissing('organization');

        return MobileApiResponse::success(
            UserResource::make($user)->resolve($request),
            'Profile updated successfully.',
        );
    }

    /**
     * Get the authenticated user's mobile permission catalogue.
     *
     * Requires a Sanctum bearer token.
     *
     * @response array{success: bool, message: string, data: array, error: null, meta: object{}}
     */
    public function permissions(Request $request, PermissionCatalogService $permissionCatalogService): JsonResponse
    {
        return MobileApiResponse::success(
            $permissionCatalogService->forUser($request->user()),
            'Permissions retrieved successfully.',
        );
    }
}
