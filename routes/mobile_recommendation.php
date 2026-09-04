<?php

declare(strict_types=1);

use App\Http\Controllers\Mobile\ExplorationFeedbackController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')
    ->name('mobile.recommendations.')
    ->middleware(['auth:sanctum', 'mobile-access-token'])
    ->group(function (): void {
        Route::post('recommendations/exploration-feedback', ExplorationFeedbackController::class)
            ->name('exploration-feedback');
    });
