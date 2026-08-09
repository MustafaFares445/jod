<?php

declare(strict_types=1);

namespace App\Services\Permissions;

use App\Enums\PermissionGroup;
use App\Enums\PermissionModule;
use App\Models\OrganizationRole;
use App\Models\OrganizationStaff;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use Spatie\Permission\Models\Permission;

class OrganizationPermissionSyncService
{
    public function syncForUser(User $user): void
    {
        if ($user->user_type === 'admin') {
            return;
        }

        $managedPermissionNames = $this->managedPermissionNames();

        $preservedPermissionNames = $user->getDirectPermissions()
            ->pluck('name')
            ->reject(fn (string $permission): bool => in_array($permission, $managedPermissionNames, true))
            ->values()
            ->all();

        $rolePermissionNames = [];

        if ($user->organization_id !== null) {
            $staff = OrganizationStaff::query()
                ->with('role')
                ->where('organization_id', $user->organization_id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if ($staff?->role?->is_active) {
                $rolePermissionNames = collect($staff->role->permissions ?? [])
                    ->filter(fn (mixed $permission): bool => is_string($permission))
                    ->filter(fn (string $permission): bool => in_array($permission, $managedPermissionNames, true))
                    ->values()
                    ->all();
            }
        }

        $permissionNames = Permission::query()
            ->whereIn('name', array_values(array_unique([
                ...$preservedPermissionNames,
                ...$rolePermissionNames,
            ])))
            ->pluck('name')
            ->all();

        $user->syncPermissions($permissionNames);
    }

    public function syncForRole(OrganizationRole $role): void
    {
        $role->staff()
            ->with('user')
            ->whereNotNull('user_id')
            ->get()
            ->each(function (OrganizationStaff $staff): void {
                if ($staff->user !== null) {
                    $this->syncForUser($staff->user);
                }
            });
    }

    /**
     * @return list<string>
     */
    private function managedPermissionNames(): array
    {
        return PermissionCatalog::permissions()
            ->filter(fn (array $permission): bool => $permission['group'] === PermissionGroup::DASHBOARD
                || $permission['group']->module() === PermissionModule::ORGANIZATION)
            ->pluck('name')
            ->values()
            ->all();
    }
}
