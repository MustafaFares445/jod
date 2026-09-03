<?php

declare(strict_types=1);

use App\Http\Controllers\Mobile\AuthController;
use App\Http\Controllers\Mobile\CampaignApplicationController;
use App\Http\Controllers\Mobile\DiscoveryController;
use App\Http\Controllers\Mobile\DonationController;
use App\Http\Controllers\Mobile\FollowController;
use App\Http\Controllers\Mobile\HelpOfferController;
use App\Http\Controllers\Mobile\LookupController;
use App\Http\Controllers\Mobile\MeController;
use App\Http\Controllers\Mobile\MediaDiscoveryController;
use App\Http\Controllers\Mobile\MediaEngagementController;
use App\Http\Controllers\Mobile\MediaStreamController;
use App\Http\Controllers\Mobile\MobileDeviceController;
use App\Http\Controllers\Mobile\NotificationController;
use App\Http\Controllers\Mobile\OrganizationVideoController;
use App\Http\Controllers\Mobile\PostEngagementController;
use App\Http\Controllers\Mobile\PostImageController;
use App\Http\Controllers\Mobile\PostReportController;
use App\Http\Controllers\Mobile\SavedPostController;
use App\Http\Controllers\Mobile\SearchController;
use App\Http\Controllers\Mobile\UserPostController;
use App\Http\Controllers\Mobile\UserAvatarController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('verify-reset-code', [AuthController::class, 'verifyResetCode'])->name('verify-reset-code');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
});

Route::get('search', SearchController::class)->middleware('throttle:60,1')->name('search');

Route::prefix('discovery')->name('discovery.')->middleware('throttle:60,1')->group(function (): void {
    Route::get('posts', [DiscoveryController::class, 'posts'])->name('posts');
    Route::get('posts/{post}', [DiscoveryController::class, 'showPost'])->name('posts.show');
    Route::get('media', [MediaDiscoveryController::class, 'index'])->name('media');
    Route::get('media/{video}', [MediaDiscoveryController::class, 'show'])->name('media.show');
    Route::get('media/{video}/stream', MediaStreamController::class)->name('media.stream');
    Route::get('media/{video}/preview', [MediaStreamController::class, 'preview'])->name('media.preview');
    Route::get('publishers/{publisher}', [DiscoveryController::class, 'showPublisher'])->name('publishers.show');
    Route::get('publishers/{publisher}/posts', [DiscoveryController::class, 'publisherPosts'])->name('publishers.posts');
    Route::get('organizations/{organization}/videos', [OrganizationVideoController::class, 'index'])->name('organizations.videos.index');
    Route::get('organizations/{organization}/videos/{video}', [OrganizationVideoController::class, 'show'])->name('organizations.videos.show');
    Route::get('campaigns', [DiscoveryController::class, 'campaigns'])->name('campaigns');
    Route::get('campaigns/{campaign}', [DiscoveryController::class, 'showCampaign'])->name('campaigns.show');
    Route::get('articles', [DiscoveryController::class, 'articles'])->name('articles');
    Route::get('articles/{article}', [DiscoveryController::class, 'showArticle'])->name('articles.show');
    Route::get('categories', [DiscoveryController::class, 'categories'])->name('categories');
});

Route::prefix('lookups')->name('lookups.')->middleware('throttle:60,1')->group(function (): void {
    Route::get('cities', [LookupController::class, 'cities'])->name('cities');
    Route::get('report-reasons', [LookupController::class, 'reportReasons'])->name('report-reasons');
    Route::get('post-types', [LookupController::class, 'postTypes'])->name('post-types');
    Route::get('post-statuses', [LookupController::class, 'postStatuses'])->name('post-statuses');
    Route::get('cta-states', [LookupController::class, 'ctaStates'])->name('cta-states');
    Route::get('notification-types', [LookupController::class, 'notificationTypes'])->name('notification-types');
    Route::get('donation-flows', [LookupController::class, 'donationFlows'])->name('donation-flows');
    Route::get('donation-statuses', [LookupController::class, 'donationStatuses'])->name('donation-statuses');
});

