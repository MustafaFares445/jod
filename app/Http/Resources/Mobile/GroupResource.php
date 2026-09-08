<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use App\Models\GroupInvitation;
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
        $categories = $this->relationLoaded('categories')
            ? $this->categories->pluck('category')->values()->all()
            : [(string) $this->category];
        $isOwner = $viewer instanceof User && (string) $this->owner_id === (string) $viewer->id;

        return [
            'id' => (string) $this->id,
            'name' => (string) $this->name,
            'description' => (string) $this->description,
            'category' => (string) ($categories[0] ?? $this->category),
            'categories' => $categories,
            'location' => (string) ($this->location ?? ''),
            'membersCount' => (int) ($this->active_members_count ?? 0),
            'postsThisWeek' => (int) ($this->posts_this_week_count ?? 0),
            'postsCount' => (int) ($this->posts_count ?? 0),
            'isMember' => $membership !== null,
            'myRole' => $membership?->role,
            'isOwner' => $isOwner,
            'canManage' => $isOwner,
            'canCreatePost' => $membership !== null && $this->status === 'active',
            'canCreateCampaign' => $isOwner && $this->status === 'active',
            'requiresPostApproval' => (bool) $this->requires_post_approval,
            'imageUrl' => $this->relationLoaded('avatarMedia') ? $this->avatarMedia?->publicUrl() : null,
            'coverImageUrl' => $this->relationLoaded('coverMedia') ? $this->coverMedia?->publicUrl() : null,
            'organizationName' => $this->relationLoaded('organization') ? $this->organization?->name : null,
            'isVerifiedOrganization' => false,
            'rules' => array_values($this->rules ?? []),
            'status' => (string) $this->status,
            'rejectionReason' => $this->rejection_reason,
            'suspensionReason' => $this->suspension_reason,
            'createdAt' => $this->created_at?->toIso8601String(),
            'createdAtLabel' => $this->created_at?->diffForHumans() ?? '',
            'owner' => $owner ? $this->person($owner, 'owner') : null,
            'admins' => $members
                ->filter(fn (GroupMember $member) => in_array($member->role, ['admin', 'moderator'], true) && $member->status === 'active' && $member->user)
                ->map(fn (GroupMember $member) => $this->person($member->user, $member->role))
                ->values()->all(),
            'membersPreview' => $members
                ->filter(fn (GroupMember $member) => $member->status === 'active' && $member->user)
                ->take(5)
                ->map(fn (GroupMember $member) => $this->person($member->user, $member->role))
                ->values()->all(),
            'invitations' => $isOwner && $this->relationLoaded('invitations')
                ? $this->invitations->map(fn (GroupInvitation $invitation) => [
                    'id' => (string) $invitation->id,
                    'status' => (string) $invitation->status,
                    'user' => $invitation->invitedUser ? $this->person($invitation->invitedUser, 'member') : null,
                    'createdAt' => $invitation->created_at?->toIso8601String(),
                ])->values()->all()
                : [],
        ];
    }

    private function person(User $user, string $role): array
    {
        return [
            'id' => (string) $user->id,
            'name' => (string) $user->name,
            'username' => filled($user->email) ? Str::before((string) $user->email, '@') : 'jod',
            'email' => $user->email,
            'avatarUrl' => $user->relationLoaded('avatarMedia') ? $user->avatarMedia?->publicUrl() : null,
            'role' => $role,
        ];
    }
}
