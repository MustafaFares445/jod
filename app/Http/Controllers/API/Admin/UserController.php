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
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function __construct(protected UserService $service) {}

    public function index(UserFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->when($request->get('filter.status'), fn ($q) => $q->where('status', $request->get('filter.status')))
            ->when($request->get('filter.role'), fn ($q) => $q->where('user_type', $request->get('filter.role')))
            ->when($request->get('filter.search') ?? $request->get('search'), fn ($q) => $q->where('name', 'LIKE', '%' . ($request->get('filter.search') ?? $request->get('search')) . '%')
                ->orWhere('email', 'LIKE', '%' . ($request->get('filter.search') ?? $request->get('search')) . '%'))
            ->orderByDesc('created_at')
            ->paginate($request->get('perPage', 20));

        return UserResource::collection($users);
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
        $this->authorize('resetPassword');

        $request->validate([
            'newPassword' => ['required', 'string', 'min:8', 'confirmed'],
            'newPassword_confirmation' => ['required'],
        ]);

        $updated = $this->service->updatePassword($user, $request->get('newPassword'));

        return UserResource::make($updated);
    }
}
