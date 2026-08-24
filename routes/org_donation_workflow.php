<?php

declare(strict_types=1);

use App\Http\Controllers\API\Org\DonationWorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'access-token', 'org-active'])
    ->prefix('v1/org/donations')
    ->name('org.donations.')
    ->group(function (): void {
        Route::get('/', [DonationWorkflowController::class, 'index'])->name('index');
        Route::get('{donation}', [DonationWorkflowController::class, 'show'])->name('show');
        Route::patch('{donation}/contact', [DonationWorkflowController::class, 'contact'])->name('contact');
        Route::patch('{donation}/agree', [DonationWorkflowController::class, 'agree'])->name('agree');
        Route::patch('{donation}/complete', [DonationWorkflowController::class, 'complete'])->name('complete');
        Route::patch('{donation}/cancel', [DonationWorkflowController::class, 'cancel'])->name('cancel');
    });
