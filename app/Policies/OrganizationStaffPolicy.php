<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrganizationStaff;
use App\Models\User;

class OrganizationStaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOrganizationOwner();
    }

    public function view(User $user, OrganizationStaff $staff): bool
    {
        return $user->isOrganizationOwner() && $this->sameOrganization($user, $staff);
    }

    public function create(User $user): bool
    {
        return $user->isOrganizationOwner();
    }

    public function update(User $user, OrganizationStaff $staff): bool
    {
        return $user->isOrganizationOwner() && $this->sameOrganization($user, $staff);
    }

    public function delete(User $user, OrganizationStaff $staff): bool
    {
        return $user->isOrganizationOwner()
            && $this->sameOrganization($user, $staff)
            && ($staff->user_id === null || (string) $staff->user_id !== (string) $user->id);
    }

    private function sameOrganization(User $user, OrganizationStaff $staff): bool
    {
        return $user->organization_id !== null
            && (string) $user->organization_id === (string) $staff->organization_id;
    }
}
