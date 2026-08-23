<?php

declare(strict_types=1);

use App\Http\Controllers\Mobile\AuthController;
use App\Http\Controllers\Mobile\CampaignApplicationController;
use App\Http\Controllers\Mobile\DiscoveryController;
use App\Http\Controllers\Mobile\DonationController;
use App\Http\Controllers\Mobile\LookupController;
use App\Http\Controllers\Mobile\MeController;
use App\Http\Controllers\Mobile\MobileDeviceController;
use App\Http\Controllers\Mobile\NotificationController;
use App\Http\Controllers\Mobile\PostEngagementController;
use App\Http\Controllers\Mobile\PostImageController;
use App\Http\Controllers\Mobile\PostReportController;
use App\Http\Controllers\Mobile\SavedPostController;
use App\Http\Controllers\Mobile\UserPostController;
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
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
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
        Route::get('publishers/{publisher}', [DiscoveryController::class, 'showPublisher'])->name('publishers.show');
        Route::get('publishers/{publisher}/posts', [DiscoveryController::class, 'publisherPosts'])->name('publishers.posts');
        Route::get('campaigns', [DiscoveryController::class, 'campaigns'])->name('campaigns');
        Route::get('campaigns/{campaign}', [DiscoveryController::class, 'showCampaign'])->name('campaigns.show');
        Route::get('categories', [DiscoveryController::class, 'categories'])->name('categories');
    });

Route::prefix('lookups')
    ->name('lookups.')
    ->middleware('throttle:60,1')
    ->group(function (): void {
        Route::get('cities', [LookupController::class, 'cities'])->name('cities');
        Route::get('report-reasons', [LookupController::class, 'reportReasons'])->name('report-reasons');
        Route::get('post-types', [LookupController::class, 'postTypes'])->name('post-types');
        Route::get('post-statuses', [LookupController::class, 'postStatuses'])->name('post-statuses');
        Route::get('cta-states', [LookupController::class, 'ctaStates'])->name('cta-states');
        Route::get('notification-types', [LookupController::class, 'notificationTypes'])->name('notification-types');
        Route::get('donation-flows', [LookupController::class, 'donationFlows'])->name('donation-flows');
        Route::get('donation-statuses', [LookupController::class, 'donationStatuses'])->name('donation-statuses');
    });

/*
|--------------------------------------------------------------------------
| Authenticated mobile routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'mobile-access-token'])->group(function (): void {
    Route::prefix('auth')
        ->name('auth.')
        ->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        });

    Route::prefix('me')
        ->name('me.')
        ->group(function (): void {
            Route::get('/', [MeController::class, 'profile'])->name('profile');
            Route::get('posts', [UserPostController::class, 'index'])->name('posts.index');
            Route::get('saved-posts', [SavedPostController::class, 'index'])->name('saved-posts.index');
            Route::get('donations', [DonationController::class, 'index'])->name('donations.index');
            Route::get('donations/{donation}', [DonationController::class, 'show'])->name('donations.show');
            Route::get('applications', [CampaignApplicationController::class, 'index'])->name('applications.index');
            Route::get('applications/{application}', [CampaignApplicationController::class, 'show'])->name('applications.show');
            Route::delete('applications/{application}', [CampaignApplicationController::class, 'destroy'])->name('applications.destroy');
            Route::put('devices', [MobileDeviceController::class, 'store'])->name('devices.store');
            Route::delete('devices/{device}', [MobileDeviceController::class, 'destroy'])->name('devices.destroy');

            Route::prefix('notifications')
                ->name('notifications.')
                ->group(function (): void {
                    Route::get('/', [NotificationController::class, 'index'])->name('index');
                    Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
                    Route::patch('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
                    Route::get('{notification}', [NotificationController::class, 'show'])->name('show');
                    Route::patch('{notification}/read', [NotificationController::class, 'markRead'])->name('read');
                    Route::patch('{notification}/unread', [NotificationController::class, 'markUnread'])->name('unread');
                });

            Route::patch('profile', [MeController::class, 'updateProfile'])->name('profile.update');
            Route::patch('change-password', [MeController::class, 'changePassword'])->name('change-password');
            Route::get('permissions', [MeController::class, 'permissions'])->name('permissions');
        });

    Route::post('campaigns/{campaign}/applications', [CampaignApplicationController::class, 'store'])
        ->name('campaigns.applications.store');
    Route::post('campaigns/{campaign}/donations', [DonationController::class, 'store'])
        ->name('campaigns.donations.store');

    Route::prefix('posts')
        ->name('posts.')
        ->group(function (): void {
            Route::post('/', [UserPostController::class, 'store'])->name('store');
            Route::patch('{post}', [UserPostController::class, 'update'])->name('update');
            Route::post('{post}/images', [PostImageController::class, 'store'])->name('images.store');
            Route::patch('{post}/images/order', [PostImageController::class, 'reorder'])->name('images.reorder');
            Route::delete('{post}/images/{image}', [PostImageController::class, 'destroy'])->name('images.destroy');
            Route::post('{post}/like', [PostEngagementController::class, 'like'])->name('like');
            Route::delete('{post}/like', [PostEngagementController::class, 'unlike'])->name('unlike');
            Route::post('{post}/save', [PostEngagementController::class, 'save'])->name('save');
            Route::delete('{post}/save', [PostEngagementController::class, 'unsave'])->name('unsave');
            Route::post('{post}/reports', [PostReportController::class, 'store'])->name('reports.store');
            Route::post('{post}/submit', [UserPostController::class, 'submit'])->name('submit');
            Route::post('{post}/archive', [UserPostController::class, 'archive'])->name('archive');
            Route::post('{post}/repost', [UserPostController::class, 'repost'])->name('repost');
            Route::delete('{post}', [UserPostController::class, 'destroy'])->name('destroy');
        });
});
