<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Notification;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByPermissionGroup;
use App\Support\Permissions\PermissionNameResolver;

class NotificationPolicy
{
    use AuthorizesByPermissionGroup;

    protected function permissionGroup(): PermissionGroup
    {
        return PermissionGroup::NOTIFICATION;
    }

    public function viewAny(User $user): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function view(User $user, Notification $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function viewAnyOrganization(User $user): bool
    {
        return $user->organization_id !== null
            && $this->authorizeOrganizationAction($user, PermissionAction::VIEW);
    }

    public function viewOrganization(User $user, Notification $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::VIEW);
    }

    public function create(User $user): bool
    {
        return $this->authorizeAction($user, PermissionAction::CREATE);
    }

    public function createOrganization(User $user): bool
    {
        return $user->organization_id !== null
            && $this->authorizeOrganizationAction($user, PermissionAction::CREATE);
    }

    public function update(User $user, Notification $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::UPDATE);
    }

    public function updateReadState(User $user, Notification $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::UPDATE);
    }

    public function updateOrganization(User $user, Notification $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::UPDATE);
    }

    public function updateReadStateOrganization(User $user, Notification $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::UPDATE);
    }

    public function delete(User $user, Notification $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::DELETE);
    }

    public function deleteOrganization(User $user, Notification $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::DELETE);
    }

    public function resend(User $user, Notification $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::RESEND);
    }

    public function resendOrganization(User $user, Notification $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::RESEND);
    }

    private function authorizeOrganizationAction(User $user, PermissionAction $action): bool
    {
        return $user->can(
            PermissionNameResolver::resolve(PermissionGroup::ORG_NOTIFICATION, $action)
        );
    }

    private function sameOrganization(User $user, Notification $model): bool
    {
        return $user->organization_id !== null
            && (string) $user->organization_id === (string) $model->organization_id;
    }
}
