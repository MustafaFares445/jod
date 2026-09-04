<?php

declare(strict_types=1);

use App\Http\Controllers\Mobile\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile/auth')->name('mobile.auth.')->group(function (): void {
    Route::post('verify-account', [AuthController::class, 'verifyAccount'])
        ->middleware('throttle:10,1')
        ->name('verify-account');

    Route::post('resend-verification', [AuthController::class, 'resendAccountVerification'])
        ->middleware('throttle:10,1')
        ->name('resend-verification');
});
