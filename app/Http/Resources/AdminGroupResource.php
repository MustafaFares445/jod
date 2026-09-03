<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class AdminGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->relationLoaded('owner') ? $this->owner : null;
        $proposed = $this->relationLoaded('proposedAdmins') ? $this->getRelation('proposedAdmins') : collect();

        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'location' => $this->location,
            'membersCount' => (int) ($this->active_members_count ?? 0),
            'postsThisWeek' => (int) ($this->posts_this_week_count ?? 0),
            'postsCount' => (int) ($this->posts_count ?? 0),
            'imageUrl' => $this->relationLoaded('avatarMedia') ? $this->avatarMedia?->publicUrl() : null,
            'organizationName' => $this->relationLoaded('organization') ? $this->organization?->name : null,
            'isVerifiedOrganization' => $this->relationLoaded('organization') && $this->organization
                ? $this->organization->verification_status === 'verified'
                : false,
            'ownerName' => $owner?->name,
            'status' => $this->status,
            'rejectionReason' => $this->rejection_reason,
            'suspensionReason' => $this->suspension_reason,
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'reviewedAt' => $this->reviewed_at?->toIso8601String(),
            'reviewedBy' => $this->relationLoaded('reviewedBy') ? $this->reviewedBy?->name : null,
            'rules' => array_values($this->rules ?? []),
            'purpose' => $this->purpose,
            'owner' => $owner ? $this->person($owner, 'owner') : null,
            'proposedAdmins' => $proposed->map(fn (User $user) => $this->person($user, 'admin'))->values()->all(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    private function person(User $user, string $role): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'username' => filled($user->email) ? Str::before((string) $user->email, '@') : 'jod',
            'role' => $role,
        ];
    }
}
