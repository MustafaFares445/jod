<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Me;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\OrganizationStaff;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\Permissions\PermissionCatalogService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DashboardContextController extends Controller
{
    public function __invoke(
        Request $request,
        PermissionCatalogService $permissionCatalogService,
    ): array {
        $user = $request->user();
        $user->loadMissing('organization');

        if ($user->user_type !== 'admin' && $user->organization_id === null) {
            throw ValidationException::withMessages([
                'organizationId' => ['Authenticated user is not linked to an organization.'],
            ]);
        }

        $membership = $user->activeOrganizationStaffMembership()
            ->with('role')
            ->first();

        return [
            'data' => [
                'profile' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'userType' => $user->user_type,
                    'organizationId' => $user->organization_id,
                    'dashboardRole' => $this->dashboardRole($user, $membership),
                    'status' => $user->status,
                    'createdAt' => $user->created_at?->toIso8601String(),
                    'lastActiveAt' => $user->last_active_at?->toIso8601String(),
                ],
                'organization' => $user->organization === null ? null : [
                    'id' => $user->organization->id,
                    'name' => $user->organization->name,
                    'status' => $user->organization->status,
                    'verificationStatus' => $user->organization->verification_status,
                ],
                'staffRole' => $membership?->role === null ? null : [
                    'id' => $membership->role->id,
                    'name' => $membership->role->name,
                    'description' => $membership->role->description,
                    'isActive' => $membership->role->is_active,
                    'isSystem' => $membership->role->is_system,
                    'membershipStatus' => $membership->status,
                ],
                'permissions' => $permissionCatalogService->forUser($user),
                'counters' => $this->counters($user),
            ],
        ];
    }

    private function dashboardRole(User $user, ?OrganizationStaff $membership): ?string
    {
        if ($user->user_type === 'admin') {
            return 'admin';
        }

        if ($membership === null || $membership->role === null || ! $membership->role->is_active) {
            return null;
        }

        return $membership->isOwner() ? 'org_owner' : 'org_staff';
    }

    /** @return array{unreadNotifications: int, pendingReviews: int, openReports: int} */
    private function counters(User $user): array
    {
        if ($user->user_type === 'admin') {
            return [
                'unreadNotifications' => Notification::query()
                    ->where('mailbox', 'inbox')
                    ->where('status', 'unread')
                    ->count(),
                'pendingReviews' => Post::query()
                    ->where('status', 'pending')
                    ->whereNull('organization_id')
                    ->whereHas('author', fn ($author) => $author->where('user_type', '!=', 'admin'))
                    ->count(),
                'openReports' => Report::query()
                    ->whereIn('status', ['new', 'in_progress'])
                    ->count(),
            ];
        }

        $organizationId = (string) $user->organization_id;

        return [
            'unreadNotifications' => Notification::query()
                ->where('mailbox', 'inbox')
                ->where('status', 'unread')
                ->where(function ($query) use ($user, $organizationId): void {
                    $query->where(function ($inner) use ($user): void {
                        $inner->where('recipient_scope', 'users')
                            ->where('recipient_id', $user->id);
                    })->orWhere(function ($inner) use ($organizationId): void {
                        $inner->where('recipient_scope', 'organizations')
                            ->where('organization_id', $organizationId);
                    });
                })
                ->count(),
            'pendingReviews' => 0,
            'openReports' => Report::query()
                ->where('organization_id', $organizationId)
                ->whereIn('status', ['new', 'in_progress'])
                ->count(),
        ];
    }
}
