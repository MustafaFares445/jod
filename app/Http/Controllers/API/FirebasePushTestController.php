<?php

declare(strict_types=1);

namespace App\Http\Controllers\API;

use App\Contracts\MobilePushGateway;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\FirebasePushTestRequest;
use App\Models\MobileDevice;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use RuntimeException;

class FirebasePushTestController extends Controller
{
    public function __invoke(FirebasePushTestRequest $request, MobilePushGateway $gateway): JsonResponse
    {
        if (! (bool) config('mobile_push.test_endpoint_enabled')) {
            return $this->errorResponse('Not found.', 404);
        }

        $validated = $request->validated();
        $device = new MobileDevice();
        $device->forceFill([
            'id' => (string) Str::uuid(),
            'push_token' => $validated['fcmToken'],
            'push_target_type' => 'token',
            'platform' => $validated['platform'] ?? 'web',
        ]);

        $notification = new Notification();
        $notification->forceFill([
            'id' => (string) Str::uuid(),
            'title' => $validated['title'] ?? 'JOD Firebase test',
            'body' => $validated['body'] ?? 'Firebase Cloud Messaging is configured correctly.',
            'category' => 'system',
            'priority' => 'normal',
            'event_type' => 'firebase.test',
        ]);

        try {
            $result = $gateway->send($device, $notification);
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 502);
        }

        if ($result->isStale()) {
            return $this->errorResponse('FCM rejected the registration token.', 422);
        }

        return $this->successResponse([
            'status' => $result->status,
            'providerMessageId' => $result->providerMessageId,
        ], 'Firebase test push sent successfully.');
    }
}
