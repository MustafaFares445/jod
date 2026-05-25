<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrganizationRole;
use App\Models\User;

class OrganizationRolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function view(User $user, OrganizationRole $role): bool
    {
        return $user->organization_id === $role->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function update(User $user, OrganizationRole $role): bool
    {
        return $user->organization_id === $role->organization_id && !$role->is_system;
    }

    public function delete(User $user, OrganizationRole $role): bool
    {
        return $user->organization_id === $role->organization_id;
    }
}
