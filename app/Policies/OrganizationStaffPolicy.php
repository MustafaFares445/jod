<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrganizationStaff;
use App\Models\User;

class OrganizationStaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function view(User $user, OrganizationStaff $staff): bool
    {
        return $user->organization_id === $staff->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null;
    }

    public function update(User $user, OrganizationStaff $staff): bool
    {
        return $user->organization_id === $staff->organization_id;
    }

    public function delete(User $user, OrganizationStaff $staff): bool
    {
        return $user->organization_id === $staff->organization_id
            && ((int) ($staff->user_id ?? 0) !== (int) $user->id);
    }
}
