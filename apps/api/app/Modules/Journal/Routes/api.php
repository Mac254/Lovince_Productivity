<?php

use App\Modules\Journal\Http\Controllers\JournalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('journal', [JournalController::class, 'index']);
    Route::get('journal/{date}/context', [JournalController::class, 'context']);
    Route::get('journal/{date}', [JournalController::class, 'show']);
    Route::put('journal/{date}', [JournalController::class, 'update']);
    Route::delete('journal/{date}', [JournalController::class, 'destroy']);
});
