<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Badge;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByPermissionGroup;

class BadgePolicy
{
    use AuthorizesByPermissionGroup;

    protected function permissionGroup(): PermissionGroup
    {
        return PermissionGroup::BADGE;
    }

    public function viewAny(User $user): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function view(User $user, Badge $badge): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function create(User $user): bool
    {
        return $this->authorizeAction($user, PermissionAction::CREATE);
    }

    public function update(User $user, Badge $badge): bool
    {
        return $this->authorizeAction($user, PermissionAction::UPDATE);
    }

    public function delete(User $user, Badge $badge): bool
    {
        return $this->authorizeAction($user, PermissionAction::DELETE);
    }
}
