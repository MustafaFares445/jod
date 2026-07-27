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

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('organization');

        return MobileApiResponse::success(
            UserResource::make($user)->resolve($request),
            'Profile retrieved successfully.',
        );
    }

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

    public function permissions(Request $request, PermissionCatalogService $permissionCatalogService): JsonResponse
    {
        return MobileApiResponse::success(
            $permissionCatalogService->forUser($request->user()),
            'Permissions retrieved successfully.',
        );
    }

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

    public function ping(Request $request): JsonResponse
    {
        return MobileApiResponse::success([
            'pong' => true,
            'userId' => $request->user()->id,
        ], 'Pong.');
    }
}
