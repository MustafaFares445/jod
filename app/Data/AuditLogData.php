<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Exists;

class AuditLogData extends Data
{
    public function __construct(
        #[Exists('users', 'id')]
        public int $actorUserId,
        #[Max(255)]
        public string $action,
        #[Max(255)]
        public string $entityType,
        public int $entityId,
        public ?array $metadata = null,
    ) {}
}
