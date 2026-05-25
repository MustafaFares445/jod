<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Models\Campaign;
use App\Models\User;
use App\Policies\Concerns\AuthorizesByPermissionGroup;

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

    public function approve(User $user, Campaign $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::APPROVE);
    }

    public function reject(User $user, Campaign $model): bool
    {
        return $this->authorizeAction($user, PermissionAction::REJECT);
    }
}
