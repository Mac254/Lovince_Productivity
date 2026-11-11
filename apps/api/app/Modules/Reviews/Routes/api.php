<?php

use App\Modules\Reviews\Http\Controllers\WeeklyReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('reviews', [WeeklyReviewController::class, 'index']);
    Route::get('reviews/{week}', [WeeklyReviewController::class, 'show'])->where('week', '\\d{4}-\\d{2}-\\d{2}');
    Route::put('reviews/{week}', [WeeklyReviewController::class, 'update'])->where('week', '\\d{4}-\\d{2}-\\d{2}');
});
