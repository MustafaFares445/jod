<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\OrganizationRole;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByPermissionGroup;

class OrganizationRolePolicy
{
    use AuthorizesByPermissionGroup;

    protected function permissionGroup(): PermissionGroup
    {
        return PermissionGroup::ORG_ROLE;
    }

    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null
            && $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function view(User $user, OrganizationRole $role): bool
    {
        return $user->organization_id === $role->organization_id
            && $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $this->authorizeAction($user, PermissionAction::CREATE);
    }

    public function update(User $user, OrganizationRole $role): bool
    {
        return $user->organization_id === $role->organization_id
            && $this->authorizeAction($user, PermissionAction::UPDATE);
    }

    public function delete(User $user, OrganizationRole $role): bool
    {
        return $user->organization_id === $role->organization_id
            && $this->authorizeAction($user, PermissionAction::DELETE);
    }
}
