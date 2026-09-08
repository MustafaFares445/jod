<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\NotificationEventType;
use App\Models\Campaign;
use App\Models\Group;
use App\Models\GroupCategory;
use App\Models\GroupComment;
use App\Models\GroupInvitation;
use App\Models\GroupMember;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostPoll;
use App\Models\PostPollOption;
use App\Models\PostPollVote;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GroupService
{
    public function __construct(private readonly NotificationEventService $notifications) {}

    public function paginate(array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $search = trim((string) ($params['search'] ?? ''));
        $category = trim((string) ($params['category'] ?? ''));
        $location = trim((string) ($params['location'] ?? ''));

        return $this->basePublicQuery()
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($category !== '', fn (Builder $query) => $query->whereHas('categories', fn (Builder $categoryQuery) => $categoryQuery->where('category', $category)))
            ->when($location !== '', fn (Builder $query) => $query->where('location', 'like', "%{$location}%"))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function mine(User $user, array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));
        $scope = (string) ($params['scope'] ?? 'all');
        $query = Group::query()->with($this->relations())->withCount($this->counts());

        if ($scope === 'owned') {
            $query->where('owner_id', $user->id);
        } elseif ($scope === 'joined') {
            $query->where('owner_id', '!=', $user->id)
                ->whereHas('memberships', fn (Builder $member) => $member
                    ->where('user_id', $user->id)
                    ->where('status', 'active'));
        } else {
            $query->where(function (Builder $builder) use ($user): void {
                $builder->where('owner_id', $user->id)
                    ->orWhereHas('memberships', fn (Builder $member) => $member
                        ->where('user_id', $user->id)
                        ->where('status', 'active'));
            });
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function create(User $owner, array $data, ?UploadedFile $avatar = null, ?UploadedFile $cover = null): Group
    {
        return DB::transaction(function () use ($owner, $data, $avatar, $cover): Group {
            $categories = $this->normalizedCategories($data);
            $group = Group::query()->create([
                'owner_id' => $owner->id,
                'organization_id' => null,
                'name' => $data['name'],
                'description' => $data['description'],
                'category' => $categories[0],
                'location' => $data['location'] ?? null,
                'status' => 'pending',
                'purpose' => $data['purpose'],
                'rules' => array_values(array_filter(array_map('trim', $data['rules'] ?? []))),
                'requires_post_approval' => (bool) ($data['requiresPostApproval'] ?? false),
                'proposed_admin_ids' => [],
                'submitted_at' => now(),
            ]);

            foreach ($categories as $category) {
                GroupCategory::query()->create(['group_id' => $group->id, 'category' => $category]);
            }

            GroupMember::query()->create([
                'group_id' => $group->id,
                'user_id' => $owner->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            if ($avatar !== null) $this->storeMedia($group, $avatar, 'avatar');
            if ($cover !== null) $this->storeMedia($group, $cover, 'cover');

            $this->inviteUsers($group, $owner, $data['invitedUserIds'] ?? [], false);

            $this->notifications->notifyAdmins(
                NotificationEventType::GroupSubmitted,
                'طلب إنشاء فريق تطوعي جديد',
                "أرسل {$owner->name} طلب إنشاء الفريق «{$group->name}» للمراجعة.",
                'group',
                'high',
                $group->name,
                '/dashboard/admin/groups/review',
                (string) $owner->id,
            );

            $this->notifications->notifyUser(
                $owner,
                NotificationEventType::GroupSubmitted,
                'تم استلام طلب إنشاء الفريق التطوعي',
                "طلب إنشاء {$group->name} قيد مراجعة إدارة جود الآن.",
                'group',
                'normal',
                $group->name,
                "/groups/{$group->id}",
            );

            return $this->loadGroup($group);
        });
    }

    public function update(Group $group, User $actor, array $data, ?UploadedFile $avatar = null, ?UploadedFile $cover = null): Group
    {
        $this->ensureOwner($group, $actor);
        return DB::transaction(function () use ($group, $actor, $data, $avatar, $cover): Group {
            $attributes = [];
            foreach (['name', 'description', 'location', 'purpose'] as $key) {
                if (array_key_exists($key, $data)) $attributes[$key] = $data[$key];
            }
            if (array_key_exists('rules', $data)) {
                $attributes['rules'] = array_values(array_filter(array_map('trim', $data['rules'] ?? [])));
            }
            if (array_key_exists('requiresPostApproval', $data)) {
                $attributes['requires_post_approval'] = (bool) $data['requiresPostApproval'];
            }
            if ($attributes !== []) $group->update($attributes);

            if (array_key_exists('categories', $data) || array_key_exists('category', $data)) {
                $categories = $this->normalizedCategories($data);
                $group->categories()->delete();
                foreach ($categories as $category) GroupCategory::query()->create(['group_id' => $group->id, 'category' => $category]);
                $group->update(['category' => $categories[0]]);
            }

            if ($avatar !== null) $this->storeMedia($group, $avatar, 'avatar', true);
            if ($cover !== null) $this->storeMedia($group, $cover, 'cover', true);
            if (array_key_exists('invitedUserIds', $data)) $this->inviteUsers($group, $actor, $data['invitedUserIds'], true);

            return $this->loadGroup($group->refresh());
        });
    }

    public function deleteOwned(Group $group, User $actor): void
    {
        $this->ensureOwner($group, $actor);
        $group->delete();
    }

    public function loadGroup(Group $group): Group
    {
        return $group->load($this->relations())->loadCount($this->counts());
    }

    public function join(Group $group, User $user): Group
    {
        $this->ensureActive($group);
        GroupMember::query()->updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $user->id],
            ['role' => (string) $group->owner_id === (string) $user->id ? 'owner' : 'member', 'status' => 'active', 'joined_at' => now(), 'left_at' => null],
        );
        return $this->loadGroup($group->refresh());
    }

    public function leave(Group $group, User $user): Group
    {
        $membership = GroupMember::query()->where('group_id', $group->id)->where('user_id', $user->id)->first();
        if ($membership?->role === 'owner') throw ValidationException::withMessages(['group' => ['The owner cannot leave the group.']]);
        $membership?->update(['status' => 'left', 'left_at' => now()]);
        return $this->loadGroup($group->refresh());
    }

    public function removeMember(Group $group, User $actor, string $userId): Group
    {
        $this->ensureOwner($group, $actor);
        if ((string) $group->owner_id === $userId) throw ValidationException::withMessages(['member' => ['The group owner cannot be removed.']]);
        $membership = GroupMember::query()->where('group_id', $group->id)->where('user_id', $userId)->firstOrFail();
        $membership->update(['status' => 'removed', 'left_at' => now()]);
        $this->notifications->notifyUser($userId, NotificationEventType::GroupMemberRemoved, 'تمت إزالتك من الفريق', "أزالك مدير الفريق من {$group->name}.", 'group', 'normal', $group->name, "/groups/{$group->id}", null, (string) $actor->id);
        return $this->loadGroup($group->refresh());
    }

    /** @return Collection<int, GroupInvitation> */
    public function invitationsForUser(User $user): Collection
    {
        return GroupInvitation::query()
            ->where('invited_user_id', $user->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->with(['group.avatarMedia', 'inviter.avatarMedia'])
            ->orderByDesc('created_at')->get();
    }

    public function respondInvitation(GroupInvitation $invitation, User $user, bool $accept): GroupInvitation
    {
        if ((string) $invitation->invited_user_id !== (string) $user->id) abort(403);
        if ($invitation->status !== 'pending') return $invitation->load('group');

        return DB::transaction(function () use ($invitation, $user, $accept): GroupInvitation {
            $invitation->update(['status' => $accept ? 'accepted' : 'declined', 'responded_at' => now()]);
            $group = $invitation->group()->firstOrFail();
            if ($accept && $group->status === 'active') {
                GroupMember::query()->updateOrCreate(
                    ['group_id' => $group->id, 'user_id' => $user->id],
                    ['role' => 'member', 'status' => 'active', 'joined_at' => now(), 'left_at' => null],
                );
            }
            $this->notifications->notifyUser(
                $group->owner_id,
                $accept ? NotificationEventType::GroupInvitationAccepted : NotificationEventType::GroupInvitationDeclined,
                $accept ? 'تم قبول دعوة الفريق' : 'تم رفض دعوة الفريق',
                $accept ? "وافق {$user->name} على الانضمام إلى {$group->name}." : "رفض {$user->name} دعوة الانضمام إلى {$group->name}.",
                'group', 'normal', $group->name, "/groups/{$group->id}", null, (string) $user->id,
            );
            return $invitation->refresh()->load('group');
        });
    }

    /** @param list<string> $userIds */
    public function invite(Group $group, User $actor, array $userIds): array
    {
        $this->ensureOwner($group, $actor);
        return $this->inviteUsers($group, $actor, $userIds, true)->values()->all();
    }

    public function posts(Group $group, ?User $viewer, string $status = 'published', int $perPage = 20): LengthAwarePaginator
    {
        $query = Post::query()->where('group_id', $group->id)->with($this->postRelations($viewer));
        $isOwner = $viewer !== null && (string) $group->owner_id === (string) $viewer->id;

        if ($status === 'pending') {
            if (! $isOwner) abort(403);
            $query->where('group_review_status', 'pending');
        } elseif ($status === 'rejected') {
            if (! $isOwner) abort(403);
            $query->where('group_review_status', 'rejected');
        } else {
            $query->where('status', 'published')->where(function (Builder $builder): void {
                $builder->whereNull('group_review_status')->orWhere('group_review_status', 'approved');
            });
        }

        return $query->orderByDesc('published_at')->orderByDesc('created_at')->paginate(max(1, min($perPage, 100)));
    }

    /** Backward-compatible quick text post endpoint. New clients use /posts with groupId. */
    public function createPost(Group $group, User $user, string $body): Post
    {
        $this->ensureMember($group, $user);
        $requiresReview = (bool) $group->requires_post_approval && (string) $group->owner_id !== (string) $user->id;
        $post = Post::query()->create([
            'group_id' => $group->id,
            'author_id' => $user->id,
            'updated_by' => $user->id,
            'summary' => mb_substr($body, 0, 255),
            'content' => $body,
            'type' => 'awareness',
            'status' => $requiresReview ? 'pending' : 'published',
            'group_review_status' => $requiresReview ? 'pending' : 'approved',
            'location' => $group->location,
            'submitted_at' => now(),
            'published_at' => $requiresReview ? null : now(),
        ]);
        if ($requiresReview) $this->notifyOwnerForPostReview($group, $post, $user);
        return $post->load($this->postRelations($user));
    }

    public function approvePost(Group $group, Post $post, User $actor): Post
    {
        $this->ensureOwner($group, $actor);
        $this->ensureGroupPost($group, $post);
        if ($post->group_review_status !== 'pending') throw ValidationException::withMessages(['post' => ['Only pending group posts can be approved.']]);
        $post->update(['status' => 'published', 'group_review_status' => 'approved', 'group_rejection_reason' => null, 'published_at' => now(), 'reviewed_at' => now(), 'reviewed_by' => $actor->id]);
        $this->notifications->notifyUser($post->author_id, NotificationEventType::GroupPostApproved, 'تم قبول منشورك في الفريق', "تم نشر منشورك في {$group->name}.", 'group', 'normal', $group->name, "/groups/{$group->id}", null, (string) $actor->id);
        return $post->load($this->postRelations($actor));
    }

    public function rejectPost(Group $group, Post $post, User $actor, string $reason): Post
    {
        $this->ensureOwner($group, $actor);
        $this->ensureGroupPost($group, $post);
        if ($post->group_review_status !== 'pending') throw ValidationException::withMessages(['post' => ['Only pending group posts can be rejected.']]);
        $post->update(['status' => 'blocked', 'group_review_status' => 'rejected', 'group_rejection_reason' => $reason, 'block_reason' => $reason, 'blocked_at' => now(), 'blocked_by' => $actor->id, 'reviewed_at' => now(), 'reviewed_by' => $actor->id]);
        $this->notifications->notifyUser($post->author_id, NotificationEventType::GroupPostRejected, 'تم رفض منشورك في الفريق', "تم رفض منشورك في {$group->name}: {$reason}", 'group', 'high', $group->name, "/groups/{$group->id}", null, (string) $actor->id);
        return $post->load($this->postRelations($actor));
    }

    public function deletePost(Group $group, Post $post, User $actor): void
    {
        $this->ensureGroupPost($group, $post);
        if ((string) $post->author_id !== (string) $actor->id && (string) $group->owner_id !== (string) $actor->id) abort(403);
        $post->delete();
    }

    public function createComment(Post $post, User $user, string $body, ?string $parentId): GroupComment
    {
        $group = $post->group()->firstOrFail();
        $this->ensureMember($group, $user);
        $rootId = null;
        if ($parentId !== null) {
            $parent = GroupComment::query()->where('post_id', $post->id)->whereKey($parentId)->firstOrFail();
            $rootId = $parent->parent_id ?: $parent->id;
        }
        return GroupComment::query()->create(['post_id' => $post->id, 'author_id' => $user->id, 'parent_id' => $rootId, 'body' => $body, 'status' => 'published']);
    }

    public function setPostLike(Post $post, User $user, bool $liked): array
    {
        $group = $post->group()->firstOrFail();
        $this->ensureMember($group, $user);
        return DB::transaction(function () use ($post, $user, $liked): array {
            $query = DB::table('post_likes')->where('post_id', $post->id)->where('user_id', $user->id);
            $exists = $query->exists();
            if ($liked && ! $exists) {
                DB::table('post_likes')->insert(['id' => (string) Str::uuid(), 'post_id' => $post->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
                $post->increment('reactions_count');
            } elseif (! $liked && $exists) {
                $query->delete();
                $post->update(['reactions_count' => max(0, (int) $post->reactions_count - 1)]);
            }
            return ['isLiked' => $liked, 'likesCount' => (int) $post->refresh()->reactions_count];
        });
    }

    public function setCommentLike(GroupComment $comment, User $user, bool $liked): array
    {
        $this->ensureMember($comment->post->group, $user);
        return DB::transaction(function () use ($comment, $user, $liked): array {
            $query = DB::table('group_comment_likes')->where('comment_id', $comment->id)->where('user_id', $user->id);
            $exists = $query->exists();
            if ($liked && ! $exists) {
                $query = DB::table('group_comment_likes');
                $query->insert(['comment_id' => $comment->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
                $comment->increment('likes_count');
            } elseif (! $liked && $exists) {
                $query->delete();
                $comment->update(['likes_count' => max(0, (int) $comment->likes_count - 1)]);
            }
            return ['isLiked' => $liked, 'likesCount' => (int) $comment->refresh()->likes_count];
        });
    }

    public function vote(Post $post, User $user, array $optionIds): Post
    {
        $group = $post->group()->firstOrFail();
        $this->ensureMember($group, $user);
        $poll = $post->poll()->with('options')->firstOrFail();
        if ($poll->ends_at?->isPast()) throw ValidationException::withMessages(['poll' => ['This poll is closed.']]);
        $valid = $poll->options->whereIn('id', $optionIds)->pluck('id')->values();
        if ($valid->isEmpty() || $valid->count() !== count(array_unique($optionIds))) throw ValidationException::withMessages(['optionIds' => ['Choose valid poll options.']]);
        if (! $poll->allows_multiple_choices && $valid->count() !== 1) throw ValidationException::withMessages(['optionIds' => ['This poll accepts one choice only.']]);

        DB::transaction(function () use ($poll, $user, $valid): void {
            PostPollVote::query()->where('poll_id', $poll->id)->where('user_id', $user->id)->delete();
            foreach ($valid as $optionId) PostPollVote::query()->create(['poll_id' => $poll->id, 'option_id' => $optionId, 'user_id' => $user->id]);
            foreach ($poll->options as $option) $option->update(['votes_count' => PostPollVote::query()->where('option_id', $option->id)->count()]);
        });
        return $post->load($this->postRelations($user));
    }

    public function recommendations(Group $group): array
    {
        $categories = $group->categories()->pluck('category');
        $groups = Group::query()->where('status', 'active')->whereKeyNot($group->id)
            ->whereHas('categories', fn (Builder $query) => $query->whereIn('category', $categories))->limit(3)->get();
        $campaigns = Campaign::query()->with(['organization', 'group', 'category'])->where('status', 'active')->limit(3)->get();
        return [
            ...$groups->map(fn (Group $candidate) => ['id' => 'group-'.$candidate->id, 'kind' => 'group', 'title' => $candidate->name, 'subtitle' => 'فريق تطوعي', 'category' => $candidate->category, 'location' => $candidate->location ?? '', 'reason' => 'يتقاطع مع اهتمامات هذا الفريق', 'metaLabel' => null, 'targetGroupId' => $candidate->id])->all(),
            ...$campaigns->map(fn (Campaign $campaign) => ['id' => 'campaign-'.$campaign->id, 'kind' => 'campaign', 'title' => $campaign->title, 'subtitle' => $campaign->group?->name ?? $campaign->organization?->name ?? 'حملة جود', 'category' => $campaign->category?->name ?? '', 'location' => $campaign->location ?? '', 'reason' => 'حملة مقترحة للفريق', 'metaLabel' => null])->all(),
        ];
    }

    public function createCampaign(Group $group, User $actor, array $data): Campaign
    {
        $this->ensureOwner($group, $actor);
        return Campaign::query()->create([
            'group_id' => $group->id,
            'organization_id' => null,
            'creator_id' => $actor->id,
            'title' => $data['title'],
            'summary' => $data['summary'] ?? null,
            'content' => $data['content'] ?? $data['summary'] ?? null,
            'category_id' => $data['categoryId'] ?? null,
            'audience' => $data['audience'] ?? 'general',
            'status' => 'active',
            'location' => $data['location'] ?? $group->location,
            'goal_amount' => $data['goalAmount'] ?? 0,
            'start_date' => $data['startDate'] ?? now()->toDateString(),
            'end_date' => $data['endDate'] ?? null,
            'submitted_at' => now(),
        ]);
    }

    public function approve(Group $group, User $reviewer): Group
    {
        if (! in_array($group->status, ['pending', 'rejected'], true)) throw ValidationException::withMessages(['group' => ['Only pending or rejected group requests can be approved.']]);
        return DB::transaction(function () use ($group, $reviewer): Group {
            $group->update(['status' => 'active', 'rejection_reason' => null, 'suspension_reason' => null, 'reviewed_at' => now(), 'reviewed_by' => $reviewer->id]);
            $acceptedInvitations = GroupInvitation::query()->where('group_id', $group->id)->where('status', 'accepted')->get();
            foreach ($acceptedInvitations as $invitation) {
                GroupMember::query()->updateOrCreate(['group_id' => $group->id, 'user_id' => $invitation->invited_user_id], ['role' => 'member', 'status' => 'active', 'joined_at' => now(), 'left_at' => null]);
            }
            $this->notifications->notifyUser($group->owner_id, NotificationEventType::GroupApproved, 'تمت الموافقة على الفريق التطوعي', "وافقت إدارة جود على {$group->name} وأصبح الفريق متاحاً.", 'group', 'high', $group->name, "/groups/{$group->id}", null, $reviewer->id);
            $this->notifications->notifyUserIds($acceptedInvitations->pluck('invited_user_id'), NotificationEventType::GroupApproved, 'تم اعتماد الفريق التطوعي', "تمت الموافقة على {$group->name} وأصبحت عضويتك فعالة.", 'group', 'high', $group->name, "/groups/{$group->id}", null, $reviewer->id);
            return $this->loadGroup($group->refresh());
        });
    }

    public function reject(Group $group, User $reviewer, string $reason): Group
    {
        if ($group->status !== 'pending') throw ValidationException::withMessages(['group' => ['Only pending group requests can be rejected.']]);
        $group->update(['status' => 'rejected', 'rejection_reason' => $reason, 'reviewed_at' => now(), 'reviewed_by' => $reviewer->id]);
        $this->notifications->notifyUser($group->owner_id, NotificationEventType::GroupRejected, 'تم رفض طلب الفريق', "تم رفض طلب {$group->name}: {$reason}", 'group', 'high', $group->name, "/groups/{$group->id}", null, $reviewer->id);
        return $this->loadGroup($group->refresh());
    }

    public function suspend(Group $group, User $reviewer, string $reason): Group
    {
        if ($group->status !== 'active') throw ValidationException::withMessages(['group' => ['Only active groups can be suspended.']]);
        $group->update(['status' => 'suspended', 'suspension_reason' => $reason, 'reviewed_at' => now(), 'reviewed_by' => $reviewer->id]);
        $this->notifications->notifyUser($group->owner_id, NotificationEventType::GroupRejected, 'تم إيقاف الفريق مؤقتاً', "أوقفت إدارة جود {$group->name}: {$reason}", 'group', 'high', $group->name, "/groups/{$group->id}", null, $reviewer->id);
        return $this->loadGroup($group->refresh());
    }

    private function basePublicQuery(): Builder
    {
        return Group::query()->where('status', 'active')->with($this->relations())->withCount($this->counts());
    }

    private function relations(): array
    {
        return [
            'owner.avatarMedia', 'organization', 'avatarMedia', 'coverMedia', 'categories',
            'memberships' => fn ($query) => $query->where('status', 'active')->with('user.avatarMedia'),
            'invitations.invitedUser.avatarMedia',
        ];
    }

    private function counts(): array
    {
        return [
            'activeMembers as active_members_count',
            'posts as posts_count' => fn (Builder $query) => $query->where('status', 'published'),
            'posts as posts_this_week_count' => fn (Builder $query) => $query->where('status', 'published')->where('created_at', '>=', now()->subDays(7)),
        ];
    }

    private function postRelations(?User $viewer): array
    {
        $relations = [
            'author.avatarMedia', 'group.memberships.user.avatarMedia', 'images', 'poll.options',
            'poll.votes',
        ];
        if ($viewer !== null) {
            $relations['likes'] = fn ($query) => $query->where('user_id', $viewer->id);
            $relations['poll.votes'] = fn ($query) => $query->where('user_id', $viewer->id);
        }
        return $relations;
    }

    private function ensureActive(Group $group): void
    {
        if ($group->status !== 'active') throw ValidationException::withMessages(['group' => ['This group is not active.']]);
    }

    private function ensureMember(Group $group, User $user): void
    {
        $this->ensureActive($group);
        if (! GroupMember::query()->where('group_id', $group->id)->where('user_id', $user->id)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['group' => ['You must join the group first.']]);
        }
    }

    private function ensureOwner(Group $group, User $user): void
    {
        if ((string) $group->owner_id !== (string) $user->id) abort(403, 'Only the group owner can perform this action.');
    }

    private function ensureGroupPost(Group $group, Post $post): void
    {
        if ((string) $post->group_id !== (string) $group->id) abort(404);
    }

    private function storeMedia(Group $group, UploadedFile $file, string $prop, bool $replace = false): void
    {
        if ($replace) $group->{$prop === 'avatar' ? 'avatarMedia' : 'coverMedia'}()->delete();
        $path = $file->store("media/group/{$group->id}/{$prop}", 'public');
        if ($path === false) return;
        Media::query()->create(['model_type' => 'group', 'model_id' => $group->id, 'prop' => $prop, 'disk' => 'public', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => (int) ($file->getSize() ?: 0), 'position' => 0]);
    }

    /** @return list<string> */
    private function normalizedCategories(array $data): array
    {
        $categories = $data['categories'] ?? (isset($data['category']) ? [$data['category']] : []);
        $categories = array_values(array_unique(array_filter(array_map(fn ($value) => trim((string) $value), $categories))));
        if ($categories === []) throw ValidationException::withMessages(['categories' => ['Choose at least one group category.']]);
        return array_slice($categories, 0, 8);
    }

    /** @param list<string> $userIds @return Collection<int, GroupInvitation> */
    private function inviteUsers(Group $group, User $actor, array $userIds, bool $notify): Collection
    {
        $result = collect();
        foreach (array_unique(array_filter($userIds)) as $userId) {
            if ((string) $userId === (string) $group->owner_id) continue;
            $user = User::query()->whereKey($userId)->where('status', 'active')->first();
            if ($user === null) continue;
            $invitation = GroupInvitation::query()->updateOrCreate(
                ['group_id' => $group->id, 'invited_user_id' => $user->id],
                ['invited_by' => $actor->id, 'status' => 'pending', 'responded_at' => null],
            );
            $result->push($invitation);
            if ($notify || $group->status === 'pending') {
                $this->notifications->notifyUser($user, NotificationEventType::GroupInvitationCreated, 'دعوة للانضمام إلى فريق تطوعي', "دعاك {$actor->name} لتكون عضواً في فريق {$group->name}. يمكنك قبول الدعوة الآن، وتتفعل العضوية بعد اعتماد الفريق إذا كان قيد المراجعة.", 'group', 'normal', $group->name, '/my-groups?tab=invitations', null, (string) $actor->id);
            }
        }
        return $result;
    }

    private function notifyOwnerForPostReview(Group $group, Post $post, User $author): void
    {
        $this->notifications->notifyUser($group->owner_id, NotificationEventType::GroupPostReviewRequested, 'منشور جديد بانتظار مراجعتك', "أرسل {$author->name} منشوراً جديداً إلى {$group->name}.", 'group', 'high', $group->name, "/groups/{$group->id}?tab=pending", null, (string) $author->id);
    }
}
