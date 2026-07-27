<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'userType' => $this->user_type,
            'status' => $this->status,
            'organizationId' => $this->organization_id,
            'organization' => $this->whenLoaded('organization', fn (): ?array => $this->organization ? [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'email' => $this->organization->email,
                'phone' => $this->organization->phone,
                'status' => $this->organization->status,
                'verificationStatus' => $this->organization->verification_status,
            ] : null),
            'createdAt' => $this->created_at?->toIso8601String(),
            'lastActiveAt' => $this->last_active_at?->toIso8601String(),
        ];
    }
}
