<?php

use App\Modules\Reviews\Http\Controllers\WeeklyReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('reviews', [WeeklyReviewController::class, 'index']);
    Route::get('reviews/{week}', [WeeklyReviewController::class, 'show']);
    Route::put('reviews/{week}', [WeeklyReviewController::class, 'update']);
});
