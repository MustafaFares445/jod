<?php

declare(strict_types=1);

namespace App\Http\Resources\Org;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->name,
            'description' => $this->description,
            'permissions' => $this->permissions ?? [],
            'isActive' => $this->is_active,
            'isSystem' => $this->is_system,
            'membersCount' => $this->members_count,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
