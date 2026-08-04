<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Data\UserData;
use App\Http\Controllers\Controller;
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

class MeController extends Controller
{
    public function __construct(private UserService $userService) {}

    /**
     * Get the authenticated mobile user profile.
     *
     * Requires a Sanctum bearer token.
     *
     * @response array{success: bool, message: string, data: array{id: int, name: string, email: string, phone: string|null, userType: string|null, status: string|null, organizationId: int|null, organization: array|null, createdAt: string|null, lastActiveAt: string|null}, error: null, meta: array}
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
     * @response array{success: bool, message: string, data: array{id: int, name: string, email: string, phone: string|null, userType: string|null, status: string|null, organizationId: int|null, organization: array|null, createdAt: string|null, lastActiveAt: string|null}, error: null, meta: array}
     */
    public function updateProfile(ProfileRequest $request): JsonResponse
    {
        $user = $this->userService->update(
            UserData::from($request->validated()),
            $request->user(),
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
     * @response array{success: bool, message: string, data: array, error: null, meta: array}
     */
    public function permissions(Request $request, PermissionCatalogService $permissionCatalogService): JsonResponse
    {
        return MobileApiResponse::success(
            $permissionCatalogService->forUser($request->user()),
            'Permissions retrieved successfully.',
        );
    }

    /**
     * Get profile, permissions, and dashboard counters for mobile startup.
     *
     * Requires a Sanctum bearer token.
     *
     * @response array{success: bool, message: string, data: array{profile: array, permissions: array, counters: array{unreadNotifications: int, pendingReviews: int, openReports: int}}, error: null, meta: array}
     */
    public function dashboardContext(Request $request, PermissionCatalogService $permissionCatalogService): JsonResponse
    {
        $user = $request->user();

        return MobileApiResponse::success([
            'profile' => UserResource::make($user->loadMissing('organization'))->resolve($request),
            'permissions' => $permissionCatalogService->forUser($user),
            'counters' => [
                'unreadNotifications' => Notification::query()
                    ->where('status', 'unread')
                    ->where(function ($query) use ($user): void {
                        $query->where('recipient_id', $user->id);

                        if ($user->organization_id) {
                            $query->orWhere('organization_id', $user->organization_id);
                        }
                    })
                    ->count(),
                'pendingReviews' => Post::query()->where('status', 'pending')->count()
                    + Campaign::query()->where('status', 'pending')->count(),
                'openReports' => Report::query()
                    ->whereIn('status', ['new', 'in_progress'])
                    ->count(),
            ],
        ], 'Dashboard context retrieved successfully.');
    }

    /**
     * Ping the authenticated mobile API session.
     *
     * Requires a Sanctum bearer token.
     *
     * @response array{success: bool, message: string, data: array{pong: bool, userId: int}, error: null, meta: array}
     */
    public function ping(Request $request): JsonResponse
    {
        return MobileApiResponse::success([
            'pong' => true,
            'userId' => $request->user()->id,
        ], 'Pong.');
    }
}
