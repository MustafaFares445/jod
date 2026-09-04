<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CapabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => (string) $this->name,
            'slug' => (string) $this->slug,
            'status' => (string) $this->status,
            'sortOrder' => (int) $this->sort_order,
            'usersCount' => (int) ($this->users_count ?? 0),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
