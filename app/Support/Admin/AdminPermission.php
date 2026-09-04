<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\User;
use App\Support\Permissions\PermissionNameResolver;

final class AdminPermission
{
    public static function allows(?User $user, PermissionGroup $group, PermissionAction $action): bool
    {
        return $user !== null && $user->can(PermissionNameResolver::resolve($group, $action));
    }

    public static function authorize(?User $user, PermissionGroup $group, PermissionAction $action): void
    {
        abort_unless(self::allows($user, $group, $action), 403);
    }
}
