<?php

declare(strict_types=1);
use App\Contracts\MobilePushGateway;
use App\Jobs\DeliverMobilePush;
use App\Models\MobileDevice;
use App\Models\MobilePushDelivery;
use App\Models\Notification;
use App\Models\User;
use App\Services\Mobile\FcmPushGateway;
use App\Services\Mobile\FirebaseAccessTokenProvider;
use App\Support\MobilePush\PushDeliveryResult;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery\MockInterface;
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    config([
        'mobile_push.enabled' => false,
        'mobile_push.provider' => 'fcm',
        'mobile_push.fcm.project_id' => 'jod-test',
    ]);
});
test('inbox notification queues one delivery per registered device', function () {
    Queue::fake();
    config(['mobile_push.enabled' => true]);

    $user = User::factory()->create();
    $firstDevice = createDevice($user, 'first-token');
    $secondDevice = createDevice($user, 'second-token');

    $notification = Notification::factory()->create([
        'recipient_id' => $user->id,
        'mailbox' => 'inbox',
        'status' => 'unread',
    ]);

    $this->assertDatabaseHas('mobile_push_deliveries', [
        'notification_id' => $notification->id,
        'mobile_device_id' => $firstDevice->id,
        'status' => 'pending',
    ]);
    $this->assertDatabaseHas('mobile_push_deliveries', [
        'notification_id' => $notification->id,
        'mobile_device_id' => $secondDevice->id,
        'status' => 'pending',
    ]);
    expect(MobilePushDelivery::query()->where('notification_id', $notification->id)->count())->toBe(2);
    Queue::assertPushed(DeliverMobilePush::class, 2);
});
test('push delivery is not created when feature is disabled', function () {
    Queue::fake();

    $user = User::factory()->create();
    createDevice($user, 'disabled-token');

    Notification::factory()->create([
        'recipient_id' => $user->id,
        'mailbox' => 'inbox',
    ]);

    $this->assertDatabaseCount('mobile_push_deliveries', 0);
    Queue::assertNothingPushed();
});
test('fcm gateway sends notification and deep link payload', function () {
    $this->mock(FirebaseAccessTokenProvider::class, function (MockInterface $mock): void {
        $mock->shouldReceive('token')->once()->andReturn('oauth-token');
    });
    Http::fake([
        'https://fcm.googleapis.com/*' => Http::response([
            'name' => 'projects/jod-test/messages/message-123',
        ]),
    ]);

    $user = User::factory()->create();
    $device = createDevice($user, 'fcm-token');
    $notification = Notification::factory()->create([
        'recipient_id' => $user->id,
        'title' => 'Campaign updated',
        'body' => 'A campaign you follow has an update.',
        'category' => 'campaign',
        'priority' => 'high',
        'reference_label' => 'Open campaign',
        'reference_path' => '/campaigns/campaign-123',
    ]);

    $result = app(FcmPushGateway::class)->send($device, $notification);

    expect($result->isSent())->toBeTrue();
    expect($result->providerMessageId)->toBe('projects/jod-test/messages/message-123');

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();

        return $request->url() === 'https://fcm.googleapis.com/v1/projects/jod-test/messages:send'
            && $request->hasHeader('Authorization', 'Bearer oauth-token')
            && data_get($payload, 'message.token') === 'fcm-token'
            && data_get($payload, 'message.notification.title') === 'Campaign updated'
            && data_get($payload, 'message.data.referencePath') === '/campaigns/campaign-123'
            && data_get($payload, 'message.android.priority') === 'HIGH';
    });
});
test('fcm gateway targets firebase installation id when requested', function () {
    $this->mock(FirebaseAccessTokenProvider::class, function (MockInterface $mock): void {
        $mock->shouldReceive('token')->once()->andReturn('oauth-token');
    });
    Http::fake([
        'https://fcm.googleapis.com/*' => Http::response([
            'name' => 'projects/jod-test/messages/fid-message',
        ]),
    ]);

    $user = User::factory()->create();
    $device = createDevice($user, 'firebase-installation-id', 'fid');
    $notification = Notification::factory()->create(['recipient_id' => $user->id]);

    $result = app(FcmPushGateway::class)->send($device, $notification);

    expect($result->isSent())->toBeTrue();
    Http::assertSent(function (Request $request): bool {
        $message = data_get($request->data(), 'message', []);

        return ($message['fid'] ?? null) === 'firebase-installation-id'
            && ! array_key_exists('token', $message);
    });
});
test('fcm gateway marks unregistered registration token as stale', function () {
    $this->mock(FirebaseAccessTokenProvider::class, function (MockInterface $mock): void {
        $mock->shouldReceive('token')->once()->andReturn('oauth-token');
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

    $user = User::factory()->create();
    $device = createDevice($user, 'stale-fcm-token');
    $notification = Notification::factory()->create(['recipient_id' => $user->id]);

    $result = app(FcmPushGateway::class)->send($device, $notification);

    expect($result->isStale())->toBeTrue();
});
test('fcm gateway marks fcm specific invalid target as stale', function () {
    $this->mock(FirebaseAccessTokenProvider::class, function (MockInterface $mock): void {
        $mock->shouldReceive('token')->once()->andReturn('oauth-token');
    });
    Http::fake([
        'https://fcm.googleapis.com/*' => Http::response([
            'error' => [
                'code' => 400,
                'status' => 'INVALID_ARGUMENT',
                'details' => [[
                    '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                    'errorCode' => 'INVALID_ARGUMENT',
                ]],
            ],
        ], 400),
    ]);

    $user = User::factory()->create();
    $device = createDevice($user, 'invalid-target');
    $notification = Notification::factory()->create(['recipient_id' => $user->id]);

    $result = app(FcmPushGateway::class)->send($device, $notification);

    expect($result->isStale())->toBeTrue();
});
test('delivery job removes stale device and records attempt', function () {
    $user = User::factory()->create();
    $device = createDevice($user, 'job-stale-token');
    $notification = Notification::factory()->create(['recipient_id' => $user->id]);
    $delivery = MobilePushDelivery::query()->create([
        'id' => (string) Str::uuid(),
        'notification_id' => $notification->id,
        'mobile_device_id' => $device->id,
        'status' => 'pending',
        'attempts' => 0,
    ]);
    $gateway = $this->mock(MobilePushGateway::class, function (MockInterface $mock): void {
        $mock->shouldReceive('send')->once()->andReturn(PushDeliveryResult::stale());
    });

    (new DeliverMobilePush($delivery->id))->handle($gateway);

    $this->assertDatabaseMissing('mobile_devices', ['id' => $device->id]);
    $this->assertDatabaseHas('mobile_push_deliveries', [
        'id' => $delivery->id,
        'status' => 'stale',
        'attempts' => 1,
        'mobile_device_id' => null,
    ]);
});
test('firebase access token provider signs service account assertion and caches token', function () {
    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    $this->assertNotFalse($key);

    $privateKey = '';
    expect(openssl_pkey_export($key, $privateKey))->toBeTrue();

    $credentialsPath = tempnam(sys_get_temp_dir(), 'jod-firebase-');
    $this->assertNotFalse($credentialsPath);
    file_put_contents($credentialsPath, json_encode([
        'client_email' => 'firebase-adminsdk@example.iam.gserviceaccount.com',
        'private_key' => $privateKey,
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ], JSON_THROW_ON_ERROR));

    config(['mobile_push.fcm.credentials' => $credentialsPath]);
    Cache::flush();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'firebase-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
    ]);

    try {
        $provider = app(FirebaseAccessTokenProvider::class);

        expect($provider->token())->toBe('firebase-access-token');
        expect($provider->token())->toBe('firebase-access-token');

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $assertion = (string) ($request['assertion'] ?? '');

            return $request->url() === 'https://oauth2.googleapis.com/token'
                && ($request['grant_type'] ?? null) === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && count(explode('.', $assertion)) === 3;
        });
    } finally {
        @unlink($credentialsPath);
    }
});
function createDevice(User $user, string $pushToken, string $pushTargetType = 'token'): MobileDevice
{
    return MobileDevice::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'push_token' => $pushToken,
        'push_target_type' => $pushTargetType,
        'platform' => 'android',
        'device_id' => (string) Str::uuid(),
        'app_version' => '1.0.0',
        'last_seen_at' => now(),
    ]);
}
