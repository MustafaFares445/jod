<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\User;

class AdminAuditService
{
    public function record(User $actor, string $action, string $entityType, string $entityId, array $old = [], array $new = []): void
    {
        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => ['old' => $old, 'new' => $new, 'source' => 'admin-personalization'],
            'at' => now(),
        ]);
    }
}
