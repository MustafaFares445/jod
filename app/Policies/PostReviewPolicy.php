<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Post;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByPermissionGroup;

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

    public function approve(User $user, Post $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::APPROVE);
    }

    public function reject(User $user, Post $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::REJECT);
    }
}
