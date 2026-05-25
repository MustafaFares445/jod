<?php

declare(strict_types=1);

namespace App\Support\Permissions;

use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use InvalidArgumentException;

final class PermissionNameResolver
{
    public static function resolve(
        PermissionGroup|string $group,
        PermissionAction|string $action,
    ): string {
        $groupValue = $group instanceof PermissionGroup ? $group->value : $group;
        $actionValue = $action instanceof PermissionAction ? $action->value : $action;

        $permissionName = self::normalize("{$groupValue}.{$actionValue}");

        self::assertSupportedDepth($permissionName);

        return $permissionName;
    }

    public static function normalize(string $permissionName): string
    {
        return trim(preg_replace('/\.+/', '.', strtolower($permissionName)), '.');
    }

    public static function depth(string $permissionName): int
    {
        return substr_count(self::normalize($permissionName), '.') + 1;
    }

    public static function assertSupportedDepth(string $permissionName): void
    {
        $depth = self::depth($permissionName);

        if (! in_array($depth, [2, 3], true)) {
            throw new InvalidArgumentException(
                "Permission [{$permissionName}] must contain 2 or 3 layers."
            );
        }
    }
}
