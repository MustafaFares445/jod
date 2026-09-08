<?php

declare(strict_types=1);

use App\Http\Controllers\API\EmailTestController;
use Illuminate\Support\Facades\Route;

Route::post('v1/email/test', EmailTestController::class)
    ->middleware('throttle:5,1')
    ->name('email.test');
