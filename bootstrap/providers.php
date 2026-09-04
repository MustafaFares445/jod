<?php

use App\Providers\AdminPersonalizationServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\MobilePushServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    MobilePushServiceProvider::class,
    AdminPersonalizationServiceProvider::class,
];
