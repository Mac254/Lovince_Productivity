<?php

use App\Modules\Planner\Http\Controllers\ScheduleController;
use App\Modules\Planner\Http\Controllers\TimeBlockController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('time-blocks', [TimeBlockController::class, 'index']);
    Route::post('time-blocks', [TimeBlockController::class, 'store']);
    Route::patch('time-blocks/{block}', [TimeBlockController::class, 'update'])->whereNumber('block');
    Route::delete('time-blocks/{block}', [TimeBlockController::class, 'destroy'])->whereNumber('block');

    Route::get('schedules', [ScheduleController::class, 'index']);
    Route::post('schedules', [ScheduleController::class, 'store']);
    Route::get('schedules/{schedule}', [ScheduleController::class, 'show'])->whereNumber('schedule');
    Route::patch('schedules/{schedule}', [ScheduleController::class, 'update'])->whereNumber('schedule');
    Route::delete('schedules/{schedule}', [ScheduleController::class, 'destroy'])->whereNumber('schedule');
    Route::post('schedules/{schedule}/apply', [ScheduleController::class, 'apply'])->whereNumber('schedule')->middleware('throttle:30,1,schedule-apply');
});
