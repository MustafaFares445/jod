<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Me;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Report;
use App\Services\Permissions\PermissionCatalogService;
use Illuminate\Http\Request;

class DashboardContextController extends Controller
{
    public function __invoke(
        Request $request,
        PermissionCatalogService $permissionCatalogService,
    ): array {
        $user = $request->user();
        $permissions = $permissionCatalogService->forUser($user);

        return [
            'data' => [
                'profile' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'userType' => $user->user_type,
                    'organizationId' => $user->organization_id,
                    'status' => $user->status,
                    'createdAt' => $user->created_at?->toIso8601String(),
                    'lastActiveAt' => $user->last_active_at?->toIso8601String(),
                ],
                'permissions' => $permissions,
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
            ],
        ];
    }
}
