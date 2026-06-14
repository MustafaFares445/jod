<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Organization;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByPermissionGroup;
use App\Support\Permissions\PermissionNameResolver;

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

    public function viewSettings(User $user, Organization $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::VIEW);
    }

    public function create(User $user): bool
    {
        return $this->authorizeAction($user, PermissionAction::CREATE);
    }

    public function update(User $user, Organization $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::UPDATE);
    }

    public function updateSettings(User $user, Organization $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::UPDATE);
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

    private function authorizeOrganizationAction(User $user, PermissionAction $action): bool
    {
        return $user->can(
            PermissionNameResolver::resolve(PermissionGroup::ORG_SETTINGS, $action)
        );
    }

    private function sameOrganization(User $user, Organization $model): bool
    {
        return $user->organization_id !== null
            && (string) $user->organization_id === (string) $model->id;
    }
}
