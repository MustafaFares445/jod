<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function grantPermissions(User $user, array $permissions, string $guard = 'web'): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, $guard);
        }

        $user->givePermissionTo($permissions);
    }
}
