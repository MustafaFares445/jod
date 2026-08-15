<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Contracts\MobilePushGateway;
use App\Models\MobileDevice;
use App\Models\Notification;
use App\Support\MobilePush\PushDeliveryResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmPushGateway implements MobilePushGateway
{
    public function __construct(private readonly FirebaseAccessTokenProvider $accessTokenProvider) {}

    public function send(MobileDevice $device, Notification $notification): PushDeliveryResult
    {
        $projectId = trim((string) config('mobile_push.fcm.project_id'));

        if ($projectId === '') {
            throw new RuntimeException('FIREBASE_PROJECT_ID is required when mobile push delivery is enabled.');
        }

        $endpoint = sprintf((string) config('mobile_push.fcm.endpoint'), rawurlencode($projectId));
        $response = Http::withToken($this->accessTokenProvider->token())
            ->acceptJson()
            ->timeout(10)
            ->post($endpoint, [
                'message' => $this->message($device, $notification),
            ]);

        if ($response->successful()) {
            $messageId = $response->json('name');

            return PushDeliveryResult::sent(is_string($messageId) ? $messageId : null);
        }

        if ($this->isUnregistered($response)) {
            return PushDeliveryResult::stale();
        }

        throw new RuntimeException(
            sprintf('FCM push delivery failed (HTTP %d).', $response->status()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function message(MobileDevice $device, Notification $notification): array
    {
        $priority = in_array($notification->priority, ['high', 'urgent'], true) ? 'HIGH' : 'NORMAL';
        $data = array_filter([
            'notificationId' => (string) $notification->id,
            'category' => $notification->category,
            'priority' => $notification->priority,
            'referenceLabel' => $notification->reference_label,
            'referencePath' => $notification->reference_path,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return [
            'token' => $device->push_token,
            'notification' => [
                'title' => $notification->title,
                'body' => $notification->body,
            ],
            'data' => array_map(static fn (mixed $value): string => (string) $value, $data),
            'android' => [
                'priority' => $priority,
                'notification' => [
                    'sound' => 'default',
                ],
            ],
            'apns' => [
                'headers' => [
                    'apns-priority' => $priority === 'HIGH' ? '10' : '5',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                    ],
                ],
            ],
        ];
    }

    private function isUnregistered(Response $response): bool
    {
        $details = $response->json('error.details', []);

        if (! is_array($details)) {
            return false;
        }

        foreach ($details as $detail) {
            if (is_array($detail) && ($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return true;
            }
        }

        return false;
    }
}
