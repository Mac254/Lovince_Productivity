<?php

use App\Modules\Backup\Http\Controllers\BackupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('backup', [BackupController::class, 'export'])->middleware('throttle:10,1');
    Route::post('backup', [BackupController::class, 'import'])->middleware('throttle:10,1');
});
