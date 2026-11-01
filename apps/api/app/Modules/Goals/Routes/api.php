<?php

use App\Modules\Goals\Http\Controllers\GoalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::apiResource('goals', GoalController::class);
});
