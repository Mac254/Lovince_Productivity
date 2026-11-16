<?php

use App\Modules\Reviews\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    $date = '\\d{4}-\\d{2}-\\d{2}';
    $period = 'week|month|year';

    Route::get('reviews', [ReviewController::class, 'index']);
    Route::get('reviews/{period}/{date}', [ReviewController::class, 'show'])->where(['period' => $period, 'date' => $date]);
    Route::put('reviews/{period}/{date}', [ReviewController::class, 'update'])->where(['period' => $period, 'date' => $date]);
    Route::get('reviews/{date}', [ReviewController::class, 'showWeek'])->where('date', $date);
    Route::put('reviews/{date}', [ReviewController::class, 'updateWeek'])->where('date', $date);
});
