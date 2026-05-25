<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'user' => [
                'id' => $this->actor?->id,
                'name' => $this->actor?->name,
                'email' => $this->actor?->email,
            ],
            'at' => $this->at?->toIso8601String(),
            'entityType' => $this->entity_type,
            'entityId' => $this->entity_id,
            'metadata' => $this->metadata,
        ];
    }
}
