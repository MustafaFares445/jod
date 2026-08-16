<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\ChangePasswordRequest;
use App\Http\Requests\Mobile\ProfileRequest;
use App\Http\Resources\Mobile\UserResource;
use App\Models\User;
use App\Services\Permissions\PermissionCatalogService;
use App\Services\UserService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MeController extends Controller
{
    public function __construct(private UserService $userService) {}

    /**
     * Get the authenticated mobile user profile.
     *
     * Requires a Sanctum bearer token.
     */
    public function profile(Request $request): JsonResponse
    {
        return MobileApiResponse::success(
            UserResource::make($this->profileUser($request->user()))->resolve($request),
            'Profile retrieved successfully.',
        );
    }

    /**
     * Update the authenticated mobile user profile.
     *
     * Requires a Sanctum bearer token.
     *
     * @bodyParam name string required The user's display name.
     * @bodyParam email string required The user's email address.
     * @bodyParam phone string optional The user's phone number.
     * @bodyParam city string optional The user's city.
     * @bodyParam bio string optional The user's biography, up to 180 characters.
     */
    public function updateProfile(ProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $preserveNullAttributes = array_values(array_intersect(
            ['phone', 'city', 'bio'],
            array_keys($validated),
        ));

        $user = $this->userService->update(
            UserData::from($validated),
            $request->user(),
            $preserveNullAttributes,
        );

        return MobileApiResponse::success(
            UserResource::make($this->profileUser($user))->resolve($request),
            'Profile updated successfully.',
        );
    }

    /**
     * Change the authenticated mobile user password.
     *
     * Requires a Sanctum bearer token.
     *
     * @bodyParam currentPassword string required The current password.
     * @bodyParam password string required The new password.
     * @bodyParam password_confirmation string required Confirmation of the new password.
     *
     * @response array{success: bool, message: string, data: array{passwordChanged: bool}, error: null, meta: array}
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->validated('currentPassword'), $user->password)) {
            return MobileApiResponse::error('invalid_credentials', 'The current password is incorrect.', null, 422);
        }

        $this->userService->updatePassword($user, $request->validated('password'));

        return MobileApiResponse::success([
            'passwordChanged' => true,
        ], 'Password changed successfully.');
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

    private function profileUser(User $user): User
    {
        return $user
            ->loadMissing('organization')
            ->loadCount(['posts', 'savedPosts', 'donations']);
    }
}
