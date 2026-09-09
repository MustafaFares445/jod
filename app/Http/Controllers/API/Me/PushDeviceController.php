<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\DashboardPushDeviceRequest;
use App\Models\User;
use App\Services\Mobile\MobileDeviceService;
use Illuminate\Http\JsonResponse;

class PushDeviceController extends Controller
{
    public function __construct(private readonly MobileDeviceService $devices) {}

    public function store(DashboardPushDeviceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $device = $this->devices->register($user, [
            'pushToken' => $validated['fcmToken'],
            'pushTargetType' => 'token',
            'platform' => 'web',
            'deviceId' => $validated['deviceId'] ?? null,
            'appVersion' => $validated['appVersion'] ?? null,
        ]);

        return $this->successResponse([
            'id' => (string) $device->id,
            'platform' => 'web',
        ], 'Dashboard push device registered successfully.');
    }

    public function destroy(DashboardPushDeviceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $removed = $this->devices->unregisterByToken(
            $user,
            (string) $request->validated('fcmToken'),
            'web',
        );

        return $this->successResponse([
            'removed' => $removed,
        ], 'Dashboard push device unregistered successfully.');
    }
}
