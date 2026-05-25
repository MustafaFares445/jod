<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __invoke(Request $request): array
    {
        $user = $request->user();

        return [
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'userType' => $user->user_type,
                'organizationId' => $user->organization_id,
                'organizationName' => $user->organization_id ? $user->organization->name : null,
                'status' => $user->status,
                'createdAt' => $user->created_at?->toIso8601String(),
                'lastActiveAt' => $user->last_active_at?->toIso8601String(),
            ],
        ];
    }
}
