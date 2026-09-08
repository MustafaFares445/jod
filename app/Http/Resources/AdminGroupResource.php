<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\GroupInvitation;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class AdminGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->relationLoaded('owner') ? $this->owner : null;
        $categories = $this->relationLoaded('categories') ? $this->categories->pluck('category')->values()->all() : [(string) $this->category];
        $members = $this->relationLoaded('memberships') ? $this->memberships : collect();
        $invitations = $this->relationLoaded('invitations') ? $this->invitations : collect();

        return [
            'id' => (string) $this->id,
            'name' => (string) $this->name,
            'description' => (string) $this->description,
            'category' => (string) ($categories[0] ?? $this->category),
            'categories' => $categories,
            'location' => $this->location,
            'status' => (string) $this->status,
            'purpose' => (string) $this->purpose,
            'rules' => array_values($this->rules ?? []),
            'requiresPostApproval' => (bool) $this->requires_post_approval,
            'imageUrl' => $this->relationLoaded('avatarMedia') ? $this->avatarMedia?->publicUrl() : null,
            'coverImageUrl' => $this->relationLoaded('coverMedia') ? $this->coverMedia?->publicUrl() : null,
            'organizationName' => $this->relationLoaded('organization') ? $this->organization?->name : null,
            'isVerifiedOrganization' => false,
            'ownerName' => $owner?->name,
            'owner' => $owner ? $this->person($owner, 'owner') : null,
            'members' => $members->filter(fn (GroupMember $member) => $member->status === 'active' && $member->user)
                ->map(fn (GroupMember $member) => $this->person($member->user, $member->role))->values()->all(),
            'invitations' => $invitations->map(fn (GroupInvitation $invitation) => [
                'id' => (string) $invitation->id,
                'status' => (string) $invitation->status,
                'user' => $invitation->invitedUser ? $this->person($invitation->invitedUser, 'member') : null,
                'createdAt' => $invitation->created_at?->toIso8601String(),
            ])->values()->all(),
            'proposedAdmins' => [],
            'membersCount' => (int) ($this->active_members_count ?? 0),
            'postsCount' => (int) ($this->posts_count ?? 0),
            'postsThisWeek' => (int) ($this->posts_this_week_count ?? 0),
            'rejectionReason' => $this->rejection_reason,
            'suspensionReason' => $this->suspension_reason,
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'reviewedAt' => $this->reviewed_at?->toIso8601String(),
            'reviewedBy' => $this->relationLoaded('reviewedBy') ? $this->reviewedBy?->name : null,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }

    private function person(User $user, string $role): array
    {
        return [
            'id' => (string) $user->id,
            'name' => (string) $user->name,
            'username' => filled($user->email) ? Str::before((string) $user->email, '@') : 'jod',
            'email' => $user->email,
            'role' => $role,
        ];
    }
}
