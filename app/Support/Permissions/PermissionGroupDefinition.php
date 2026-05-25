<?php

declare(strict_types=1);

namespace App\Support\Permissions;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;

final readonly class PermissionGroupDefinition
{
    /**
     * @param list<PermissionAction>|null $actions
     */
    public function __construct(
        public string $label,
        public PermissionModule $module,
        public string $description,
        public int $order,
        public ?string $sectionLabel = null,
        public ?array $actions = null,
    ) {}
}
