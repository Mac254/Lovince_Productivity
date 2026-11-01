<?php

use App\Modules\Quotes\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('quotes/random', [QuoteController::class, 'random']);
});
