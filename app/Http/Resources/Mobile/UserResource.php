<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $organization = $this->relationLoaded('organization') ? $this->organization : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username(),
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'bio' => $this->bio,
            'avatarUrl' => $this->relationLoaded('avatarMedia') ? $this->avatarMedia?->publicUrl() : null,
            'verified' => $organization !== null
                ? $organization->verification_status === 'verified'
                : $this->email_verified_at !== null,
            'userType' => $this->user_type,
            'status' => $this->status,
            'organizationId' => $this->organization_id,
            'organization' => $this->whenLoaded('organization', fn (): ?array => $this->organization ? [
                'id' => $this->organization->id,
                'name' => $this->organization->name,
                'email' => $this->organization->email,
                'phone' => $this->organization->phone,
                'city' => $this->organization->location,
                'bio' => $this->organization->description,
                'status' => $this->organization->status,
                'verificationStatus' => $this->organization->verification_status,
            ] : null),
            'stats' => [
                'postsCount' => (int) ($this->posts_count ?? 0),
                'savedCount' => (int) ($this->saved_posts_count ?? 0),
                'donationsCount' => (int) ($this->donations_count ?? 0),
            ],
            'createdAt' => $this->created_at?->toIso8601String(),
            'lastActiveAt' => $this->last_active_at?->toIso8601String(),
        ];
    }

    private function username(): string
    {
        if (filled($this->email)) {
            return Str::before((string) $this->email, '@');
        }

        $slug = Str::slug((string) $this->name, '.');

        return $slug !== '' ? $slug : 'jod';
    }
}
