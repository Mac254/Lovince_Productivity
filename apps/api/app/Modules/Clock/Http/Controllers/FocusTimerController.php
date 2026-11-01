<?php

namespace App\Modules\Clock\Http\Controllers;

use App\Modules\Clock\Http\Requests\FocusTimerRequest;
use App\Modules\Clock\Models\FocusTimer;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FocusTimerController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->respond(FocusTimer::query()->whereBelongsTo($request->user())->first());
    }

    public function update(FocusTimerRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $timer = DB::transaction(function () use ($user, $validated) {
            $timer = FocusTimer::query()->whereBelongsTo($user)->lockForUpdate()->first()
                ?? $user->focusTimer()->make(['revision' => 0]);

            if ($timer->exists && $validated['revision'] < $timer->revision) {
                return $timer;
            }

            $timer->fill(['state' => $validated['state'], 'revision' => $timer->revision + 1])->save();

            return $timer;
        });

        return $this->respond($timer, $timer->wasChanged() || $timer->wasRecentlyCreated ? 200 : 409);
    }

    private function respond(?FocusTimer $timer, int $status = 200): JsonResponse
    {
        return response()->json(['data' => [
            'state' => $timer?->state,
            'revision' => $timer?->revision ?? 0,
            'server_time' => now()->getTimestampMs(),
        ]], $status);
    }
}
