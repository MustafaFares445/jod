<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByPermissionGroup;

class UserPolicy
{
    use AuthorizesByPermissionGroup;

    protected function permissionGroup(): PermissionGroup
    {
        return PermissionGroup::USER;
    }

    public function viewAny(User $user): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function view(User $user, User $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function create(User $user): bool
    {
        return $this->authorizeAction($user, PermissionAction::CREATE);
    }

    public function update(User $user, User $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::UPDATE);
    }

    public function delete(User $user, User $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::DELETE);
    }

    public function resetPassword(User $user): bool
    {
        return $this->authorizeAction($user, PermissionAction::RESET_PASSWORD);
    }
}
