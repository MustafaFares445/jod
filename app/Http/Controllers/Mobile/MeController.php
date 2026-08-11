<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\ChangePasswordRequest;
use App\Http\Requests\Mobile\ProfileRequest;
use App\Http\Resources\Mobile\UserResource;
use App\Models\Campaign;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Report;
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
     * @bodyParam name string required The user's display name.
     * @bodyParam email string required The user's email address.
     * @bodyParam phone string optional The user's phone number.
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

    /**
     * Get the authenticated mobile dashboard context.
     *
     * Requires a Sanctum bearer token.
     *
     * @response array{success: bool, message: string, data: array{profile: array{id: string, name: string, email: string, phone: string|null, userType: string|null, status: string|null, organizationId: string|null, organization: object{id: string, name: string, email: string|null, phone: string|null, status: string|null, verificationStatus: string|null}|null, createdAt: string|null, lastActiveAt: string|null}, permissions: array<string, mixed>, counters: array{unreadNotifications: int, pendingReviews: int, openReports: int}}, error: null, meta: object{}}
     */
    public function dashboardContext(Request $request, PermissionCatalogService $permissionCatalogService): JsonResponse
    {
        $user = $request->user()->loadMissing('organization');
        $organizationId = $user->organization_id;

        return MobileApiResponse::success([
            'profile' => UserResource::make($user)->resolve($request),
            'permissions' => $permissionCatalogService->forUser($user),
            'counters' => [
                'unreadNotifications' => Notification::query()
                    ->where('status', 'unread')
                    ->where('user_id', $user->id)
                    ->count(),
                'pendingReviews' => $organizationId
                    ? Post::query()
                        ->where('organization_id', $organizationId)
                        ->where('status', 'pending')
                        ->count()
                        + Campaign::query()
                            ->where('organization_id', $organizationId)
                            ->where('status', 'pending')
                            ->count()
                    : 0,
                'openReports' => $organizationId
                    ? Report::query()
                        ->where('organization_id', $organizationId)
                        ->whereIn('status', ['new', 'in_progress'])
                        ->count()
                    : 0,
            ],
        ], 'Dashboard context retrieved successfully.');
    }

    /**
     * Respond to mobile health checks.
     *
     * Requires a Sanctum bearer token.
     *
     * @response array{success: bool, message: string, data: array{pong: bool, userId: string}, error: null, meta: object{}}
     */
    public function ping(Request $request): JsonResponse
    {
        return MobileApiResponse::success([
            'pong' => true,
            'userId' => (string) $request->user()->id,
        ], 'Pong.');
    }
}
