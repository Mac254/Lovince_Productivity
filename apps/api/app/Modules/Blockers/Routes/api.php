<?php

use App\Modules\Blockers\Http\Controllers\BlockerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::apiResource('blockers', BlockerController::class)->except('show');
});
