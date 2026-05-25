<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class AuditLogService
{
    public function log(int $userId, string $action, string $entityType, int $entityId, ?array $metadata = null): void
    {
        DB::transaction(static function () use ($userId, $action, $entityType, $entityId, $metadata) {
            AuditLog::create([
                'actor_user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'metadata' => $metadata,
                'at' => now(),
            ]);
        });
    }
}
