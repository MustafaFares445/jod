<?php

declare(strict_types=1);

use App\Http\Controllers\API\Admin\CapabilityController;
use App\Http\Controllers\API\Admin\CategoryKeywordController;
use App\Http\Controllers\API\Admin\HelpMatchingController;
use App\Http\Controllers\API\Admin\HelpRequestLifecycleController;
use App\Http\Controllers\API\Admin\RecommendationAnalyticsController;
use App\Http\Controllers\API\Admin\RecommendationConfigurationController;
use App\Http\Controllers\API\Admin\RecommendationInspectorController;
use App\Http\Controllers\API\Admin\UserPersonalizationController;
use Illuminate\Support\Facades\Route;

Route::apiResource('capabilities', CapabilityController::class);
Route::patch('capabilities/{capability}/status', [CapabilityController::class, 'updateStatus']);

Route::get('categories/{category}/keywords', [CategoryKeywordController::class, 'index']);
Route::put('categories/{category}/keywords', [CategoryKeywordController::class, 'update']);

Route::get('users/{user}/personalization', UserPersonalizationController::class);

Route::patch('posts/{post}/urgency', [HelpRequestLifecycleController::class, 'urgency']);
Route::patch('posts/{post}/expiration', [HelpRequestLifecycleController::class, 'expiration']);
Route::patch('posts/{post}/fulfillment', [HelpRequestLifecycleController::class, 'fulfillment']);

Route::get('recommendations/analytics', RecommendationAnalyticsController::class);
Route::get('recommendations/users/{user}/preview', RecommendationInspectorController::class);
Route::get('recommendations/config', [RecommendationConfigurationController::class, 'show']);
Route::patch('recommendations/config', [RecommendationConfigurationController::class, 'update']);
Route::delete('recommendations/config', [RecommendationConfigurationController::class, 'reset']);

Route::get('help-matching', [HelpMatchingController::class, 'index']);
Route::get('help-matching/{post}', [HelpMatchingController::class, 'show']);