Route::middleware(['auth:sanctum', 'mobile-access-token'])->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });

    Route::prefix('me')->name('me.')->group(function (): void {
        Route::get('/', [MeController::class, 'profile'])->name('profile');
        Route::get('posts', [UserPostController::class, 'index'])->name('posts.index');
        Route::get('posts/{post}', [UserPostController::class, 'show'])->name('posts.show');
        Route::get('saved-posts', [SavedPostController::class, 'index'])->name('saved-posts.index');
        Route::get('following', [FollowController::class, 'following'])->name('following.index');
        Route::get('donations', [DonationController::class, 'index'])->name('donations.index');
        Route::get('donations/{donation}', [DonationController::class, 'show'])->name('donations.show');
        Route::get('help-offers', [HelpOfferController::class, 'index'])->name('help-offers.index');
        Route::get('help-offers/{offer}', [HelpOfferController::class, 'show'])->name('help-offers.show');
        Route::get('applications', [CampaignApplicationController::class, 'index'])->name('applications.index');
        Route::get('applications/{application}', [CampaignApplicationController::class, 'show'])->name('applications.show');
        Route::delete('applications/{application}', [CampaignApplicationController::class, 'destroy'])->name('applications.destroy');
        Route::put('devices', [MobileDeviceController::class, 'store'])->name('devices.store');
        Route::delete('devices/{device}', [MobileDeviceController::class, 'destroy'])->name('devices.destroy');

        Route::prefix('notifications')->name('notifications.')->group(function (): void {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
            Route::patch('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
            Route::get('{notification}', [NotificationController::class, 'show'])->name('show');
            Route::patch('{notification}/read', [NotificationController::class, 'markRead'])->name('read');
            Route::patch('{notification}/unread', [NotificationController::class, 'markUnread'])->name('unread');
        });

        Route::patch('profile', [MeController::class, 'updateProfile'])->name('profile.update');
        Route::post('avatar', [UserAvatarController::class, 'store'])->name('avatar.store');
        Route::delete('avatar', [UserAvatarController::class, 'destroy'])->name('avatar.destroy');
        Route::patch('change-password', [MeController::class, 'changePassword'])->name('change-password');
        Route::get('permissions', [MeController::class, 'permissions'])->name('permissions');
    });

    Route::get('discovery/following', [FollowController::class, 'feed'])->name('discovery.following');
    Route::put('publishers/{targetType}/{targetId}/follow', [FollowController::class, 'follow'])->name('publishers.follow');
    Route::delete('publishers/{targetType}/{targetId}/follow', [FollowController::class, 'unfollow'])->name('publishers.unfollow');

    Route::post('campaigns/{campaign}/applications', [CampaignApplicationController::class, 'store'])->name('campaigns.applications.store');
    Route::post('campaigns/{campaign}/donations', [DonationController::class, 'store'])->name('campaigns.donations.store');

    Route::prefix('help-offers')->name('help-offers.')->group(function (): void {
        Route::patch('{offer}/accept', [HelpOfferController::class, 'accept'])->name('accept');
        Route::patch('{offer}/reject', [HelpOfferController::class, 'reject'])->name('reject');
        Route::patch('{offer}/contact', [HelpOfferController::class, 'contact'])->name('contact');
        Route::patch('{offer}/agree', [HelpOfferController::class, 'agree'])->name('agree');
        Route::patch('{offer}/cancel', [HelpOfferController::class, 'cancel'])->name('cancel');
        Route::patch('{offer}/confirm-provided', [HelpOfferController::class, 'confirmProvided'])->name('confirm-provided');
        Route::patch('{offer}/confirm-received', [HelpOfferController::class, 'confirmReceived'])->name('confirm-received');
    });

    Route::prefix('media')->name('media.')->group(function (): void {
        Route::post('{media}/like', [MediaEngagementController::class, 'like'])->name('like');
        Route::delete('{media}/like', [MediaEngagementController::class, 'unlike'])->name('unlike');
        Route::post('{media}/save', [MediaEngagementController::class, 'save'])->name('save');
        Route::delete('{media}/save', [MediaEngagementController::class, 'unsave'])->name('unsave');
        Route::post('{media}/reports', [MediaEngagementController::class, 'report'])->name('reports.store');
    });

    Route::prefix('posts')->name('posts.')->group(function (): void {
        Route::post('/', [UserPostController::class, 'store'])->name('store');
        Route::patch('{post}', [UserPostController::class, 'update'])->name('update');
        Route::post('{post}/help-offers', [HelpOfferController::class, 'store'])->name('help-offers.store');
        Route::patch('{post}/help-status', [HelpOfferController::class, 'updatePostStatus'])->name('help-status.update');

        Route::post('{post}/images', [PostImageController::class, 'store'])->name('images.store');
        Route::patch('{post}/images/order', [PostImageController::class, 'reorder'])->name('images.reorder');
        Route::delete('{post}/images/{image}', [PostImageController::class, 'destroy'])->name('images.destroy');
        Route::post('{post}/like', [PostEngagementController::class, 'like'])->name('like');
        Route::delete('{post}/like', [PostEngagementController::class, 'unlike'])->name('unlike');
        Route::post('{post}/save', [PostEngagementController::class, 'save'])->name('save');
        Route::delete('{post}/save', [PostEngagementController::class, 'unsave'])->name('unsave');
        Route::post('{post}/reports', [PostReportController::class, 'store'])->name('reports.store');
        Route::post('{post}/submit', [UserPostController::class, 'submit'])->name('submit');
        Route::delete('{post}', [UserPostController::class, 'destroy'])->name('destroy');
    });
});
