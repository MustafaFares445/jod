<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SettingsRequest;
use App\Models\PlatformSetting;
use App\Services\PlatformSettingService;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function index(PlatformSettingService $service): JsonResponse
    {
        $this->authorize('viewAny', PlatformSetting::class);

        $settings = $service->all();

        return response()->json([
            'data' => [
                'siteName' => $settings['siteName'] ?? '',
                'allowNewPosts' => $settings['allowNewPosts'] ?? true,
                'requirePostReview' => $settings['requirePostReview'] ?? true,
            ],
            'message' => 'Platform settings retrieved successfully',
        ]);
    }

    public function update(SettingsRequest $request, PlatformSettingService $service): JsonResponse
    {
        $this->authorize('update', PlatformSetting::class);

        $service->update($request->validated());

        $settings = $service->all();

        return response()->json([
            'data' => [
                'siteName' => $settings['siteName'] ?? '',
                'allowNewPosts' => $settings['allowNewPosts'] ?? true,
                'requirePostReview' => $settings['requirePostReview'] ?? true,
            ],
            'message' => 'Platform settings updated successfully',
        ]);
    }
}
