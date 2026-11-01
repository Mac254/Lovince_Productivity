<?php

namespace App\Modules\Clock\Http\Controllers;

use App\Modules\Clock\Actions\RecordFocusSession;
use App\Modules\Clock\Http\Requests\StoreFocusSessionRequest;
use App\Modules\Clock\Http\Resources\FocusSessionResource;
use App\Modules\Clock\Models\FocusSession;
use App\Modules\Clock\Services\FocusStats;
use App\Modules\Tasks\Models\TaskNote;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FocusSessionController extends Controller
{
    private const WEEK = 7;

    public function index(Request $request, FocusStats $stats): JsonResponse
    {
        $validated = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);
        $date = Carbon::createFromFormat('!Y-m-d', $validated['date'] ?? today()->toDateString());
        $from = $date->copy()->subDays(self::WEEK - 1);

        $week = $stats->between($request->user(), $from, $date)->load('task:id,title')->sortByDesc('started_at')->values();

        $byDay = $week->groupBy(fn (FocusSession $session) => $session->started_on->toDateString());
        $sessions = $byDay->get($date->toDateString(), collect());

        $days = collect(range(self::WEEK - 1, 0))->map(function (int $back) use ($date, $byDay) {
            $day = $date->copy()->subDays($back)->toDateString();
            $items = $byDay->get($day, collect());

            return ['date' => $day, 'minutes' => FocusStats::minutes($items), 'count' => $items->count()];
        });

        return response()->json([
            'data' => FocusSessionResource::collection($sessions)->resolve(),
            'meta' => [
                'date' => $date->toDateString(),
                'minutes' => FocusStats::minutes($sessions),
                'count' => $sessions->count(),
                'completed' => $sessions->where('completed', true)->count(),
                'goal_minutes' => $stats->goalMinutes($request->user()),
                'streak' => $stats->streak($request->user()),
                'week' => $days->values(),
            ],
        ]);
    }

    public function store(StoreFocusSessionRequest $request, RecordFocusSession $record): JsonResponse
    {
        $session = $record->handle($request->user(), $request->validated());

        return FocusSessionResource::make($session)->response()->setStatusCode(201);
    }

    public function destroy(FocusSession $session): Response
    {
        Gate::authorize('delete', $session);

        DB::transaction(function () use ($session) {
            $session->delete();
            TaskNote::query()->whereKey($session->task_note_id)->first()?->delete();
        });

        return response()->noContent();
    }
}
