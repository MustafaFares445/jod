<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Contracts\MobilePushGateway;
use App\Jobs\DeliverMobilePush;
use App\Models\MobileDevice;
use App\Models\MobilePushDelivery;
use App\Models\Notification;
use App\Models\User;
use App\Services\Mobile\FcmPushGateway;
use App\Services\Mobile\FirebaseAccessTokenProvider;
use App\Support\MobilePush\PushDeliveryResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class MobilePushDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mobile_push.enabled' => false,
            'mobile_push.provider' => 'fcm',
            'mobile_push.fcm.project_id' => 'jod-test',
        ]);
    }

    public function test_inbox_notification_queues_one_delivery_per_registered_device(): void
    {
        Queue::fake();
        config(['mobile_push.enabled' => true]);

        $user = User::factory()->create();
        $firstDevice = $this->createDevice($user, 'first-token');
        $secondDevice = $this->createDevice($user, 'second-token');

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
        $this->assertSame(2, MobilePushDelivery::query()->where('notification_id', $notification->id)->count());
        Queue::assertPushed(DeliverMobilePush::class, 2);
    }

    public function test_push_delivery_is_not_created_when_feature_is_disabled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->createDevice($user, 'disabled-token');

        Notification::factory()->create([
            'recipient_id' => $user->id,
            'mailbox' => 'inbox',
        ]);

        $this->assertDatabaseCount('mobile_push_deliveries', 0);
        Queue::assertNothingPushed();
    }

    public function test_fcm_gateway_sends_notification_and_deep_link_payload(): void
    {
        $this->mock(FirebaseAccessTokenProvider::class, function (MockInterface $mock): void {
            $mock->shouldReceive('token')->once()->andReturn('oauth-token');
        });
        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response([
                'name' => 'projects/jod-test/messages/message-123',
            ]),
        ]);

        $user = User::factory()->create();
        $device = $this->createDevice($user, 'fcm-token');
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

        $this->assertTrue($result->isSent());
        $this->assertSame('projects/jod-test/messages/message-123', $result->providerMessageId);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://fcm.googleapis.com/v1/projects/jod-test/messages:send'
                && $request->hasHeader('Authorization', 'Bearer oauth-token')
                && data_get($payload, 'message.token') === 'fcm-token'
                && data_get($payload, 'message.notification.title') === 'Campaign updated'
                && data_get($payload, 'message.data.referencePath') === '/campaigns/campaign-123'
                && data_get($payload, 'message.android.priority') === 'HIGH';
        });
    }

    public function test_fcm_gateway_marks_unregistered_registration_token_as_stale(): void
    {
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
        $device = $this->createDevice($user, 'stale-fcm-token');
        $notification = Notification::factory()->create(['recipient_id' => $user->id]);

        $result = app(FcmPushGateway::class)->send($device, $notification);

        $this->assertTrue($result->isStale());
    }

    public function test_delivery_job_removes_stale_device_and_records_attempt(): void
    {
        $user = User::factory()->create();
        $device = $this->createDevice($user, 'job-stale-token');
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
    }

    public function test_firebase_access_token_provider_signs_service_account_assertion_and_caches_token(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($key);

        $privateKey = '';
        $this->assertTrue(openssl_pkey_export($key, $privateKey));

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

            $this->assertSame('firebase-access-token', $provider->token());
            $this->assertSame('firebase-access-token', $provider->token());

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
    }

    private function createDevice(User $user, string $pushToken): MobileDevice
    {
        return MobileDevice::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'push_token' => $pushToken,
            'platform' => 'android',
            'device_id' => (string) Str::uuid(),
            'app_version' => '1.0.0',
            'last_seen_at' => now(),
        ]);
    }
}
