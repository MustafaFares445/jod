<?php

declare(strict_types=1);

use App\Http\Controllers\Mobile\AuthController;
use App\Http\Controllers\Mobile\AvatarController;
use App\Http\Controllers\Mobile\BlogController;
use App\Http\Controllers\Mobile\CampaignApplicationController;
use App\Http\Controllers\Mobile\DiscoveryController;
use App\Http\Controllers\Mobile\DonationController;
use App\Http\Controllers\Mobile\MeController;
use App\Http\Controllers\Mobile\MobileDeviceController;
use App\Http\Controllers\Mobile\NotificationController;
use App\Http\Controllers\Mobile\PostCommentController;
use App\Http\Controllers\Mobile\PostEngagementController;
use App\Http\Controllers\Mobile\PostImageController;
use App\Http\Controllers\Mobile\PostReportController;
use App\Http\Controllers\Mobile\ProfilePostController;
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
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:5,1')
            ->name('register');
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1')
            ->name('login');
        Route::post('refresh', [AuthController::class, 'refresh'])
            ->middleware('throttle:30,1')
            ->name('refresh');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:5,1')
            ->name('forgot-password');
        Route::post('verify-reset-code', [AuthController::class, 'verifyResetCode'])
            ->middleware('throttle:10,1')
            ->name('verify-reset-code');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:5,1')
            ->name('reset-password');
    });

Route::middleware('throttle:60,1')->group(function (): void {
    Route::get('blogs', [BlogController::class, 'index'])->name('blogs.index');
    Route::get('blogs/{blog}', [BlogController::class, 'show'])->name('blogs.show');
    Route::get('blog-categories', [BlogController::class, 'categories'])->name('blogs.categories');
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
            Route::get('profile-posts', [ProfilePostController::class, 'index'])->name('profile-posts.index');
            Route::get('saved-posts', [SavedPostController::class, 'index'])->name('saved-posts.index');
            Route::get('donations', [DonationController::class, 'index'])->name('donations.index');
            Route::get('donations/{donation}', [DonationController::class, 'show'])->name('donations.show');
            Route::get('applications', [CampaignApplicationController::class, 'index'])->name('applications.index');
            Route::get('applications/{application}', [CampaignApplicationController::class, 'show'])->name('applications.show');
            Route::delete('applications/{application}', [CampaignApplicationController::class, 'destroy'])->name('applications.destroy');
            Route::post('avatar', [AvatarController::class, 'store'])->name('avatar.store');
            Route::delete('avatar', [AvatarController::class, 'destroy'])->name('avatar.destroy');
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
            Route::post('{post}/share', [PostEngagementController::class, 'share'])->name('share');
            Route::get('{post}/comments', [PostCommentController::class, 'index'])->name('comments.index');
            Route::post('{post}/comments', [PostCommentController::class, 'store'])->name('comments.store');
            Route::patch('{post}/comments/{comment}', [PostCommentController::class, 'update'])->name('comments.update');
            Route::delete('{post}/comments/{comment}', [PostCommentController::class, 'destroy'])->name('comments.destroy');
            Route::post('{post}/reports', [PostReportController::class, 'store'])->name('reports.store');
            Route::post('{post}/submit', [UserPostController::class, 'submit'])->name('submit');
            Route::post('{post}/archive', [UserPostController::class, 'archive'])->name('archive');
            Route::post('{post}/repost', [UserPostController::class, 'repost'])->name('repost');
            Route::delete('{post}', [UserPostController::class, 'destroy'])->name('destroy');
        });
});
