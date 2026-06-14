<?php

namespace Tests;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\User;
use App\Support\Permissions\PermissionCatalog;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function grantPermissions(User $user, array $permissions, string $guard = 'web'): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionCatalog::names() as $permissionName) {
            Permission::findOrCreate($permissionName, $guard);
        }

        $permissionNames = [];
        foreach ($permissions as $permission) {
            $permissionName = $this->normalizePermissionName($permission);
            Permission::findOrCreate($permissionName, $guard);
            $permissionNames[] = $permissionName;
        }

        $user->givePermissionTo($permissionNames);
    }

    private function normalizePermissionName(mixed $permission): string
    {
        if (is_string($permission)) {
            return $permission;
        }

        if (is_array($permission)) {
            $group = $permission['group'] ?? $permission[0] ?? null;
            $action = $permission['action'] ?? $permission[1] ?? null;

            if ($group !== null && $action !== null) {
                return PermissionNameResolver::resolve($group, $action);
            }
        }

        if ($permission instanceof PermissionGroup || $permission instanceof PermissionAction) {
            throw new InvalidArgumentException('Permission definitions must include both a group and an action.');
        }

        throw new InvalidArgumentException('Unsupported permission definition.');
    }
}
