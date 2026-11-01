<?php

namespace App\Modules\Clock\Http\Controllers;

use App\Modules\Clock\Actions\RecordFocusSession;
use App\Modules\Clock\Http\Requests\StoreFocusSessionRequest;
use App\Modules\Clock\Http\Resources\FocusSessionResource;
use App\Modules\Clock\Models\FocusSession;
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

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);
        $date = Carbon::createFromFormat('!Y-m-d', $validated['date'] ?? today()->toDateString());
        $from = $date->copy()->subDays(self::WEEK - 1);

        $week = FocusSession::query()
            ->whereBelongsTo($request->user())
            ->whereDate('started_on', '>=', $from->toDateString())
            ->whereDate('started_on', '<=', $date->toDateString())
            ->with('task:id,title')
            ->orderByDesc('started_at')
            ->get();

        $byDay = $week->groupBy(fn (FocusSession $session) => $session->started_on->toDateString());
        $sessions = $byDay->get($date->toDateString(), collect());

        $days = collect(range(self::WEEK - 1, 0))->map(function (int $back) use ($date, $byDay) {
            $day = $date->copy()->subDays($back)->toDateString();
            $items = $byDay->get($day, collect());

            return ['date' => $day, 'minutes' => $this->minutes($items), 'count' => $items->count()];
        });

        return response()->json([
            'data' => FocusSessionResource::collection($sessions)->resolve(),
            'meta' => [
                'date' => $date->toDateString(),
                'minutes' => $this->minutes($sessions),
                'count' => $sessions->count(),
                'completed' => $sessions->where('completed', true)->count(),
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

    private function minutes($sessions): int
    {
        return (int) round($sessions->sum('focused_seconds') / 60);
    }
}
