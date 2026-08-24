<?php

use App\Http\Controllers\API\Admin\AnalyticsController;
use App\Http\Controllers\API\Admin\ArticleController;
use App\Http\Controllers\API\Admin\AuditLogController;
use App\Http\Controllers\API\Admin\BadgeController;
use App\Http\Controllers\API\Admin\CategoryController;
use App\Http\Controllers\API\Admin\NotificationController;
use App\Http\Controllers\API\Admin\OrganizationController;
use App\Http\Controllers\API\Admin\OverviewController;
use App\Http\Controllers\API\Admin\PostController as AdminPostController;
use App\Http\Controllers\API\Admin\ReportController;
use App\Http\Controllers\API\Admin\SettingsController;
use App\Http\Controllers\API\Admin\UserController;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\FirebasePushTestController;
use App\Http\Controllers\API\Me\DashboardContextController;
use App\Http\Controllers\API\Me\PermissionsController;
use App\Http\Controllers\API\Me\ProfileController;
use App\Http\Controllers\API\Org\ApplicantController;
use App\Http\Controllers\API\Org\BriefController;
use App\Http\Controllers\API\Org\CampaignController;
use App\Http\Controllers\API\Org\DonorController;
use App\Http\Controllers\API\Org\PostController;
use App\Http\Controllers\API\Org\RoleController;
use App\Http\Controllers\API\Org\StaffController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')
    ->name('mobile.')
    ->group(base_path('routes/mobile.php'));

Route::post('v1/firebase/test-push', FirebasePushTestController::class)
    ->middleware('throttle:5,1')
    ->name('firebase.test-push');

