<?php

declare(strict_types=1);

namespace App\Http\Controllers\Mobile;

use App\Enums\MediaModel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Media\MediaUploadRequest;
use App\Http\Resources\Mobile\UserResource;
use App\Models\User;
use App\Services\MediaService;
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAvatarController extends Controller
{
    public function __construct(private readonly MediaService $mediaService) {}

    public function store(MediaUploadRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('avatarMedia');
        $file = $request->file('file');

        if ($user->avatarMedia !== null) {
            $this->mediaService->replace(MediaModel::USER, (string) $user->id, 'avatar', (string) $user->avatarMedia->id, $file);
        } else {
            $this->mediaService->upload(MediaModel::USER, (string) $user->id, 'avatar', $file);
        }

        return MobileApiResponse::success(
            UserResource::make($this->profileUser($user->refresh()))->resolve($request),
            'Profile image updated successfully.',
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('avatarMedia');

        if ($user->avatarMedia !== null) {
            $this->mediaService->delete(MediaModel::USER, (string) $user->id, 'avatar', (string) $user->avatarMedia->id);
        }

        return MobileApiResponse::success(
            UserResource::make($this->profileUser($user->refresh()))->resolve($request),
            'Profile image removed successfully.',
        );
    }

    private function profileUser(User $user): User
    {
        return $user
            ->loadMissing(['organization', 'avatarMedia'])
            ->loadCount(['posts', 'savedPosts', 'donations']);
    }
}
