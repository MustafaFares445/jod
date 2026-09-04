<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UserFilterRequest;
use App\Http\Requests\Users\UserRequest;
use App\Http\Resources\Admin\UserDonationResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function __construct(protected UserService $service) {}

    public function index(UserFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $validated = $request->validated();
        $filters = (array) ($validated['filter'] ?? []);
        $status = $filters['status'] ?? null;
        $role = $filters['role'] ?? $filters['userType'] ?? null;
        $search = trim((string) ($filters['search'] ?? $validated['search'] ?? ''));
        $sort = (string) ($validated['sort'] ?? '-createdAt');

        $users = User::query()
            ->when($status && $status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($role && $role !== 'all', fn (Builder $query) => $query->where('user_type', $role))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            });

        match ($sort) {
            'createdAt' => $users->orderBy('created_at'),
            '-createdAt' => $users->orderByDesc('created_at'),
            'name' => $users->orderBy('name'),
            '-name' => $users->orderByDesc('name'),
            'lastActiveAt' => $users->orderBy('last_active_at'),
            '-lastActiveAt' => $users->orderByDesc('last_active_at'),
            default => $users->orderByDesc('created_at'),
        };

        return UserResource::collection(
            $users->orderBy('id')->paginate((int) ($validated['perPage'] ?? 20)),
        );
    }

    public function store(UserRequest $request): UserResource
    {
        $this->authorize('create', User::class);

        $user = $this->service->store(UserData::from($request->validated()));

        return UserResource::make($user);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return UserResource::make($user);
    }

    public function posts(Request $request, User $user): AnonymousResourceCollection
    {
        $this->authorize('view', $user);

        $perPage = min(max((int) $request->integer('perPage', 20), 1), 100);

        $posts = $user->posts()
            ->with([
                'organization',
                'campaign',
                'images',
                'author',
                'updatedBy',
                'reviewedBy',
                'blockedBy',
            ])
            ->latest('created_at')
            ->paginate($perPage);

        return PostResource::collection($posts);
    }

    public function donations(Request $request, User $user): AnonymousResourceCollection
    {
        $this->authorize('view', $user);

        $perPage = min(max((int) $request->integer('perPage', 20), 1), 100);

        $donations = $user->donations()
            ->with(['organization', 'campaign'])
            ->latest('donated_at')
            ->latest('created_at')
            ->paginate($perPage);

        return UserDonationResource::collection($donations);
    }

    public function personalization(Request $request, User $user): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $user);
        $user->loadMissing(['preference', 'categoryInterests.category', 'capabilities']);

        return response()->json(['data' => [
            'onboardingCompleted' => $user->preference?->onboarding_completed_at !== null,
            'onboardingCompletedAt' => $user->preference?->onboarding_completed_at?->toIso8601String(),
            'intent' => $user->preference?->intent?->value ?? $user->preference?->intent,
            'preferredCity' => $user->preference?->preferred_city,
            'preferredGovernorate' => $user->preference?->preferred_governorate,
            'preferredRadiusKm' => $user->preference?->preferred_radius_km,
            'remoteHelpEnabled' => (bool) ($user->preference?->remote_help_enabled ?? false),
            'availabilityStatus' => $user->preference?->availability_status?->value ?? $user->preference?->availability_status,
            'interests' => $user->categoryInterests->map(fn ($interest) => [
                'category' => ['id' => (string) $interest->category_id, 'name' => (string) ($interest->category?->name ?? '')],
                'selectedByUser' => (float) $interest->explicit_weight > 0,
                'explicitWeight' => (float) $interest->explicit_weight,
                'behavioralWeight' => (float) $interest->behavioral_weight,
            ])->values(),
            'capabilities' => $user->capabilities->map(fn ($capability) => ['id' => (string) $capability->id, 'name' => (string) $capability->name, 'slug' => (string) $capability->slug])->values(),
            'feedback' => [
                'interested' => $user->interactions()->where('event_type', 'interested')->count(),
                'notInterested' => $user->interactions()->where('event_type', 'not_interested')->count(),
                'hiddenPosts' => $user->postFeedback()->where('type', 'hide')->count(),
                'hiddenPublishers' => $user->hiddenPublishers()->count(),
            ],
        ]]);
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $updated = $this->service->update(UserData::from($request->validated()), $user);

        return UserResource::make($updated);
    }

    public function destroy(User $user): Response
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->noContent();
    }

    public function updateStatus(Request $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $request->validate(['status' => ['required', 'in:active,inactive']]);

        $updated = $this->service->updateStatus($user, $request->get('status'));

        return UserResource::make($updated);
    }

    public function updatePassword(Request $request, User $user): UserResource
    {
        $this->authorize('resetPassword', User::class);

        $request->validate([
            'newPassword' => ['required', 'string', 'min:8', 'confirmed'],
            'newPassword_confirmation' => ['required'],
        ]);

        $updated = $this->service->updatePassword($user, $request->get('newPassword'));

        return UserResource::make($updated);
    }
}
