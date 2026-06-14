<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Donation;
use App\Models\User;
use App\Support\Permissions\PermissionNameResolver;

class DonationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null
            && $this->authorizeOrganizationAction($user, PermissionAction::VIEW, PermissionAction::MANAGE);
    }

    public function view(User $user, Donation $donation): bool
    {
        return $this->sameOrganization($user, $donation)
            && $this->authorizeOrganizationAction($user, PermissionAction::VIEW, PermissionAction::MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $this->authorizeOrganizationAction($user, PermissionAction::CREATE, PermissionAction::MANAGE);
    }

    public function update(User $user, Donation $donation): bool
    {
        return $this->sameOrganization($user, $donation)
            && $this->authorizeOrganizationAction($user, PermissionAction::UPDATE, PermissionAction::MANAGE);
    }

    public function delete(User $user, Donation $donation): bool
    {
        return $this->sameOrganization($user, $donation)
            && $this->authorizeOrganizationAction($user, PermissionAction::DELETE, PermissionAction::MANAGE);
    }

    private function authorizeOrganizationAction(User $user, PermissionAction ...$actions): bool
    {
        foreach ($actions as $action) {
            if ($user->can(PermissionNameResolver::resolve(PermissionGroup::ORG_DONOR, $action))) {
                return true;
            }
        }

        return false;
    }

    private function sameOrganization(User $user, Donation $donation): bool
    {
        return $user->organization_id !== null
            && (string) $user->organization_id === (string) $donation->organization_id;
    }
}
