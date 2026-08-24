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

        if ($this->isInvalidRegistration($response)) {
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
        $webUrgency = $priority === 'HIGH' ? 'high' : 'normal';
        $targetKey = $device->push_target_type === 'fid' ? 'fid' : 'token';
        $data = array_filter([
            'notificationId' => (string) $notification->id,
            'category' => $notification->category,
            'eventType' => $notification->event_type,
            'priority' => $notification->priority,
            'referenceLabel' => $notification->reference_label,
            'referencePath' => $notification->reference_path,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return [
            $targetKey => $device->push_token,
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
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                    ],
                ],
            ],
            'webpush' => [
                'headers' => [
                    'Urgency' => $webUrgency,
                ],
            ],
        ];
    }

    private function isInvalidRegistration(Response $response): bool
    {
        $details = $response->json('error.details', []);

        if (! is_array($details)) {
            return false;
        }

        foreach ($details as $detail) {
            if (! is_array($detail)
                || ($detail['@type'] ?? null) !== 'type.googleapis.com/google.firebase.fcm.v1.FcmError') {
                continue;
            }

            if (in_array($detail['errorCode'] ?? null, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                return true;
            }
        }

        return false;
    }
}
