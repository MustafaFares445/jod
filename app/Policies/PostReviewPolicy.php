<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Post;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByPermissionGroup;
use App\Support\Permissions\PermissionNameResolver;

class PostReviewPolicy
{
    use AuthorizesByPermissionGroup;

    protected function permissionGroup(): PermissionGroup
    {
        return PermissionGroup::POST_REVIEW;
    }

    public function viewAny(User $user): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function view(User $user, Post $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function viewAnyOrganization(User $user): bool
    {
        return $user->organization_id !== null
            && $this->authorizeOrganizationAction($user, PermissionAction::VIEW);
    }

    public function viewOrganization(User $user, Post $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::VIEW);
    }

    public function approve(User $user, Post $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::APPROVE);
    }

    public function reject(User $user, Post $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::REJECT);
    }

    public function createOrganization(User $user): bool
    {
        return $user->organization_id !== null
            && $this->authorizeOrganizationAction($user, PermissionAction::CREATE);
    }

    public function updateOrganization(User $user, Post $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::UPDATE);
    }

    public function deleteOrganization(User $user, Post $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::DELETE);
    }

    public function publishOrganization(User $user, Post $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::PUBLISH);
    }

    public function archiveOrganization(User $user, Post $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::ARCHIVE);
    }

    public function restoreOrganization(User $user, Post $model): bool
    {
        return $this->sameOrganization($user, $model)
            && $this->authorizeOrganizationAction($user, PermissionAction::RESTORE);
    }

    public function viewOwn(User $user, Post $model): bool
    {
        return $this->ownsPost($user, $model);
    }

    public function createOwn(User $user): bool
    {
        return $user->id !== null;
    }

    public function updateOwn(User $user, Post $model): bool
    {
        return $this->ownsPost($user, $model)
            && in_array($model->status, ['draft', 'rejected'], true);
    }

    public function submitOwn(User $user, Post $model): bool
    {
        return $this->ownsPost($user, $model)
            && in_array($model->status, ['draft', 'rejected'], true);
    }

    public function archiveOwn(User $user, Post $model): bool
    {
        return $this->ownsPost($user, $model)
            && $model->status === 'published';
    }

    public function repostOwn(User $user, Post $model): bool
    {
        return $this->ownsPost($user, $model)
            && $model->status === 'archived';
    }

    public function deleteOwn(User $user, Post $model): bool
    {
        return $this->ownsPost($user, $model);
    }

    private function authorizeOrganizationAction(User $user, PermissionAction $action): bool
    {
        return $user->isOrganizationOwner()
            || $user->can(PermissionNameResolver::resolve(PermissionGroup::ORG_POST, $action));
    }

    private function sameOrganization(User $user, Post $model): bool
    {
        return $user->organization_id !== null
            && (string) $user->organization_id === (string) $model->organization_id;
    }

    private function ownsPost(User $user, Post $model): bool
    {
        return $model->author_id !== null
            && (string) $user->id === (string) $model->author_id;
    }
}
