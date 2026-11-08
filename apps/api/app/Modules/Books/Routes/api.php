<?php

use App\Modules\Books\Http\Controllers\BookController;
use App\Modules\Books\Http\Controllers\BookReadingController;
use App\Modules\Books\Http\Controllers\BookTaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::apiResource('books', BookController::class);
    Route::post('books/{book}/readings', [BookReadingController::class, 'store']);
    Route::patch('book-readings/{reading}', [BookReadingController::class, 'update']);
    Route::delete('book-readings/{reading}', [BookReadingController::class, 'destroy']);
    Route::post('books/{book}/tasks', [BookTaskController::class, 'store']);
    Route::delete('books/{book}/tasks/{task}', [BookTaskController::class, 'destroy']);
});
