<?php

declare(strict_types=1);

namespace App\Http\Resources;

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
            'postsCount' => $this->posts_count,
            'reportsCount' => $this->reports_count,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
            'lastActiveAt' => $this->last_active_at?->toIso8601String(),
        ];
    }
}
