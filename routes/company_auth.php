<?php

use App\Http\Controllers\API\Auth\CompanyAuthController;
use App\Http\Controllers\API\MediaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/company/auth')->group(function (): void {
    Route::post('register', [CompanyAuthController::class, 'register']);
});

// Kept outside the org-active middleware so a newly registered/pending
// organization can upload its logo after registration returns organizationId.
Route::middleware(['auth:sanctum', 'access-token'])
    ->prefix('v1/media')
    ->group(function (): void {
        Route::post('{model}/{modelId}/{prop}', [MediaController::class, 'upload'])
            ->whereIn('model', ['organization', 'campaign', 'post']);
        Route::post('{model}/{modelId}/{prop}/{mediaId}/replace', [MediaController::class, 'replace'])
            ->whereIn('model', ['organization', 'campaign', 'post']);
        Route::delete('{model}/{modelId}/{prop}/{mediaId}', [MediaController::class, 'destroy'])
            ->whereIn('model', ['organization', 'campaign', 'post']);
    });
