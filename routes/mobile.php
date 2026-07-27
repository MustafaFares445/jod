<?php

declare(strict_types=1);

use App\Http\Controllers\Mobile\AuthController;
use App\Http\Controllers\Mobile\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')
    ->name('auth.')
    ->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->name('login');

        Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout'])->name('logout');
    });

Route::middleware('auth:sanctum')
    ->prefix('me')
    ->name('me.')
    ->group(function (): void {
        Route::get('/', [MeController::class, 'profile'])->name('profile');
        Route::patch('profile', [MeController::class, 'updateProfile'])->name('profile.update');
        Route::get('permissions', [MeController::class, 'permissions'])->name('permissions');
        Route::get('dashboard-context', [MeController::class, 'dashboardContext'])->name('dashboard-context');
        Route::get('ping', [MeController::class, 'ping'])->name('ping');
    });
