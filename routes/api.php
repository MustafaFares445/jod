<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('v1/me')->group(function () {
        Route::get('/', \App\Http\Controllers\API\Me\ProfileController::class);
        Route::get('/permissions', \App\Http\Controllers\API\Me\PermissionsController::class);
        Route::get('/dashboard-context', \App\Http\Controllers\API\Me\DashboardContextController::class);
    });

    Route::prefix('v1/admin')->group(function () {
        Route::apiResource('users', \App\Http\Controllers\API\Admin\UserController::class);
        Route::patch('users/{user}/status', \App\Http\Controllers\API\Admin\UserController::class . '@updateStatus');
        Route::patch('users/{user}/password', \App\Http\Controllers\API\Admin\UserController::class . '@updatePassword');

        Route::apiResource('organizations', \App\Http\Controllers\API\Admin\OrganizationController::class);
        Route::patch('organizations/{organization}/status', \App\Http\Controllers\API\Admin\OrganizationController::class . '@updateStatus');
        Route::patch('organizations/{organization}/verification', \App\Http\Controllers\API\Admin\OrganizationController::class . '@updateVerification');
        Route::post('organizations/{organization}/accept', \App\Http\Controllers\API\Admin\OrganizationController::class . '@accept');

        Route::prefix('review')->group(function () {
            Route::get('posts', \App\Http\Controllers\API\Admin\Review\PostController::class . '@index');
            Route::get('posts/{post}', \App\Http\Controllers\API\Admin\Review\PostController::class . '@show');
            Route::post('posts/{post}/approve', \App\Http\Controllers\API\Admin\Review\PostController::class . '@approve');
            Route::post('posts/{post}/reject', \App\Http\Controllers\API\Admin\Review\PostController::class . '@reject');

            Route::get('campaigns', \App\Http\Controllers\API\Admin\Review\CampaignController::class . '@index');
            Route::get('campaigns/{campaign}', \App\Http\Controllers\API\Admin\Review\CampaignController::class . '@show');
            Route::post('campaigns/{campaign}/approve', \App\Http\Controllers\API\Admin\Review\CampaignController::class . '@approve');
            Route::post('campaigns/{campaign}/reject', \App\Http\Controllers\API\Admin\Review\CampaignController::class . '@reject');
        });

        Route::prefix('reports')->group(function () {
            Route::get('/', \App\Http\Controllers\API\Admin\ReportController::class . '@index');
            Route::get('{report}', \App\Http\Controllers\API\Admin\ReportController::class . '@show');
            Route::post('{report}/claim', \App\Http\Controllers\API\Admin\ReportController::class . '@claim');
            Route::post('{report}/request-info', \App\Http\Controllers\API\Admin\ReportController::class . '@requestInfo');
            Route::post('{report}/close', \App\Http\Controllers\API\Admin\ReportController::class . '@close');
        });

        Route::apiResource('notifications', \App\Http\Controllers\API\Admin\NotificationController::class);
        Route::patch('notifications/{notification}/read-state', \App\Http\Controllers\API\Admin\NotificationController::class . '@updateReadState');
        Route::post('notifications/{notification}/resend', \App\Http\Controllers\API\Admin\NotificationController::class . '@resend');

        // Phase 2: Admin secondary endpoints
        Route::apiResource('badges', \App\Http\Controllers\API\Admin\BadgeController::class);
        Route::patch('badges/{badge}/status', \App\Http\Controllers\API\Admin\BadgeController::class . '@updateStatus');

        Route::apiResource('articles', \App\Http\Controllers\API\Admin\ArticleController::class);

        Route::get('overview', \App\Http\Controllers\API\Admin\OverviewController::class);
        Route::get('analytics/kpis', \App\Http\Controllers\API\Admin\AnalyticsController::class . '@kpis');
        Route::get('analytics/weekly', \App\Http\Controllers\API\Admin\AnalyticsController::class . '@weekly');

        Route::get('audit-logs', \App\Http\Controllers\API\Admin\AuditLogController::class . '@index');

        Route::get('platform-settings', \App\Http\Controllers\API\Admin\SettingsController::class . '@index');
        Route::patch('platform-settings', \App\Http\Controllers\API\Admin\SettingsController::class . '@update');
    });

    Route::prefix('v1/org')->group(function () {
        Route::get('overview', \App\Http\Controllers\API\Org\OverviewController::class);

        Route::apiResource('campaigns', \App\Http\Controllers\API\Org\CampaignController::class);
        Route::post('campaigns/{campaign}/close', \App\Http\Controllers\API\Org\CampaignController::class . '@close');

        Route::apiResource('posts', \App\Http\Controllers\API\Org\PostController::class);
        Route::post('posts/{post}/publish', \App\Http\Controllers\API\Org\PostController::class . '@publish');
        Route::post('posts/{post}/archive', \App\Http\Controllers\API\Org\PostController::class . '@archive');
        Route::post('posts/{post}/restore', \App\Http\Controllers\API\Org\PostController::class . '@restore');

        Route::apiResource('donors', \App\Http\Controllers\API\Org\DonorController::class);
        Route::apiResource('applicants', \App\Http\Controllers\API\Org\ApplicantController::class);

        Route::apiResource('notifications', \App\Http\Controllers\API\Org\NotificationController::class);
        Route::patch('notifications/{notification}/read-state', \App\Http\Controllers\API\Org\NotificationController::class . '@updateReadState');
        Route::post('notifications/{notification}/resend', \App\Http\Controllers\API\Org\NotificationController::class . '@resend');

        Route::get('reports', \App\Http\Controllers\API\Org\ReportController::class . '@index');
        Route::get('reports/{report}', \App\Http\Controllers\API\Org\ReportController::class . '@show');

        Route::get('settings/profile', \App\Http\Controllers\API\Org\SettingsController::class . '@profile');
        Route::patch('settings/profile', \App\Http\Controllers\API\Org\SettingsController::class . '@updateProfile');

        Route::apiResource('staff', \App\Http\Controllers\API\Org\StaffController::class);
        Route::apiResource('roles', \App\Http\Controllers\API\Org\RoleController::class);
        Route::get('permissions/catalog', \App\Http\Controllers\API\Org\PermissionsController::class);
    });
});
