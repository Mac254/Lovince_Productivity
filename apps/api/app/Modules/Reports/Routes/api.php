<?php

use App\Modules\Reports\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('reports', [ReportController::class, 'index']);
    Route::post('reports', [ReportController::class, 'store'])->middleware('throttle:20,1,reports');
    Route::get('reports/{date}', [ReportController::class, 'show'])->where('date', '\\d{4}-\\d{2}-\\d{2}');
});
