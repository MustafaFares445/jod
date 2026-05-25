<?php

declare(strict_types=1);

namespace App\Http\Resources\Org;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role?->name,
            'status' => $this->status,
            'invitedAt' => $this->invited_at?->toIso8601String(),
            'acceptedAt' => $this->accepted_at?->toIso8601String(),
        ];
    }
}
