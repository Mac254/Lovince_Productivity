<?php

use App\Modules\Suggestions\Http\Controllers\SuggestionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('suggestions', [SuggestionController::class, 'index']);
    Route::post('suggestions', [SuggestionController::class, 'store']);
    Route::post('suggestions/refresh', [SuggestionController::class, 'refresh'])->middleware('throttle:30,1');
    Route::patch('suggestions/{suggestion}', [SuggestionController::class, 'update'])->whereNumber('suggestion');
    Route::post('suggestions/{suggestion}/accept', [SuggestionController::class, 'accept'])->whereNumber('suggestion');
    Route::delete('suggestions/{suggestion}', [SuggestionController::class, 'destroy'])->whereNumber('suggestion');
});
