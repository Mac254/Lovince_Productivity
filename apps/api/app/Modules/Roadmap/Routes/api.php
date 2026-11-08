<?php

use App\Modules\Roadmap\Http\Controllers\RoadmapItemController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('roadmap-items/reorder', [RoadmapItemController::class, 'reorder']);
    Route::apiResource('roadmap-items', RoadmapItemController::class)->parameters(['roadmap-items' => 'item']);
});
