<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileDeviceResource extends JsonResource
{
    /**
     * @return array{id: string, platform: string, deviceId: string|null, appVersion: string|null, lastSeenAt: string|null, createdAt: string|null, updatedAt: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'platform' => (string) $this->platform,
            'deviceId' => $this->device_id,
            'appVersion' => $this->app_version,
            'lastSeenAt' => $this->last_seen_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
