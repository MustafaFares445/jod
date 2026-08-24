<?php

declare(strict_types=1);

use App\Models\MobileDevice;
use App\Models\Notification;
use App\Services\Mobile\FcmPushGateway;
use App\Services\Mobile\FirebaseAccessTokenProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'mobile_push.fcm.project_id' => 'jod-unit-test',
        'mobile_push.fcm.endpoint' => 'https://fcm.googleapis.com/v1/projects/%s/messages:send',
    ]);
});

test('FCM gateway sends a web-compatible notification to a registration token', function () {
    $this->mock(FirebaseAccessTokenProvider::class, function (MockInterface $mock): void {
        $mock->shouldReceive('token')->once()->andReturn('firebase-oauth-token');
    });

    Http::fake([
        'https://fcm.googleapis.com/*' => Http::response([
            'name' => 'projects/jod-unit-test/messages/web-message',
        ]),
    ]);

    $device = new MobileDevice();
    $device->forceFill([
        'push_token' => 'unit-web-fcm-token',
        'push_target_type' => 'token',
        'platform' => 'web',
    ]);

    $notification = new Notification();
    $notification->forceFill([
        'id' => 'notification-unit-id',
        'title' => 'Web push title',
        'body' => 'Web push body',
        'category' => 'system',
        'priority' => 'high',
        'event_type' => 'firebase.unit',
        'reference_path' => '/notifications',
    ]);

    $result = app(FcmPushGateway::class)->send($device, $notification);

    expect($result->isSent())->toBeTrue();
    expect($result->providerMessageId)->toBe('projects/jod-unit-test/messages/web-message');

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        return $request->url() === 'https://fcm.googleapis.com/v1/projects/jod-unit-test/messages:send'
            && $request->hasHeader('Authorization', 'Bearer firebase-oauth-token')
            && data_get($payload, 'message.token') === 'unit-web-fcm-token'
            && data_get($payload, 'message.notification.title') === 'Web push title'
            && data_get($payload, 'message.notification.body') === 'Web push body'
            && data_get($payload, 'message.data.eventType') === 'firebase.unit'
            && data_get($payload, 'message.data.referencePath') === '/notifications'
            && data_get($payload, 'message.android.priority') === 'HIGH'
            && data_get($payload, 'message.webpush.headers.Urgency') === 'high';
    });
});

test('FCM gateway marks an unregistered token as stale', function () {
    $this->mock(FirebaseAccessTokenProvider::class, function (MockInterface $mock): void {
        $mock->shouldReceive('token')->once()->andReturn('firebase-oauth-token');
    });

    Http::fake([
        'https://fcm.googleapis.com/*' => Http::response([
            'error' => [
                'code' => 404,
                'status' => 'NOT_FOUND',
                'details' => [[
                    '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                    'errorCode' => 'UNREGISTERED',
                ]],
            ],
        ], 404),
    ]);

    $device = new MobileDevice();
    $device->forceFill([
        'push_token' => 'stale-unit-fcm-token',
        'push_target_type' => 'token',
        'platform' => 'android',
    ]);

    $notification = new Notification();
    $notification->forceFill([
        'id' => 'stale-notification-id',
        'title' => 'Stale token test',
        'body' => 'The token should be rejected.',
        'priority' => 'normal',
    ]);

    $result = app(FcmPushGateway::class)->send($device, $notification);

    expect($result->isStale())->toBeTrue();
});
