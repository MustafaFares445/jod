<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\OrganizationStaff;
use App\Models\User;
use App\Support\Permissions\PermissionNameResolver;

class OrganizationStaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null
            && $this->authorizeOrganizationAction($user, PermissionAction::VIEW, PermissionAction::MANAGE);
    }

    public function view(User $user, OrganizationStaff $staff): bool
    {
        return $this->sameOrganization($user, $staff)
            && $this->authorizeOrganizationAction($user, PermissionAction::VIEW, PermissionAction::MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $this->authorizeOrganizationAction($user, PermissionAction::CREATE, PermissionAction::MANAGE);
    }

    public function update(User $user, OrganizationStaff $staff): bool
    {
        return $this->sameOrganization($user, $staff)
            && $this->authorizeOrganizationAction($user, PermissionAction::UPDATE, PermissionAction::MANAGE);
    }

    public function delete(User $user, OrganizationStaff $staff): bool
    {
        return $this->sameOrganization($user, $staff)
            && $this->authorizeOrganizationAction($user, PermissionAction::DELETE, PermissionAction::MANAGE)
            && ($staff->user_id === null || (string) $staff->user_id !== (string) $user->id);
    }

    private function authorizeOrganizationAction(User $user, PermissionAction ...$actions): bool
    {
        foreach ($actions as $action) {
            if ($user->can(PermissionNameResolver::resolve(PermissionGroup::ORG_STAFF, $action))) {
                return true;
            }
        }

        return false;
    }

    private function sameOrganization(User $user, OrganizationStaff $staff): bool
    {
        return $user->organization_id !== null
            && (string) $user->organization_id === (string) $staff->organization_id;
    }
}
