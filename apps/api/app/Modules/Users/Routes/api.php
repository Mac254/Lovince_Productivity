<?php

use App\Modules\Users\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::put('profile/password', [ProfileController::class, 'password'])->middleware('throttle:6,1');
    Route::get('profile/avatar', [ProfileController::class, 'showAvatar']);
    Route::post('profile/avatar', [ProfileController::class, 'storeAvatar'])->middleware('throttle:10,1');
    Route::delete('profile/avatar', [ProfileController::class, 'destroyAvatar']);
});
