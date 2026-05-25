<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Org;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function profile(): JsonResponse
    {
        $this->authorize('view', 'org-settings');

        $org = auth()->user()->organization;

        return response()->json([
            'data' => [
                'id' => $org?->id,
                'name' => $org?->name,
                'email' => $org?->email,
                'phone' => $org?->phone,
            ],
        ]);
    }

    public function updateProfile(): JsonResponse
    {
        $this->authorize('update', 'org-settings');

        return response()->json([
            'data' => ['message' => 'Profile updated successfully'],
        ]);
    }
}
