<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MobileDeviceService
{
    /**
     * @param  array{pushToken: string, platform: string, deviceId?: string|null, appVersion?: string|null}  $attributes
     */
    public function register(User $user, array $attributes): MobileDevice
    {
        return DB::transaction(function () use ($user, $attributes): MobileDevice {
            $device = MobileDevice::query()
                ->where('push_token', $attributes['pushToken'])
                ->lockForUpdate()
                ->first();

            if ($device === null && filled($attributes['deviceId'] ?? null)) {
                $device = MobileDevice::query()
                    ->where('device_id', $attributes['deviceId'])
                    ->lockForUpdate()
                    ->first();
            }

            $device ??= new MobileDevice();
            $device->forceFill([
                'user_id' => $user->id,
                'push_token' => $attributes['pushToken'],
                'platform' => $attributes['platform'],
                'device_id' => $attributes['deviceId'] ?? null,
                'app_version' => $attributes['appVersion'] ?? null,
                'last_seen_at' => now(),
            ])->save();

            return $device->refresh();
        });
    }

    public function unregister(User $user, string $deviceId): bool
    {
        $device = MobileDevice::query()
            ->where('user_id', $user->id)
            ->whereKey($deviceId)
            ->first();

        if ($device === null) {
            return false;
        }

        return (bool) $device->delete();
    }
}
