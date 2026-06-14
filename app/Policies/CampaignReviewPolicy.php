<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Campaign;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByPermissionGroup;
use App\Support\Permissions\PermissionNameResolver;

class CampaignReviewPolicy
{
    use AuthorizesByPermissionGroup;

    protected function permissionGroup(): PermissionGroup
    {
        return PermissionGroup::CAMPAIGN_REVIEW;
    }

    public function viewAny(User $user): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function view(User $user, Campaign $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function viewAnyOrganization(User $user): bool
    {
        return $user->organization_id !== null
            && $this->authorizeOrganizationAction($user, PermissionAction::VIEW);
    }

    public function viewOrganization(User $user, Campaign $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::VIEW);
    }

    public function approve(User $user, Campaign $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::APPROVE);
    }

    public function reject(User $user, Campaign $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::REJECT);
    }

    public function createOrganization(User $user): bool
    {
        return $user->organization_id !== null
            && $this->authorizeOrganizationAction($user, PermissionAction::CREATE);
    }

    public function updateOrganization(User $user, Campaign $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::UPDATE);
    }

    public function deleteOrganization(User $user, Campaign $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::DELETE);
    }

    public function closeOrganization(User $user, Campaign $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::CLOSE);
    }

    private function authorizeOrganizationAction(User $user, PermissionAction $action): bool
    {
        return $user->can(
            PermissionNameResolver::resolve(PermissionGroup::ORG_CAMPAIGN, $action)
        );
    }

    private function sameOrganization(User $user, Campaign $model): bool
    {
        return $user->organization_id !== null
            && (string) $user->organization_id === (string) $model->organization_id;
    }
}
