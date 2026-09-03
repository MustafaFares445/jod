<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class GroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user('sanctum');
        $membership = $viewer instanceof User && $this->relationLoaded('memberships')
            ? $this->memberships->first(fn (GroupMember $member) => (string) $member->user_id === (string) $viewer->id && $member->status === 'active')
            : null;
        $owner = $this->relationLoaded('owner') ? $this->owner : null;
        $members = $this->relationLoaded('memberships') ? $this->memberships : collect();

        return [
            'id' => (string) $this->id,
            'name' => (string) $this->name,
            'description' => (string) $this->description,
            'category' => (string) $this->category,
            'location' => (string) ($this->location ?? ''),
            'membersCount' => (int) ($this->active_members_count ?? 0),
            'postsThisWeek' => (int) ($this->posts_this_week_count ?? 0),
            'postsCount' => (int) ($this->posts_count ?? 0),
            'isMember' => $membership !== null,
            'myRole' => $membership?->role,
            'imageUrl' => $this->relationLoaded('avatarMedia') ? $this->avatarMedia?->publicUrl() : null,
            'coverImageUrl' => $this->relationLoaded('coverMedia') ? $this->coverMedia?->publicUrl() : null,
            'organizationName' => $this->relationLoaded('organization') ? $this->organization?->name : null,
            'isVerifiedOrganization' => $this->relationLoaded('organization') && $this->organization !== null
                ? $this->organization->verification_status === 'verified'
                : false,
            'rules' => array_values($this->rules ?? []),
            'status' => (string) $this->status,
            'rejectionReason' => $this->rejection_reason,
            'createdAt' => $this->created_at?->toIso8601String(),
            'createdAtLabel' => $this->created_at?->diffForHumans() ?? '',
            'owner' => $owner ? $this->person($owner, 'owner') : null,
            'admins' => $members
                ->filter(fn (GroupMember $member) => in_array($member->role, ['admin', 'moderator'], true) && $member->status === 'active' && $member->user)
                ->map(fn (GroupMember $member) => $this->person($member->user, $member->role))
                ->values()
                ->all(),
            'membersPreview' => $members
                ->filter(fn (GroupMember $member) => $member->status === 'active' && $member->user)
                ->take(5)
                ->map(fn (GroupMember $member) => $this->person($member->user, $member->role))
                ->values()
                ->all(),
        ];
    }

    private function person(User $user, string $role): array
    {
        return [
            'id' => (string) $user->id,
            'name' => (string) $user->name,
            'username' => filled($user->email) ? Str::before((string) $user->email, '@') : 'jod',
            'avatarUrl' => $user->relationLoaded('avatarMedia') ? $user->avatarMedia?->publicUrl() : null,
            'role' => $role,
        ];
    }
}
