<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Me;

use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\ProfileRequest;
use App\Services\UserService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private UserService $service) {}

    public function __invoke(Request $request): array
    {
        return [
            'data' => $this->profileData($request->user()),
        ];
    }

    public function update(ProfileRequest $request): array
    {
        $updated = $this->service->update(
            UserData::from($request->validated()),
            $request->user(),
        );

        return [
            'data' => $this->profileData($updated),
        ];
    }

    private function profileData(mixed $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'userType' => $user->user_type,
            'organizationId' => $user->organization_id,
            'organizationName' => $user->organization_id ? $user->organization?->name : null,
            'status' => $user->status,
            'createdAt' => $user->created_at?->toIso8601String(),
            'lastActiveAt' => $user->last_active_at?->toIso8601String(),
        ];
    }
}
