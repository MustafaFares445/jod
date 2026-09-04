<?php

declare(strict_types=1);

use App\Http\Controllers\API\Org\BriefController;
use App\Http\Controllers\API\Org\HelpOfferController;
use App\Http\Controllers\API\Org\HelpRequestController;
use App\Http\Controllers\API\Org\RecommendationAnalyticsController;
use Illuminate\Support\Facades\Route;

Route::get('capabilities/brief', [BriefController::class, 'capabilities']);
Route::get('help-requests', [HelpRequestController::class, 'index']);
Route::get('help-requests/{post}', [HelpRequestController::class, 'show']);
Route::patch('help-requests/{post}/status', [HelpRequestController::class, 'updateStatus']);
Route::get('help-offers', [HelpOfferController::class, 'index']);
Route::get('help-offers/{offer}', [HelpOfferController::class, 'show']);
Route::post('help-offers/{offer}/accept', [HelpOfferController::class, 'accept']);
Route::post('help-offers/{offer}/reject', [HelpOfferController::class, 'reject']);
Route::post('help-offers/{offer}/contact', [HelpOfferController::class, 'contact']);
Route::post('help-offers/{offer}/agree', [HelpOfferController::class, 'agree']);
Route::post('help-offers/{offer}/confirm-received', [HelpOfferController::class, 'confirmReceived']);
Route::get('analytics/recommendations', [RecommendationAnalyticsController::class, 'recommendations']);
Route::get('analytics/content', [RecommendationAnalyticsController::class, 'content']);