Route::prefix('v1/auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    Route::middleware(['auth:sanctum', 'access-token'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum', 'access-token'])->group(function () {
    // Single admin CRUD surface for every post that can feed mobile discovery.
    Route::apiResource('posts', AdminPostController::class);

    Route::prefix('v1/me')->group(function () {
        Route::get('/', ProfileController::class);
        Route::patch('/profile', [ProfileController::class, 'update']);
        Route::patch('/password', [ProfileController::class, 'updatePassword']);
        Route::get('/permissions', PermissionsController::class);
        Route::get('/dashboard-context', DashboardContextController::class);
    });

    Route::prefix('v1/admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::patch('users/{user}/status', UserController::class.'@updateStatus');
        Route::patch('users/{user}/password', UserController::class.'@updatePassword');

        Route::apiResource('organizations', OrganizationController::class);
        Route::patch('organizations/{organization}/status', OrganizationController::class.'@updateStatus');
        Route::patch('organizations/{organization}/verification', OrganizationController::class.'@updateVerification');
        Route::post('organizations/{organization}/accept', OrganizationController::class.'@accept');

        Route::get('organizations/{organization}/videos', [App\Http\Controllers\API\Admin\OrganizationVideoController::class, 'index']);
        Route::get('organizations/{organization}/videos/{video}', [App\Http\Controllers\API\Admin\OrganizationVideoController::class, 'show']);
        Route::delete('organizations/{organization}/videos/{video}', [App\Http\Controllers\API\Admin\OrganizationVideoController::class, 'destroy']);
        Route::post('organizations/{organization}/videos/uploads', [App\Http\Controllers\API\Admin\OrganizationVideoUploadController::class, 'initiate']);
        Route::get('organizations/{organization}/videos/uploads/{upload}', [App\Http\Controllers\API\Admin\OrganizationVideoUploadController::class, 'status']);
        Route::put('organizations/{organization}/videos/uploads/{upload}/chunks/{chunk}', [App\Http\Controllers\API\Admin\OrganizationVideoUploadController::class, 'chunk'])->whereNumber('chunk');
        Route::post('organizations/{organization}/videos/uploads/{upload}/pause', [App\Http\Controllers\API\Admin\OrganizationVideoUploadController::class, 'pause']);
        Route::post('organizations/{organization}/videos/uploads/{upload}/resume', [App\Http\Controllers\API\Admin\OrganizationVideoUploadController::class, 'resume']);
        Route::post('organizations/{organization}/videos/uploads/{upload}/complete', [App\Http\Controllers\API\Admin\OrganizationVideoUploadController::class, 'complete']);
        Route::delete('organizations/{organization}/videos/uploads/{upload}', [App\Http\Controllers\API\Admin\OrganizationVideoUploadController::class, 'cancel']);

        Route::apiResource('posts', AdminPostController::class);

        Route::prefix('review')->group(function () {
            Route::get('posts', App\Http\Controllers\API\Admin\Review\PostController::class.'@index');
            Route::get('posts/{post}', App\Http\Controllers\API\Admin\Review\PostController::class.'@show');
            Route::post('posts/{post}/approve', App\Http\Controllers\API\Admin\Review\PostController::class.'@approve');
            Route::post('posts/{post}/reject', App\Http\Controllers\API\Admin\Review\PostController::class.'@reject');
        });

        Route::prefix('reports')->group(function () {
            Route::get('/', ReportController::class.'@index');
            Route::get('{report}', ReportController::class.'@show');
            Route::post('{report}/claim', ReportController::class.'@claim');
            Route::post('{report}/request-info', ReportController::class.'@requestInfo');
            Route::post('{report}/close', ReportController::class.'@close');
        });

        Route::apiResource('notifications', NotificationController::class);
        Route::patch('notifications/{notification}/read-state', NotificationController::class.'@updateReadState');
        Route::post('notifications/{notification}/resend', NotificationController::class.'@resend');

        Route::apiResource('badges', BadgeController::class);
        Route::patch('badges/{badge}/status', BadgeController::class.'@updateStatus');

        Route::apiResource('articles', ArticleController::class);
        Route::apiResource('categories', CategoryController::class);
        Route::patch('categories/{category}/status', CategoryController::class.'@updateStatus');

        Route::get('overview', OverviewController::class);
        Route::get('analytics/kpis', AnalyticsController::class.'@kpis');
        Route::get('analytics/weekly', AnalyticsController::class.'@weekly');

        Route::get('audit-logs', AuditLogController::class.'@index');

        Route::get('platform-settings', SettingsController::class.'@index');
        Route::patch('platform-settings', SettingsController::class.'@update');
    });

    Route::prefix('v1/org')->middleware('org-active')->group(function () {
        Route::get('overview', App\Http\Controllers\API\Org\OverviewController::class);
        Route::get('dashboard/overview', App\Http\Controllers\API\Org\OverviewController::class);

        Route::get('categories/brief', [BriefController::class, 'categories']);
        Route::get('campaigns/brief', [BriefController::class, 'campaigns']);

        Route::apiResource('campaigns', CampaignController::class);
        Route::post('campaigns/{campaign}/close', CampaignController::class.'@close');

        Route::apiResource('posts', PostController::class);
        Route::patch('posts/{post}/status', PostController::class.'@updateStatus');
        Route::post('posts/{post}/publish', PostController::class.'@publish');
        Route::post('posts/{post}/archive', PostController::class.'@archive');
        Route::post('posts/{post}/restore', PostController::class.'@restore');

        Route::get('videos', [App\Http\Controllers\API\Org\OrganizationVideoController::class, 'index']);
        Route::get('videos/{video}', [App\Http\Controllers\API\Org\OrganizationVideoController::class, 'show']);
        Route::delete('videos/{video}', [App\Http\Controllers\API\Org\OrganizationVideoController::class, 'destroy']);
        Route::post('videos/uploads', [App\Http\Controllers\API\Org\OrganizationVideoUploadController::class, 'initiate']);
        Route::get('videos/uploads/{upload}', [App\Http\Controllers\API\Org\OrganizationVideoUploadController::class, 'status']);
        Route::put('videos/uploads/{upload}/chunks/{chunk}', [App\Http\Controllers\API\Org\OrganizationVideoUploadController::class, 'chunk'])->whereNumber('chunk');
        Route::post('videos/uploads/{upload}/pause', [App\Http\Controllers\API\Org\OrganizationVideoUploadController::class, 'pause']);
        Route::post('videos/uploads/{upload}/resume', [App\Http\Controllers\API\Org\OrganizationVideoUploadController::class, 'resume']);
        Route::post('videos/uploads/{upload}/complete', [App\Http\Controllers\API\Org\OrganizationVideoUploadController::class, 'complete']);
        Route::delete('videos/uploads/{upload}', [App\Http\Controllers\API\Org\OrganizationVideoUploadController::class, 'cancel']);

        Route::apiResource('donors', DonorController::class);
        Route::apiResource('applicants', ApplicantController::class);

        Route::apiResource('notifications', App\Http\Controllers\API\Org\NotificationController::class);
        Route::patch('notifications/{notification}/read', App\Http\Controllers\API\Org\NotificationController::class.'@markRead');
        Route::patch('notifications/{notification}/read-state', App\Http\Controllers\API\Org\NotificationController::class.'@updateReadState');
        Route::post('notifications/{notification}/resend', App\Http\Controllers\API\Org\NotificationController::class.'@resend');

        Route::get('reports', App\Http\Controllers\API\Org\ReportController::class.'@index');
        Route::get('reports/{report}', App\Http\Controllers\API\Org\ReportController::class.'@show');
        Route::patch('reports/{report}/status', App\Http\Controllers\API\Org\ReportController::class.'@updateStatus');
        Route::post('reports/{report}/claim', App\Http\Controllers\API\Org\ReportController::class.'@claim');
        Route::post('reports/{report}/request-info', App\Http\Controllers\API\Org\ReportController::class.'@requestInfo');
        Route::post('reports/{report}/close', App\Http\Controllers\API\Org\ReportController::class.'@close');
        Route::get('audit-logs', App\Http\Controllers\API\Org\AuditLogController::class.'@index');

        Route::get('profile', App\Http\Controllers\API\Org\SettingsController::class.'@profile');
        Route::put('profile', App\Http\Controllers\API\Org\SettingsController::class.'@updateProfile');
        Route::get('settings/profile', App\Http\Controllers\API\Org\SettingsController::class.'@profile');
        Route::patch('settings/profile', App\Http\Controllers\API\Org\SettingsController::class.'@updateProfile');
        Route::patch('settings/password', [ProfileController::class, 'updatePassword']);
        Route::get('settings/bank-account', App\Http\Controllers\API\Org\SettingsController::class.'@bankAccount');
        Route::patch('settings/bank-account', App\Http\Controllers\API\Org\SettingsController::class.'@updateBankAccount');

        Route::apiResource('staff/roles', RoleController::class);
        Route::apiResource('staff', StaffController::class);
        Route::apiResource('roles', RoleController::class);
        Route::get('permissions/catalog', App\Http\Controllers\API\Org\PermissionsController::class);
    });
});
