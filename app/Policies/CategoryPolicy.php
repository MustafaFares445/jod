<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Category;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByPermissionGroup;

class CategoryPolicy
{
    use AuthorizesByPermissionGroup;

    protected function permissionGroup(): PermissionGroup
    {
        return PermissionGroup::CATEGORY;
    }

    public function viewAny(User $user): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function view(User $user, Category $category): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function create(User $user): bool
    {
        return $this->authorizeAction($user, PermissionAction::CREATE);
    }

    public function update(User $user, Category $category): bool
    {
        return $this->authorizeAction($user, PermissionAction::UPDATE);
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->authorizeAction($user, PermissionAction::DELETE);
    }
}
