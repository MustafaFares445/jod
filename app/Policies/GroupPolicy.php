<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Group;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByPermissionGroup;

class GroupPolicy
{
    use AuthorizesByPermissionGroup;

    protected function permissionGroup(): PermissionGroup
    {
        return PermissionGroup::GROUP;
    }

    public function viewAny(User $user): bool { return $this->authorizeAction($user, PermissionAction::VIEW); }
    public function view(User $user, Group $group): bool { return $this->authorizeAction($user, PermissionAction::VIEW); }
    public function approve(User $user, Group $group): bool { return $this->authorizeAction($user, PermissionAction::APPROVE); }
    public function reject(User $user, Group $group): bool { return $this->authorizeAction($user, PermissionAction::REJECT); }
    public function delete(User $user, Group $group): bool { return $this->authorizeAction($user, PermissionAction::DELETE); }
}
