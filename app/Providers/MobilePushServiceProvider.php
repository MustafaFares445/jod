<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\MobilePushGateway;
use App\Models\Notification;
use App\Observers\NotificationObserver;
use App\Services\Mobile\FcmPushGateway;
use Illuminate\Support\ServiceProvider;

class MobilePushServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MobilePushGateway::class, FcmPushGateway::class);
    }

    public function boot(): void
    {
        Notification::observe(NotificationObserver::class);
    }
}
