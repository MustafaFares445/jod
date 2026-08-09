<?php

use App\Http\Controllers\API\Auth\CompanyAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/company/auth')->group(function (): void {
    Route::post('login', [CompanyAuthController::class, 'login']);
    Route::post('register', [CompanyAuthController::class, 'register']);
});
