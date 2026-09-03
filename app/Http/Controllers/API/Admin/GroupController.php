<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminGroupResource;
use App\Models\Group;
use App\Models\User;
use App\Services\Mobile\GroupService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class GroupController extends Controller
{
    public function __construct(private readonly GroupService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Group::class);
        $perPage = max(1, min((int) $request->integer('perPage', 20), 100));
        $query = Group::query()
            ->with(['owner', 'organization', 'avatarMedia', 'reviewedBy'])
            ->withCount([
                'activeMembers as active_members_count',
                'posts as posts_count' => fn (Builder $builder) => $builder->where('status', 'published'),
                'posts as posts_this_week_count' => fn (Builder $builder) => $builder->where('status', 'published')->where('created_at', '>=', now()->subDays(7)),
            ]);

        if ($request->filled('status')) $query->where('status', $request->string('status')->toString());
        if ($request->filled('category')) $query->where('category', $request->string('category')->toString());
        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(fn (Builder $builder) => $builder->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
        }

        $sort = $request->string('sort', '-submittedAt')->toString();
        match ($sort) {
            'name' => $query->orderBy('name'),
            '-name' => $query->orderByDesc('name'),
            'submittedAt' => $query->orderBy('submitted_at'),
            '-membersCount' => $query->orderByDesc('active_members_count'),
            default => $query->orderByDesc('submitted_at'),
        };

        return AdminGroupResource::collection($query->paginate($perPage));
    }

    public function show(Group $group): AdminGroupResource
    {
        $this->authorize('view', $group);
        return AdminGroupResource::make($this->loadAdmin($group));
    }

    public function approve(Request $request, Group $group): AdminGroupResource
    {
        $this->authorize('approve', $group);
        return AdminGroupResource::make($this->loadAdmin($this->service->approve($group, $request->user())));
    }

    public function reject(Request $request, Group $group): AdminGroupResource
    {
        $this->authorize('reject', $group);
        $data = $request->validate(['rejectionReason' => ['required', 'string', 'min:3', 'max:1000']]);
        return AdminGroupResource::make($this->loadAdmin($this->service->reject($group, $request->user(), $data['rejectionReason'])));
    }

    public function destroy(Group $group): Response
    {
        $this->authorize('delete', $group);
        $group->delete();
        return response()->noContent();
    }

    private function loadAdmin(Group $group): Group
    {
        $ids = collect($group->proposed_admin_ids ?? [])->filter()->values();
        $group->setRelation('proposedAdmins', User::query()->whereIn('id', $ids)->get());

        return $group->load(['owner', 'organization', 'avatarMedia', 'reviewedBy'])->loadCount([
            'activeMembers as active_members_count',
            'posts as posts_count' => fn (Builder $builder) => $builder->where('status', 'published'),
            'posts as posts_this_week_count' => fn (Builder $builder) => $builder->where('status', 'published')->where('created_at', '>=', now()->subDays(7)),
        ]);
    }
}
