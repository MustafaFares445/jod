<?php

declare(strict_types=1);

namespace App\Enums;

enum PermissionModule: string
{
    case CORE = 'core';
    case ADMIN = 'admin';
    case ORGANIZATION = 'organization';

    public function label(): string
    {
        return match ($this) {
            self::CORE => 'Core',
            self::ADMIN => 'Admin',
            self::ORGANIZATION => 'Organization',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::CORE => 10,
            self::ADMIN => 20,
            self::ORGANIZATION => 30,
        };
    }
}
