<?php

use App\Modules\Metrics\Http\Controllers\MetricsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('metrics', MetricsController::class);
});
