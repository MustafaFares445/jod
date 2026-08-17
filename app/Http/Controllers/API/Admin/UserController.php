<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UserFilterRequest;
use App\Http\Requests\Users\UserRequest;
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

        $status = $request->input('filter.status');
        $userType = $request->input('filter.userType') ?? $request->input('filter.role');
        $search = $request->input('filter.search') ?? $request->input('search');
        $sort = (string) $request->input('sort', '-createdAt');

        $users = User::query()
            ->when($status, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($userType, fn (Builder $query, string $value) => $query->where('user_type', $value))
            ->when($search, function (Builder $query, string $value): void {
                $query->where(function (Builder $searchQuery) use ($value): void {
                    $searchQuery->where('name', 'LIKE', "%{$value}%")
                        ->orWhere('email', 'LIKE', "%{$value}%");
                });
            });

        match ($sort) {
            'createdAt' => $users->orderBy('created_at'),
            'name' => $users->orderBy('name'),
            '-name' => $users->orderByDesc('name'),
            default => $users->orderByDesc('created_at'),
        };

        return UserResource::collection($users->paginate((int) $request->input('perPage', 20)));
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
