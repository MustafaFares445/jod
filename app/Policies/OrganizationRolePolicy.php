<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrganizationRole;
use App\Models\User;

class OrganizationRolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOrganizationOwner();
    }

    public function view(User $user, OrganizationRole $role): bool
    {
        return $user->isOrganizationOwner() && $this->sameOrganization($user, $role);
    }

    public function create(User $user): bool
    {
        return $user->isOrganizationOwner();
    }

    public function update(User $user, OrganizationRole $role): bool
    {
        return $user->isOrganizationOwner() && $this->sameOrganization($user, $role);
    }

    public function delete(User $user, OrganizationRole $role): bool
    {
        return $user->isOrganizationOwner() && $this->sameOrganization($user, $role);
    }

    private function sameOrganization(User $user, OrganizationRole $role): bool
    {
        return $user->organization_id !== null
            && (string) $user->organization_id === (string) $role->organization_id;
    }
}
