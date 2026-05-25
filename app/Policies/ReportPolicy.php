<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Report;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByPermissionGroup;

class ReportPolicy
{
    use AuthorizesByPermissionGroup;

    protected function permissionGroup(): PermissionGroup
    {
        return PermissionGroup::REPORT;
    }

    public function viewAny(User $user): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function view(User $user, Report $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::VIEW);
    }

    public function claim(User $user, Report $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::CLAIM);
    }

    public function requestInfo(User $user, Report $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::REQUEST_INFO);
    }

    public function close(User $user, Report $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::CLOSE);
    }
}
