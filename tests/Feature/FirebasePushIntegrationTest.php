<?php

declare(strict_types=1);

use App\Contracts\MobilePushGateway;
use App\Jobs\DeliverMobilePush;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\User;
use App\Services\Mobile\MobileDeviceService;
use App\Support\MobilePush\PushDeliveryResult;
use Database\Seeders\Permissions\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

test('dashboard admin and company logins store FCM tokens as web devices', function () {
    $this->seed(PermissionsSeeder::class);

    $organization = Organization::factory()->create();
    $admin = User::factory()->create([
        'email' => 'push-admin@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'admin',
        'organization_id' => null,
    ]);
    $company = User::factory()->create([
        'email' => 'push-company@example.com',
        'password' => Hash::make('password'),
        'user_type' => 'general',
        'organization_id' => $organization->id,
        'status' => 'active',
    ]);

    foreach ([
        [$admin, 'admin', 'admin-web-fcm-token', 'admin-browser'],
        [$company, 'companies', 'company-web-fcm-token', 'company-browser'],
    ] as [$user, $userType, $fcmToken, $deviceId]) {
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'userType' => $userType,
            'fcmToken' => $fcmToken,
            'deviceId' => $deviceId,
            'appVersion' => 'web-1.0.0',
        ])->assertOk();

        $this->assertDatabaseHas('mobile_devices', [
            'user_id' => $user->id,
            'push_token' => $fcmToken,
            'push_target_type' => 'token',
            'platform' => 'web',
            'device_id' => $deviceId,
            'app_version' => 'web-1.0.0',
        ]);
    }
});

test('mobile login stores the FCM token from the request', function () {
    $user = User::factory()->create([
        'email' => 'push-mobile@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->postJson('/api/mobile/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'fcmToken' => 'ios-mobile-fcm-token',
        'fcmPlatform' => 'ios',
        'deviceId' => 'ios-installation',
        'appVersion' => '2.4.0',
    ])->assertOk();

    $this->assertDatabaseHas('mobile_devices', [
        'user_id' => $user->id,
        'push_token' => 'ios-mobile-fcm-token',
        'push_target_type' => 'token',
        'platform' => 'ios',
        'device_id' => 'ios-installation',
        'app_version' => '2.4.0',
    ]);
});

test('admin company and mobile users all receive queued Firebase push deliveries', function () {
    Queue::fake();
    config([
        'mobile_push.enabled' => true,
        'mobile_push.provider' => 'fcm',
    ]);

    $organization = Organization::factory()->create();
    $admin = User::factory()->create(['user_type' => 'admin']);
    $company = User::factory()->create([
        'organization_id' => $organization->id,
        'user_type' => 'general',
    ]);
    $mobileUser = User::factory()->create(['user_type' => 'general']);
    $devices = app(MobileDeviceService::class);

    $devices->register($admin, [
        'pushToken' => 'admin-role-token',
        'platform' => 'web',
    ]);
    $devices->register($company, [
        'pushToken' => 'company-role-token',
        'platform' => 'web',
    ]);
    $devices->register($mobileUser, [
        'pushToken' => 'mobile-role-token',
        'platform' => 'android',
    ]);

    foreach ([$admin, $company, $mobileUser] as $recipient) {
        Notification::factory()->create([
            'recipient_id' => $recipient->id,
            'mailbox' => 'inbox',
            'status' => 'unread',
            'title' => 'Firebase role test',
            'body' => 'This notification should create a push delivery.',
        ]);
    }

    $this->assertDatabaseCount('mobile_push_deliveries', 3);
    Queue::assertPushed(DeliverMobilePush::class, 3);
});

test('Firebase test endpoint sends directly to the submitted FCM token', function () {
    config(['mobile_push.test_endpoint_enabled' => true]);

    $this->mock(MobilePushGateway::class, function (MockInterface $mock): void {
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(function ($device, $notification): bool {
                return $device->push_token === 'browser-fcm-token'
                    && $device->platform === 'web'
                    && $notification->title === 'Browser Firebase test'
                    && $notification->body === 'Push received in browser.';
            })
            ->andReturn(PushDeliveryResult::sent('projects/jod/messages/test-message'));
    });

    $this->postJson('/api/v1/firebase/test-push', [
        'fcmToken' => 'browser-fcm-token',
        'platform' => 'web',
        'title' => 'Browser Firebase test',
        'body' => 'Push received in browser.',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Firebase test push sent successfully.')
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.providerMessageId', 'projects/jod/messages/test-message');
});

test('Firebase test endpoint is hidden unless explicitly enabled', function () {
    config(['mobile_push.test_endpoint_enabled' => false]);

    $this->mock(MobilePushGateway::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('send');
    });

    $this->postJson('/api/v1/firebase/test-push', [
        'fcmToken' => 'browser-fcm-token',
    ])
        ->assertNotFound()
        ->assertJsonPath('message', 'Not found.');
});

test('Firebase test endpoint validates the FCM token', function () {
    config(['mobile_push.test_endpoint_enabled' => true]);

    $this->postJson('/api/v1/firebase/test-push', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['fcmToken']);
});

test('Firebase browser test page is visible only when test sending is enabled', function () {
    config(['mobile_push.test_endpoint_enabled' => true]);

    $this->get('/firebase/push-test')
        ->assertOk()
        ->assertSee('JOD Firebase Push Test')
        ->assertSee('/api/v1/firebase/test-push', false);

    config(['mobile_push.test_endpoint_enabled' => false]);

    $this->get('/firebase/push-test')->assertNotFound();
});
