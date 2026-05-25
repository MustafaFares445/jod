<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\User;
use App\Support\Permissions\PermissionNameResolver;

trait AuthorizesByPermissionGroup
{
    abstract protected function permissionGroup(): PermissionGroup;

    protected function authorizeAction(User $user, PermissionAction $action): bool
    {
        return $user->can(
            PermissionNameResolver::resolve($this->permissionGroup(), $action)
        );
    }
}
