<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\GroupCommentResource;
use App\Http\Resources\Mobile\GroupPostResource;
use App\Http\Resources\Mobile\GroupResource;
use App\Models\Group;
use App\Models\GroupComment;
use App\Models\GroupInvitation;
use App\Models\CampaignApplication;
use App\Models\Donation;
use App\Models\Post;
use App\Models\User;
use App\Services\Mobile\GroupService;
use App\Services\Mobile\GroupWorkflowService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    public function __construct(
        private readonly GroupService $service,
        private readonly GroupWorkflowService $workflows,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $params = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'], 'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'], 'category' => ['nullable', 'string', 'max:120'], 'location' => ['nullable', 'string', 'max:120'],
        ]);
        $paginator = $this->service->paginate($params);
        return MobileApiResponse::paginated($paginator->through(fn (Group $group) => GroupResource::make($group)->resolve($request)), 'Groups retrieved successfully.');
    }

    public function suggested(Request $request): JsonResponse { return $this->index($request); }

    public function mine(Request $request): JsonResponse
    {
        $params = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'], 'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'scope' => ['nullable', Rule::in(['all', 'owned', 'joined'])],
        ]);
        $paginator = $this->service->mine($request->user(), $params);
        return MobileApiResponse::paginated($paginator->through(fn (Group $group) => GroupResource::make($group)->resolve($request)), 'User groups retrieved successfully.');
    }

    public function show(Request $request, Group $group): JsonResponse
    {
        $viewer = $request->user('sanctum');
        if ($group->status !== 'active' && (! $viewer instanceof User || (string) $group->owner_id !== (string) $viewer->id)) abort(404);
        return MobileApiResponse::success(GroupResource::make($this->service->loadGroup($group))->resolve($request), 'Group retrieved successfully.');
    }

    private function groupRules(bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return [
            'name' => [$required, 'string', 'min:3', 'max:120'],
            'description' => [$required, 'string', 'min:10', 'max:2000'],
            'categories' => [$required, 'array', 'min:1', 'max:8'],
            'categories.*' => ['required', 'string', 'max:120', 'distinct'],
            'location' => ['nullable', 'string', 'max:255'],
            'rules' => [$required, 'array', 'min:1', 'max:20'],
            'rules.*' => ['required', 'string', 'max:300'],
            'purpose' => [$required, 'string', 'min:10', 'max:1000'],
            'invitedUserIds' => ['nullable', 'array', 'max:30'],
            'invitedUserIds.*' => ['string', 'distinct', 'exists:users,id'],
            'requiresPostApproval' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->groupRules());
        $group = $this->service->create($request->user(), $data, $request->file('image'), $request->file('cover'));
        return MobileApiResponse::success(GroupResource::make($group)->resolve($request), 'Group creation request submitted successfully.');
    }

    public function update(Request $request, Group $group): JsonResponse
    {
        $data = $request->validate($this->groupRules(true));
        $group = $this->service->update($group, $request->user(), $data, $request->file('image'), $request->file('cover'));
        return MobileApiResponse::success(GroupResource::make($group)->resolve($request), 'Group updated successfully.');
    }

    public function destroy(Request $request, Group $group): JsonResponse
    {
        $this->service->deleteOwned($group, $request->user());
        return MobileApiResponse::success(null, 'Group deleted successfully.');
    }

    public function userCandidates(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $search = trim((string) ($data['search'] ?? ''));
        $user = $request->user();
        $candidates = User::query()->with('avatarMedia')->where('status', 'active')->whereKeyNot($user->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })->orderBy('name')->limit(30)->get()->map(fn (User $candidate): array => [
                'id' => (string) $candidate->id, 'name' => (string) $candidate->name,
                'username' => str((string) $candidate->email)->before('@')->toString(), 'email' => $candidate->email,
                'avatarUrl' => $candidate->avatarMedia?->publicUrl(),
            ])->values()->all();
        return MobileApiResponse::success($candidates, 'Group invitation candidates retrieved successfully.');
    }

    public function adminCandidates(Request $request): JsonResponse { return $this->userCandidates($request); }

    public function invite(Request $request, Group $group): JsonResponse
    {
        $data = $request->validate(['userIds' => ['required', 'array', 'min:1', 'max:30'], 'userIds.*' => ['required', 'string', 'distinct', 'exists:users,id']]);
        return MobileApiResponse::success($this->service->invite($group, $request->user(), $data['userIds']), 'Invitations sent successfully.');
    }

    public function myInvitations(Request $request): JsonResponse
    {
        return MobileApiResponse::success($this->service->invitationsForUser($request->user())->map(fn (GroupInvitation $invite) => [
            'id' => (string) $invite->id, 'status' => $invite->status,
            'group' => ['id' => (string) $invite->group->id, 'name' => $invite->group->name, 'imageUrl' => $invite->group->avatarMedia?->publicUrl(), 'status' => $invite->group->status],
            'invitedBy' => ['id' => (string) $invite->inviter->id, 'name' => $invite->inviter->name],
            'createdAt' => $invite->created_at?->toIso8601String(),
        ])->values()->all(), 'Group invitations retrieved successfully.');
    }

    public function acceptInvitation(Request $request, GroupInvitation $invitation): JsonResponse
    {
        return MobileApiResponse::success($this->service->respondInvitation($invitation, $request->user(), true), 'Invitation accepted successfully.');
    }

    public function declineInvitation(Request $request, GroupInvitation $invitation): JsonResponse
    {
        return MobileApiResponse::success($this->service->respondInvitation($invitation, $request->user(), false), 'Invitation declined successfully.');
    }

    public function join(Request $request, Group $group): JsonResponse
    {
        return MobileApiResponse::success(GroupResource::make($this->service->join($group, $request->user()))->resolve($request), 'Joined group successfully.');
    }

    public function leave(Request $request, Group $group): JsonResponse
    {
        return MobileApiResponse::success(GroupResource::make($this->service->leave($group, $request->user()))->resolve($request), 'Left group successfully.');
    }

    public function removeMember(Request $request, Group $group, User $user): JsonResponse
    {
        return MobileApiResponse::success(GroupResource::make($this->service->removeMember($group, $request->user(), (string) $user->id))->resolve($request), 'Member removed successfully.');
    }

    public function members(Request $request, Group $group): JsonResponse
    {
        if ($group->status !== 'active' && (string) $group->owner_id !== (string) optional($request->user('sanctum'))->id) abort(404);
        $members = $group->memberships()->where('status', 'active')->with('user.avatarMedia')->paginate(max(1, min((int) $request->integer('perPage', 20), 100)));
        $members->setCollection($members->getCollection()->map(fn ($member) => [
            'id' => (string) $member->user->id, 'name' => $member->user->name,
            'username' => str($member->user->email)->before('@')->toString(), 'email' => $member->user->email,
            'avatarUrl' => $member->user->avatarMedia?->publicUrl(), 'role' => $member->role,
        ]));
        return MobileApiResponse::paginated($members, 'Group members retrieved successfully.');
    }

    public function posts(Request $request, Group $group): JsonResponse
    {
        $viewer = $request->user('sanctum');
        if ($group->status !== 'active' && (! $viewer instanceof User || (string) $group->owner_id !== (string) $viewer->id)) abort(404);
        $status = (string) $request->query('status', 'published');
        if (! in_array($status, ['published', 'pending', 'rejected'], true)) $status = 'published';
        $paginator = $this->service->posts($group, $viewer instanceof User ? $viewer : null, $status, (int) $request->integer('perPage', 20));
        return MobileApiResponse::paginated($paginator->through(fn (Post $post) => GroupPostResource::make($post)->resolve($request)), 'Group posts retrieved successfully.');
    }

    public function createPost(Request $request, Group $group): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $post = $this->service->createPost($group, $request->user(), $data['body']);
        return MobileApiResponse::success(GroupPostResource::make($post)->resolve($request), 'Group post created successfully.');
    }

    public function approvePost(Request $request, Group $group, Post $post): JsonResponse
    {
        return MobileApiResponse::success(GroupPostResource::make($this->service->approvePost($group, $post, $request->user()))->resolve($request), 'Group post approved successfully.');
    }

    public function rejectPost(Request $request, Group $group, Post $post): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        return MobileApiResponse::success(GroupPostResource::make($this->service->rejectPost($group, $post, $request->user(), $data['reason']))->resolve($request), 'Group post rejected successfully.');
    }

    public function deletePost(Request $request, Group $group, Post $post): JsonResponse
    {
        $this->service->deletePost($group, $post, $request->user());
        return MobileApiResponse::success(null, 'Group post deleted successfully.');
    }

    public function comments(Request $request, Post $post): JsonResponse
    {
        if ($post->group_id === null || $post->group?->status !== 'active') abort(404);
        $viewer = $request->user('sanctum');
        $query = GroupComment::query()->where('post_id', $post->id)->whereNull('parent_id')->where('status', 'published')
            ->with(['author.avatarMedia', 'post.group.memberships', 'replies.author.avatarMedia', 'replies.post.group.memberships']);
        if ($viewer instanceof User) $query->with(['likedByUsers' => fn (Relation $relation) => $relation->where('users.id', $viewer->id), 'replies.likedByUsers' => fn (Relation $relation) => $relation->where('users.id', $viewer->id)]);
        $paginator = $query->orderBy('created_at')->paginate(max(1, min((int) $request->integer('perPage', 30), 100)));
        return MobileApiResponse::paginated($paginator->through(fn (GroupComment $comment) => GroupCommentResource::make($comment)->resolve($request)), 'Group comments retrieved successfully.');
    }

    public function createComment(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000'], 'parentId' => ['nullable', 'string']]);
        $comment = $this->service->createComment($post->load('group'), $request->user(), $data['body'], $data['parentId'] ?? null)->load(['author.avatarMedia', 'post.group.memberships']);
        return MobileApiResponse::success(GroupCommentResource::make($comment)->resolve($request), 'Comment created successfully.');
    }

    public function likePost(Request $request, Post $post): JsonResponse { return MobileApiResponse::success($this->service->setPostLike($post, $request->user(), true)); }
    public function unlikePost(Request $request, Post $post): JsonResponse { return MobileApiResponse::success($this->service->setPostLike($post, $request->user(), false)); }
    public function likeComment(Request $request, GroupComment $comment): JsonResponse { $comment->load('post.group'); return MobileApiResponse::success($this->service->setCommentLike($comment, $request->user(), true)); }
    public function unlikeComment(Request $request, GroupComment $comment): JsonResponse { $comment->load('post.group'); return MobileApiResponse::success($this->service->setCommentLike($comment, $request->user(), false)); }

    public function vote(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate(['optionIds' => ['required', 'array', 'min:1', 'max:10'], 'optionIds.*' => ['required', 'string', 'distinct']]);
        return MobileApiResponse::success(GroupPostResource::make($this->service->vote($post, $request->user(), $data['optionIds']))->resolve($request), 'Vote recorded successfully.');
    }

    public function createCampaign(Request $request, Group $group): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:4', 'max:255'], 'summary' => ['nullable', 'string', 'max:2000'], 'content' => ['nullable', 'string'],
            'categoryId' => ['nullable', 'string', 'exists:categories,id'], 'location' => ['nullable', 'string', 'max:255'],
            'goalAmount' => ['required', 'numeric', 'min:0'], 'startDate' => ['nullable', 'date'], 'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'audience' => ['nullable', 'string', Rule::in(['general', 'student'])],
        ]);
        return MobileApiResponse::success($this->service->createCampaign($group, $request->user(), $data), 'Group campaign created successfully.');
    }

    public function applications(Request $request, Group $group): JsonResponse
    {
        $params = $request->validate(['perPage' => ['nullable', 'integer', 'min:1', 'max:100'], 'status' => ['nullable', 'string', 'max:30']]);
        return MobileApiResponse::paginated($this->workflows->applications($group, $request->user(), $params), 'Group applications retrieved successfully.');
    }

    public function acceptApplication(Request $request, Group $group, CampaignApplication $application): JsonResponse
    {
        return MobileApiResponse::success($this->workflows->acceptApplication($group, $request->user(), $application), 'Application accepted successfully.');
    }

    public function contactApplication(Request $request, Group $group, CampaignApplication $application): JsonResponse
    {
        return MobileApiResponse::success($this->workflows->contactApplication($group, $request->user(), $application), 'Application contact started successfully.');
    }

    public function completeApplication(Request $request, Group $group, CampaignApplication $application): JsonResponse
    {
        return MobileApiResponse::success($this->workflows->completeApplication($group, $request->user(), $application), 'Application completed successfully.');
    }

    public function rejectApplication(Request $request, Group $group, CampaignApplication $application): JsonResponse
    {
        return MobileApiResponse::success($this->workflows->rejectApplication($group, $request->user(), $application), 'Application rejected successfully.');
    }

    public function donations(Request $request, Group $group): JsonResponse
    {
        $params = $request->validate(['perPage' => ['nullable', 'integer', 'min:1', 'max:100'], 'status' => ['nullable', 'string', 'max:30']]);
        return MobileApiResponse::paginated($this->workflows->donations($group, $request->user(), $params), 'Group donations retrieved successfully.');
    }

    public function acceptDonation(Request $request, Group $group, Donation $donation): JsonResponse
    {
        return MobileApiResponse::success($this->workflows->acceptDonation($group, $request->user(), $donation));
    }

    public function contactDonation(Request $request, Group $group, Donation $donation): JsonResponse
    {
        return MobileApiResponse::success($this->workflows->contactDonation($group, $request->user(), $donation));
    }

    public function agreeDonation(Request $request, Group $group, Donation $donation): JsonResponse
    {
        return MobileApiResponse::success($this->workflows->agreeDonation($group, $request->user(), $donation));
    }

    public function completeDonation(Request $request, Group $group, Donation $donation): JsonResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0']]);
        return MobileApiResponse::success($this->workflows->completeDonation($group, $request->user(), $donation, (float) $data['amount']));
    }

    public function cancelDonation(Request $request, Group $group, Donation $donation): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        return MobileApiResponse::success($this->workflows->cancelDonation($group, $request->user(), $donation, $data['reason']));
    }

    public function recommendations(Group $group): JsonResponse
    {
        if ($group->status !== 'active') abort(404);
        return MobileApiResponse::success($this->service->recommendations($group), 'Group recommendations retrieved successfully.');
    }
}
