<?php

declare(strict_types=1);

use App\Http\Controllers\Mobile\AuthController;
use App\Http\Controllers\Mobile\DiscoveryController;
use App\Http\Controllers\Mobile\MeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public mobile routes
|--------------------------------------------------------------------------
*/

Route::prefix('auth')
    ->name('auth.')
    ->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('verify-reset-code', [AuthController::class, 'verifyResetCode'])->name('verify-reset-code');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    });

Route::prefix('discovery')
    ->name('discovery.')
    ->middleware('throttle:60,1')
    ->group(function (): void {
        Route::get('posts', [DiscoveryController::class, 'posts'])->name('posts');
        Route::get('posts/{post}', [DiscoveryController::class, 'showPost'])->name('posts.show');
        Route::get('campaigns', [DiscoveryController::class, 'campaigns'])->name('campaigns');
        Route::get('campaigns/{campaign}', [DiscoveryController::class, 'showCampaign'])->name('campaigns.show');
        Route::get('categories', [DiscoveryController::class, 'categories'])->name('categories');
    });

/*
|--------------------------------------------------------------------------
| Authenticated mobile routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('auth')
        ->name('auth.')
        ->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        });

    Route::prefix('me')
        ->name('me.')
        ->group(function (): void {
            Route::get('/', [MeController::class, 'profile'])->name('profile');
            Route::patch('profile', [MeController::class, 'updateProfile'])->name('profile.update');
            Route::patch('change-password', [MeController::class, 'changePassword'])->name('change-password');
            Route::get('permissions', [MeController::class, 'permissions'])->name('permissions');
            Route::get('dashboard-context', [MeController::class, 'dashboardContext'])->name('dashboard-context');
            Route::get('ping', [MeController::class, 'ping'])->name('ping');
        });
});
