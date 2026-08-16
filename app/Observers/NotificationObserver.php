<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\DeliverMobilePush;
use App\Models\MobileDevice;
use App\Models\MobilePushDelivery;
use App\Models\Notification;
use Illuminate\Support\Str;

class NotificationObserver
{
    public function created(Notification $notification): void
    {
        if (! (bool) config('mobile_push.enabled')
            || config('mobile_push.provider') !== 'fcm'
            || $notification->mailbox !== 'inbox'
            || blank($notification->recipient_id)) {
            return;
        }

        $devices = MobileDevice::query()
            ->where('user_id', $notification->recipient_id)
            ->get(['id']);

        foreach ($devices as $device) {
            $delivery = MobilePushDelivery::query()->firstOrCreate(
                [
                    'notification_id' => $notification->id,
                    'mobile_device_id' => $device->id,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'status' => 'pending',
                    'attempts' => 0,
                ],
            );

            if ($delivery->wasRecentlyCreated) {
                DeliverMobilePush::dispatch($delivery->id)->afterCommit();
            }
        }
    }
}
