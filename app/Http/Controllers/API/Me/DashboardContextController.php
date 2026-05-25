<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Me;

use App\Http\Controllers\Controller;
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
                    'unreadNotifications' => 0,
                    'pendingReviews' => 0,
                    'openReports' => 0,
                ],
            ],
        ];
    }
}
