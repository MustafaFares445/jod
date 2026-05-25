<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByPermissionGroup;

class OrganizationPolicy
{
    use AuthorizesByPermissionGroup;

    protected function permissionGroup(): PermissionGroup
    {
        return PermissionGroup::ORGANIZATION;
    }

    public function viewAny(User $user): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function view(User $user, Organization $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function update(User $user, Organization $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::UPDATE);
    }

    public function delete(User $user, Organization $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::DELETE);
    }

    public function verify(User $user, Organization $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::VERIFY);
    }

    public function accept(User $user, Organization $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::ACCEPT);
    }
}
