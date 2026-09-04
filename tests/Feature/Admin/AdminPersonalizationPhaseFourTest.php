<?php

declare(strict_types=1);

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Support\Permissions\PermissionNameResolver;

it('registers recommendation view and update permissions for phase four', function () {
    expect(PermissionNameResolver::resolve(PermissionGroup::RECOMMENDATION, PermissionAction::VIEW))
        ->toBe('recommendations.view');
    expect(PermissionNameResolver::resolve(PermissionGroup::RECOMMENDATION, PermissionAction::UPDATE))
        ->toBe('recommendations.update');
});
