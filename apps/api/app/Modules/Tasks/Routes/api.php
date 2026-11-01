<?php

use App\Modules\Tasks\Http\Controllers\TaskController;
use App\Modules\Tasks\Http\Controllers\TaskNoteController;
use App\Modules\Tasks\Http\Controllers\TaskTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::apiResource('task-types', TaskTypeController::class)->except('show');
    Route::apiResource('tasks', TaskController::class);
    Route::get('tasks/{task}/notes', [TaskNoteController::class, 'index'])->whereNumber('task');
    Route::post('tasks/{task}/notes', [TaskNoteController::class, 'store'])->whereNumber('task')->middleware('throttle:60,1');
    Route::patch('task-notes/{note}', [TaskNoteController::class, 'update'])->whereNumber('note');
    Route::delete('task-notes/{note}', [TaskNoteController::class, 'destroy'])->whereNumber('note');
});
