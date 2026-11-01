<?php

use App\Modules\Planner\Http\Controllers\TimeBlockController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('time-blocks', [TimeBlockController::class, 'index']);
    Route::post('time-blocks', [TimeBlockController::class, 'store']);
    Route::patch('time-blocks/{block}', [TimeBlockController::class, 'update'])->whereNumber('block');
    Route::delete('time-blocks/{block}', [TimeBlockController::class, 'destroy'])->whereNumber('block');
});
