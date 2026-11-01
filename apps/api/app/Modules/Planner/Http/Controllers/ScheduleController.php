<?php

namespace App\Modules\Planner\Http\Controllers;

use App\Modules\Planner\Actions\ApplySchedule;
use App\Modules\Planner\Http\Requests\ApplyScheduleRequest;
use App\Modules\Planner\Http\Requests\ScheduleRequest;
use App\Modules\Planner\Http\Resources\ScheduleResource;
use App\Modules\Planner\Http\Resources\TimeBlockResource;
use App\Modules\Planner\Models\Schedule;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ScheduleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ScheduleResource::collection(
            Schedule::query()->whereBelongsTo($request->user())->with('items')->orderBy('name')->get()
        );
    }

    public function store(ScheduleRequest $request): JsonResponse
    {
        $schedule = DB::transaction(function () use ($request) {
            $schedule = $request->user()->schedules()->create($request->safe()->only(['name', 'days']));
            $schedule->items()->createMany($request->validated('items'));

            return $schedule;
        });

        return ScheduleResource::make($schedule->load('items'))->response()->setStatusCode(201);
    }

    public function show(Schedule $schedule): ScheduleResource
    {
        Gate::authorize('view', $schedule);

        return ScheduleResource::make($schedule->load('items'));
    }

    public function update(ScheduleRequest $request, Schedule $schedule): ScheduleResource
    {
        DB::transaction(function () use ($request, $schedule) {
            $schedule->update($request->safe()->only(['name', 'days']));
            if ($request->has('items')) {
                $schedule->items()->delete();
                $schedule->items()->createMany($request->validated('items'));
            }
        });

        return ScheduleResource::make($schedule->load('items'));
    }

    public function destroy(Schedule $schedule): Response
    {
        Gate::authorize('delete', $schedule);
        $schedule->delete();

        return response()->noContent();
    }

    public function apply(ApplyScheduleRequest $request, Schedule $schedule, ApplySchedule $apply): AnonymousResourceCollection
    {
        $blocks = $apply($schedule, $request->validated('date'), $request->boolean('replace'));

        return TimeBlockResource::collection($blocks->each->load('task:id,title,status,priority'));
    }
}
