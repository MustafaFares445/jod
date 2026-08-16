<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\MobileEventServiceProvider;
use App\Providers\MobilePushServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    MobilePushServiceProvider::class,
    MobileEventServiceProvider::class,
];
