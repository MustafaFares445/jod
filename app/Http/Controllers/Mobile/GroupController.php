<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\GroupCommentResource;
use App\Http\Resources\Mobile\GroupPostResource;
use App\Http\Resources\Mobile\GroupResource;
use App\Models\Group;
use App\Models\GroupComment;
use App\Models\GroupPost;
use App\Models\User;
use App\Services\Mobile\GroupService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function __construct(private readonly GroupService $service) {}

    public function index(Request $request): JsonResponse
    {
        $params = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
        ]);
        $paginator = $this->service->paginate($params);
        return MobileApiResponse::paginated(
            $paginator->through(fn (Group $group) => GroupResource::make($group)->resolve($request)),
            'Groups retrieved successfully.',
        );
    }

    public function suggested(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    public function mine(Request $request): JsonResponse
    {
        $params = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $paginator = $this->service->mine($request->user(), $params);
        return MobileApiResponse::paginated(
            $paginator->through(fn (Group $group) => GroupResource::make($group)->resolve($request)),
            'User groups retrieved successfully.',
        );
    }

    public function show(Request $request, Group $group): JsonResponse
    {
        $viewer = $request->user('sanctum');
        if ($group->status !== 'active' && (! $viewer instanceof User || (string) $group->owner_id !== (string) $viewer->id)) {
            abort(404);
        }
        return MobileApiResponse::success(
            GroupResource::make($this->service->loadGroup($group))->resolve($request),
            'Group retrieved successfully.',
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'category' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'rules' => ['required', 'array', 'min:1', 'max:20'],
            'rules.*' => ['required', 'string', 'max:300'],
            'purpose' => ['required', 'string', 'min:10', 'max:1000'],
            'proposedAdminIds' => ['nullable', 'array', 'max:10'],
            'proposedAdminIds.*' => ['string', 'exists:users,id'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $group = $this->service->create($request->user(), $data, $request->file('image'));
        return MobileApiResponse::success(
            GroupResource::make($group)->resolve($request),
            'Group creation request submitted successfully.',
        );
    }

    public function adminCandidates(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $search = trim((string) ($data['search'] ?? ''));
        $user = $request->user();

        $candidates = User::query()
            ->with('avatarMedia')
            ->where('status', 'active')
            ->whereKeyNot($user->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(30)
            ->get()
            ->map(fn (User $candidate): array => [
                'id' => (string) $candidate->id,
                'name' => (string) $candidate->name,
                'username' => str((string) $candidate->email)->before('@')->toString(),
                'avatarUrl' => $candidate->avatarMedia?->publicUrl(),
            ])
            ->values()
            ->all();

        return MobileApiResponse::success($candidates, 'Group admin candidates retrieved successfully.');
    }

    public function join(Request $request, Group $group): JsonResponse
    {
        return MobileApiResponse::success(
            GroupResource::make($this->service->join($group, $request->user()))->resolve($request),
            'Joined group successfully.',
        );
    }

    public function leave(Request $request, Group $group): JsonResponse
    {
        return MobileApiResponse::success(
            GroupResource::make($this->service->leave($group, $request->user()))->resolve($request),
            'Left group successfully.',
        );
    }

    public function members(Request $request, Group $group): JsonResponse
    {
        if ($group->status !== 'active') abort(404);
        $members = $group->memberships()->where('status', 'active')->with('user.avatarMedia')
            ->paginate(max(1, min((int) $request->integer('perPage', 20), 100)));
        $members->setCollection($members->getCollection()->map(fn ($member) => [
            'id' => (string) $member->user->id,
            'name' => $member->user->name,
            'username' => str($member->user->email)->before('@')->toString(),
            'avatarUrl' => $member->user->avatarMedia?->publicUrl(),
            'role' => $member->role,
        ]));
        return MobileApiResponse::paginated($members, 'Group members retrieved successfully.');
    }

    public function posts(Request $request, Group $group): JsonResponse
    {
        if ($group->status !== 'active') abort(404);
        $viewer = $request->user('sanctum');
        $query = GroupPost::query()
            ->where('group_id', $group->id)
            ->where('status', 'published')
            ->with(['author.avatarMedia', 'group.memberships' => fn (Relation $relation) => $relation->where('status', 'active')]);
        if ($viewer instanceof User) {
            $query->with(['likedByUsers' => fn (Relation $relation) => $relation->where('users.id', $viewer->id)]);
        }
        $paginator = $query->orderByDesc('is_pinned')->orderByDesc('created_at')
            ->paginate(max(1, min((int) $request->integer('perPage', 20), 100)));
        return MobileApiResponse::paginated(
            $paginator->through(fn (GroupPost $post) => GroupPostResource::make($post)->resolve($request)),
            'Group posts retrieved successfully.',
        );
    }

    public function createPost(Request $request, Group $group): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $post = $this->service->createPost($group, $request->user(), $data['body'])
            ->load(['author.avatarMedia', 'group.memberships']);
        return MobileApiResponse::success(GroupPostResource::make($post)->resolve($request), 'Group post created successfully.');
    }

    public function comments(Request $request, GroupPost $post): JsonResponse
    {
        if ($post->group->status !== 'active') abort(404);
        $viewer = $request->user('sanctum');
        $query = GroupComment::query()
            ->where('post_id', $post->id)
            ->whereNull('parent_id')
            ->where('status', 'published')
            ->with(['author.avatarMedia', 'post.group.memberships', 'replies.author.avatarMedia', 'replies.post.group.memberships']);
        if ($viewer instanceof User) {
            $query->with([
                'likedByUsers' => fn (Relation $relation) => $relation->where('users.id', $viewer->id),
                'replies.likedByUsers' => fn (Relation $relation) => $relation->where('users.id', $viewer->id),
            ]);
        }
        $paginator = $query->orderBy('created_at')
            ->paginate(max(1, min((int) $request->integer('perPage', 30), 100)));
        return MobileApiResponse::paginated(
            $paginator->through(fn (GroupComment $comment) => GroupCommentResource::make($comment)->resolve($request)),
            'Group comments retrieved successfully.',
        );
    }

    public function createComment(Request $request, GroupPost $post): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000'], 'parentId' => ['nullable', 'string']]);
        $comment = $this->service->createComment($post->load('group'), $request->user(), $data['body'], $data['parentId'] ?? null)
            ->load(['author.avatarMedia', 'post.group.memberships']);
        return MobileApiResponse::success(GroupCommentResource::make($comment)->resolve($request), 'Comment created successfully.');
    }

    public function likePost(Request $request, GroupPost $post): JsonResponse
    {
        $post->load('group');
        return MobileApiResponse::success($this->service->setPostLike($post, $request->user(), true));
    }

    public function unlikePost(Request $request, GroupPost $post): JsonResponse
    {
        $post->load('group');
        return MobileApiResponse::success($this->service->setPostLike($post, $request->user(), false));
    }

    public function likeComment(Request $request, GroupComment $comment): JsonResponse
    {
        $comment->load('post.group');
        return MobileApiResponse::success($this->service->setCommentLike($comment, $request->user(), true));
    }

    public function unlikeComment(Request $request, GroupComment $comment): JsonResponse
    {
        $comment->load('post.group');
        return MobileApiResponse::success($this->service->setCommentLike($comment, $request->user(), false));
    }

    public function recommendations(Group $group): JsonResponse
    {
        if ($group->status !== 'active') abort(404);
        return MobileApiResponse::success($this->service->recommendations($group), 'Group recommendations retrieved successfully.');
    }
}
