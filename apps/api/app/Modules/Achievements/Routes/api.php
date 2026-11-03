<?php

use App\Modules\Achievements\Http\Controllers\AchievementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::apiResource('achievements', AchievementController::class)->except('show');
});
