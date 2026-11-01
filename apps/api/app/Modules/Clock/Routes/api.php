<?php

use App\Modules\Clock\Http\Controllers\FocusSessionController;
use App\Modules\Clock\Http\Controllers\WallpaperController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('wallpapers', [WallpaperController::class, 'index']);
    Route::post('wallpapers', [WallpaperController::class, 'store'])->middleware('throttle:20,1');
    Route::get('wallpapers/{wallpaper}/image', [WallpaperController::class, 'image'])->whereNumber('wallpaper');
    Route::delete('wallpapers/{wallpaper}', [WallpaperController::class, 'destroy'])->whereNumber('wallpaper');

    Route::get('focus-sessions', [FocusSessionController::class, 'index']);
    Route::post('focus-sessions', [FocusSessionController::class, 'store']);
    Route::delete('focus-sessions/{session}', [FocusSessionController::class, 'destroy'])->whereNumber('session');
});
