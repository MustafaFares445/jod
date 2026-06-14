<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\CampaignApplication;
use App\Models\User;
use App\Support\Permissions\PermissionNameResolver;

class CampaignApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->organization_id !== null
            && $this->authorizeOrganizationAction($user, PermissionAction::VIEW, PermissionAction::MANAGE);
    }

    public function view(User $user, CampaignApplication $application): bool
    {
        return $this->sameOrganization($user, $application)
            && $this->authorizeOrganizationAction($user, PermissionAction::VIEW, PermissionAction::MANAGE);
    }

    public function create(User $user): bool
    {
        return $user->organization_id !== null
            && $this->authorizeOrganizationAction($user, PermissionAction::CREATE, PermissionAction::MANAGE);
    }

    public function update(User $user, CampaignApplication $application): bool
    {
        return $this->sameOrganization($user, $application)
            && $this->authorizeOrganizationAction($user, PermissionAction::UPDATE, PermissionAction::MANAGE);
    }

    public function delete(User $user, CampaignApplication $application): bool
    {
        return $this->sameOrganization($user, $application)
            && $this->authorizeOrganizationAction($user, PermissionAction::DELETE, PermissionAction::MANAGE);
    }

    private function authorizeOrganizationAction(User $user, PermissionAction ...$actions): bool
    {
        foreach ($actions as $action) {
            if ($user->can(PermissionNameResolver::resolve(PermissionGroup::ORG_APPLICANT, $action))) {
                return true;
            }
        }

        return false;
    }

    private function sameOrganization(User $user, CampaignApplication $application): bool
    {
        return $user->organization_id !== null
            && (string) $user->organization_id === (string) $application->organization_id;
    }
}
