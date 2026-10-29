<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BlockerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\GoalController;
use App\Http\Controllers\Api\V1\JournalController;
use App\Http\Controllers\Api\V1\MetricsController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskNoteController;
use App\Http\Controllers\Api\V1\TaskTypeController;
use App\Http\Controllers\Api\V1\WallpaperController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('dashboard', DashboardController::class);
        Route::apiResource('task-types', TaskTypeController::class)->except('show');
        Route::apiResource('tasks', TaskController::class);
        Route::get('tasks/{task}/notes', [TaskNoteController::class, 'index'])->whereNumber('task');
        Route::post('tasks/{task}/notes', [TaskNoteController::class, 'store'])->whereNumber('task')->middleware('throttle:60,1');
        Route::patch('task-notes/{note}', [TaskNoteController::class, 'update'])->whereNumber('note');
        Route::delete('task-notes/{note}', [TaskNoteController::class, 'destroy'])->whereNumber('note');
        Route::apiResource('goals', GoalController::class);
        Route::get('quotes/random', [QuoteController::class, 'random']);

        Route::apiResource('blockers', BlockerController::class)->except('show');

        Route::get('journal', [JournalController::class, 'index']);
        Route::get('journal/{date}/context', [JournalController::class, 'context']);
        Route::get('journal/{date}', [JournalController::class, 'show']);
        Route::put('journal/{date}', [JournalController::class, 'update']);
        Route::delete('journal/{date}', [JournalController::class, 'destroy']);

        Route::get('metrics', MetricsController::class);

        Route::patch('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'password'])->middleware('throttle:6,1');
        Route::get('profile/avatar', [ProfileController::class, 'showAvatar']);
        Route::post('profile/avatar', [ProfileController::class, 'storeAvatar'])->middleware('throttle:10,1');
        Route::delete('profile/avatar', [ProfileController::class, 'destroyAvatar']);

        Route::get('settings', [SettingsController::class, 'show']);
        Route::patch('settings', [SettingsController::class, 'update'])->middleware('throttle:60,1');
        Route::delete('settings', [SettingsController::class, 'destroy']);

        Route::get('wallpapers', [WallpaperController::class, 'index']);
        Route::post('wallpapers', [WallpaperController::class, 'store'])->middleware('throttle:20,1');
        Route::get('wallpapers/{wallpaper}/image', [WallpaperController::class, 'image'])->whereNumber('wallpaper');
        Route::delete('wallpapers/{wallpaper}', [WallpaperController::class, 'destroy'])->whereNumber('wallpaper');

        Route::get('reports', [ReportController::class, 'index']);
        Route::post('reports', [ReportController::class, 'store'])->middleware('throttle:20,1');
        Route::get('reports/{date}', [ReportController::class, 'show'])->where('date', '\\d{4}-\\d{2}-\\d{2}');
    });
});
