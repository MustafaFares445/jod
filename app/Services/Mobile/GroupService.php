<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Enums\NotificationEventType;
use App\Models\Campaign;
use App\Models\Group;
use App\Models\GroupComment;
use App\Models\GroupMember;
use App\Models\GroupPost;
use App\Models\Media;
use App\Models\User;
use App\Services\NotificationEventService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
            ->when($category !== '', fn (Builder $query) => $query->where('category', $category))
            ->when($location !== '', fn (Builder $query) => $query->where('location', 'like', "%{$location}%"))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function mine(User $user, array $params): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($params['perPage'] ?? 20), 100));

        return Group::query()
            ->where(function (Builder $query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('memberships', fn (Builder $member) => $member->where('user_id', $user->id)->where('status', 'active'));
            })
            ->with($this->relations())
            ->withCount($this->counts())
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(User $owner, array $data, ?UploadedFile $avatar = null): Group
    {
        return DB::transaction(function () use ($owner, $data, $avatar): Group {
            $group = Group::query()->create([
                'owner_id' => $owner->id,
                'organization_id' => $owner->organization_id,
                'name' => $data['name'],
                'description' => $data['description'],
                'category' => $data['category'],
                'location' => $data['location'] ?? null,
                'status' => 'pending',
                'purpose' => $data['purpose'],
                'rules' => array_values($data['rules'] ?? []),
                'proposed_admin_ids' => array_values($data['proposedAdminIds'] ?? []),
                'submitted_at' => now(),
            ]);

            GroupMember::query()->create([
                'group_id' => $group->id,
                'user_id' => $owner->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            if ($avatar !== null) {
                $this->storeAvatar($group, $avatar);
            }

            $this->notifications->notifyAdmins(
                NotificationEventType::GroupSubmitted,
                'طلب إنشاء مجموعة جديد',
                "تم إرسال طلب إنشاء {$group->name} للمراجعة.",
                'group',
                'normal',
                $group->name,
                '/dashboard/admin/groups/review',
                (string) $owner->id,
            );

            $this->notifications->notifyUser(
                $owner->id,
                NotificationEventType::GroupSubmitted,
                'تم استلام طلب إنشاء الفريق التطوعي',
                "طلب إنشاء {$group->name} قيد مراجعة الإدارة الآن.",
                'group',
                'normal',
                $group->name,
                "/groups/{$group->id}",
            );

            return $this->loadGroup($group);
        });
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
            ['role' => 'member', 'status' => 'active', 'joined_at' => now(), 'left_at' => null],
        );
        return $this->loadGroup($group->refresh());
    }

    public function leave(Group $group, User $user): Group
    {
        $membership = GroupMember::query()->where('group_id', $group->id)->where('user_id', $user->id)->first();
        if ($membership?->role === 'owner') {
            throw ValidationException::withMessages(['group' => ['The owner cannot leave the group.']]);
        }
        $membership?->update(['status' => 'left', 'left_at' => now()]);
        return $this->loadGroup($group->refresh());
    }

    public function createPost(Group $group, User $user, string $body): GroupPost
    {
        $this->ensureMember($group, $user);
        return GroupPost::query()->create(['group_id' => $group->id, 'author_id' => $user->id, 'body' => $body, 'status' => 'published']);
    }

    public function createComment(GroupPost $post, User $user, string $body, ?string $parentId): GroupComment
    {
        $this->ensureMember($post->group, $user);
        $rootId = null;
        if ($parentId !== null) {
            $parent = GroupComment::query()->where('post_id', $post->id)->whereKey($parentId)->firstOrFail();
            $rootId = $parent->parent_id ?: $parent->id;
        }

        return DB::transaction(function () use ($post, $user, $body, $rootId): GroupComment {
            $comment = GroupComment::query()->create([
                'post_id' => $post->id,
                'author_id' => $user->id,
                'parent_id' => $rootId,
                'body' => $body,
                'status' => 'published',
            ]);
            $post->increment('comments_count');
            return $comment;
        });
    }

    public function setPostLike(GroupPost $post, User $user, bool $liked): array
    {
        $this->ensureMember($post->group, $user);
        return DB::transaction(function () use ($post, $user, $liked): array {
            $query = DB::table('group_post_likes')->where('post_id', $post->id)->where('user_id', $user->id);
            $exists = $query->exists();
            if ($liked && ! $exists) {
                DB::table('group_post_likes')->insert(['post_id' => $post->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
                $post->increment('likes_count');
            } elseif (! $liked && $exists) {
                $query->delete();
                $post->decrement('likes_count');
            }
            return ['isLiked' => $liked, 'likesCount' => (int) $post->refresh()->likes_count];
        });
    }

    public function setCommentLike(GroupComment $comment, User $user, bool $liked): array
    {
        $this->ensureMember($comment->post->group, $user);
        return DB::transaction(function () use ($comment, $user, $liked): array {
            $query = DB::table('group_comment_likes')->where('comment_id', $comment->id)->where('user_id', $user->id);
            $exists = $query->exists();
            if ($liked && ! $exists) {
                DB::table('group_comment_likes')->insert(['comment_id' => $comment->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
                $comment->increment('likes_count');
            } elseif (! $liked && $exists) {
                $query->delete();
                $comment->decrement('likes_count');
            }
            return ['isLiked' => $liked, 'likesCount' => (int) $comment->refresh()->likes_count];
        });
    }

    public function recommendations(Group $group): array
    {
        $groups = Group::query()->where('status', 'active')->whereKeyNot($group->id)->where('category', $group->category)->limit(3)->get();
        $campaigns = Campaign::query()
            ->with(['organization', 'category'])
            ->where('status', 'active')
            ->whereHas('category', fn (Builder $query) => $query->where('name', $group->category))
            ->limit(3)
            ->get();

        return [
            ...$groups->map(fn (Group $candidate) => [
                'id' => 'group-'.$candidate->id, 'kind' => 'group', 'title' => $candidate->name,
                'subtitle' => 'فريق تطوعي',
                'category' => $candidate->category, 'location' => $candidate->location ?? '',
                'reason' => "مساحة أخرى في «{$group->category}»", 'metaLabel' => null, 'targetGroupId' => $candidate->id,
            ])->all(),
            ...$campaigns->map(fn (Campaign $campaign) => [
                'id' => 'campaign-'.$campaign->id, 'kind' => 'campaign', 'title' => $campaign->title,
                'subtitle' => $campaign->organization?->name ?? 'حملة جود', 'category' => $campaign->category?->name ?? $group->category,
                'location' => $campaign->location ?? '', 'reason' => "لأن المجموعة في مجال «{$group->category}»", 'metaLabel' => null,
            ])->all(),
        ];
    }

    public function approve(Group $group, User $reviewer): Group
    {
        if (! in_array($group->status, ['pending', 'rejected'], true)) {
            throw ValidationException::withMessages(['group' => ['Only pending or rejected group requests can be approved.']]);
        }

        return DB::transaction(function () use ($group, $reviewer): Group {
            $group->update(['status' => 'active', 'rejection_reason' => null, 'reviewed_at' => now(), 'reviewed_by' => $reviewer->id]);
            foreach (collect($group->proposed_admin_ids ?? [])->filter()->unique() as $userId) {
                if ((string) $userId === (string) $group->owner_id || ! User::query()->whereKey($userId)->where('status', 'active')->exists()) continue;
                GroupMember::query()->updateOrCreate(
                    ['group_id' => $group->id, 'user_id' => $userId],
                    ['role' => 'admin', 'status' => 'active', 'joined_at' => now(), 'left_at' => null],
                );
            }
            $this->notifications->notifyUser($group->owner_id, NotificationEventType::GroupApproved, 'تمت الموافقة على المجموعة', "أصبحت {$group->name} متاحة للعامة.", 'group', 'high', $group->name, "/groups/{$group->id}", null, $reviewer->id);
            return $this->loadGroup($group->refresh());
        });
    }

    public function reject(Group $group, User $reviewer, string $reason): Group
    {
        if ($group->status !== 'pending') {
            throw ValidationException::withMessages(['group' => ['Only pending group requests can be rejected.']]);
        }

        $group->update(['status' => 'rejected', 'rejection_reason' => $reason, 'reviewed_at' => now(), 'reviewed_by' => $reviewer->id]);
        $this->notifications->notifyUser($group->owner_id, NotificationEventType::GroupRejected, 'تم رفض طلب المجموعة', "تم رفض طلب {$group->name}: {$reason}", 'group', 'high', $group->name, "/groups/{$group->id}", null, $reviewer->id);
        return $this->loadGroup($group->refresh());
    }

    private function basePublicQuery(): Builder
    {
        return Group::query()->where('status', 'active')->with($this->relations())->withCount($this->counts());
    }

    private function relations(): array
    {
        return [
            'owner.avatarMedia', 'organization', 'avatarMedia', 'coverMedia',
            'memberships' => fn ($query) => $query->where('status', 'active')->with('user.avatarMedia'),
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

    private function ensureActive(Group $group): void
    {
        if ($group->status !== 'active') {
            throw ValidationException::withMessages(['group' => ['This group is not active.']]);
        }
    }

    private function ensureMember(Group $group, User $user): void
    {
        $this->ensureActive($group);
        if (! GroupMember::query()->where('group_id', $group->id)->where('user_id', $user->id)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['group' => ['You must join the group first.']]);
        }
    }

    private function storeAvatar(Group $group, UploadedFile $file): void
    {
        $path = $file->store("media/group/{$group->id}/avatar", 'public');
        if ($path === false) return;
        Media::query()->create([
            'model_type' => 'group', 'model_id' => $group->id, 'prop' => 'avatar', 'disk' => 'public', 'path' => $path,
            'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(), 'size' => (int) ($file->getSize() ?: 0), 'position' => 0,
        ]);
    }
}
