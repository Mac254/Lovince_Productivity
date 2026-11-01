<?php

use App\Modules\Tasks\Http\Controllers\ChecklistController;
use App\Modules\Tasks\Http\Controllers\TaskController;
use App\Modules\Tasks\Http\Controllers\TaskCoverController;
use App\Modules\Tasks\Http\Controllers\TaskNoteController;
use App\Modules\Tasks\Http\Controllers\TaskTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::apiResource('task-types', TaskTypeController::class)->except('show');
    Route::apiResource('tasks', TaskController::class);
    Route::get('tasks/{task}/cover', [TaskCoverController::class, 'show'])->whereNumber('task');
    Route::post('tasks/{task}/cover', [TaskCoverController::class, 'store'])->whereNumber('task')->middleware('throttle:20,1');
    Route::delete('tasks/{task}/cover', [TaskCoverController::class, 'destroy'])->whereNumber('task');
    Route::get('tasks/{task}/notes', [TaskNoteController::class, 'index'])->whereNumber('task');
    Route::post('tasks/{task}/notes', [TaskNoteController::class, 'store'])->whereNumber('task')->middleware('throttle:60,1');
    Route::patch('task-notes/{note}', [TaskNoteController::class, 'update'])->whereNumber('note');
    Route::delete('task-notes/{note}', [TaskNoteController::class, 'destroy'])->whereNumber('note');
    Route::get('tasks/{task}/checklist', [ChecklistController::class, 'index'])->whereNumber('task');
    Route::post('tasks/{task}/checklist', [ChecklistController::class, 'store'])->whereNumber('task');
    Route::put('tasks/{task}/checklist/order', [ChecklistController::class, 'reorder'])->whereNumber('task');
    Route::patch('checklist-items/{item}', [ChecklistController::class, 'update'])->whereNumber('item');
    Route::delete('checklist-items/{item}', [ChecklistController::class, 'destroy'])->whereNumber('item');
});
