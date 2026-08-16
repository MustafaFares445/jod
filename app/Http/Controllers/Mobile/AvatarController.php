<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mobile\AvatarRequest;
use App\Http\Resources\Mobile\UserResource;
use App\Models\User;
use App\Services\Mobile\AvatarService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvatarController extends Controller
{
    public function __construct(private readonly AvatarService $service) {}

    public function store(AvatarRequest $request): JsonResponse
    {
        $user = $this->service->replace(
            $request->user(),
            $request->file('avatar'),
        );

        return MobileApiResponse::success(
            UserResource::make($this->profileUser($user))->resolve($request),
            'Avatar updated successfully.',
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $this->service->delete($request->user());

        return MobileApiResponse::success(
            UserResource::make($this->profileUser($user))->resolve($request),
            'Avatar removed successfully.',
        );
    }

    private function profileUser(User $user): User
    {
        return $user
            ->loadMissing('organization')
            ->loadCount(['posts', 'savedPosts', 'donations']);
    }
}
