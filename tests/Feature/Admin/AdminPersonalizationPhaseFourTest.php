<?php

declare(strict_types=1);

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Support\Permissions\PermissionNameResolver;

it('keeps recommendation admin access read only and diagnostic', function () {
    expect(PermissionNameResolver::resolve(PermissionGroup::RECOMMENDATION, PermissionAction::VIEW))
        ->toBe('recommendations.view');
    expect(PermissionNameResolver::resolve(PermissionGroup::RECOMMENDATION, PermissionAction::DIAGNOSTICS))
        ->toBe('recommendations.diagnostics');
});
