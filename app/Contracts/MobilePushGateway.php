<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\MobileDevice;
use App\Models\Notification;
use App\Support\MobilePush\PushDeliveryResult;

interface MobilePushGateway
{
    public function send(MobileDevice $device, Notification $notification): PushDeliveryResult;
}
