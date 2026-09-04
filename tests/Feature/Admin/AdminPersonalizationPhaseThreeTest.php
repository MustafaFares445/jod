<?php

declare(strict_types=1);

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Support\Permissions\PermissionNameResolver;

it('registers help matching permissions for phase three', function () {
    expect(PermissionNameResolver::resolve(PermissionGroup::HELP_MATCH, PermissionAction::VIEW))
        ->toBe('help_matching.view');
});
