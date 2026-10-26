<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DeleteGoal;
use App\Enums\GoalStatus;
use App\Enums\GoalTimeframe;
use App\Http\Controllers\Controller;
use App\Http\Requests\Goal\StoreGoalRequest;
use App\Http\Requests\Goal\UpdateGoalRequest;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GoalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::enum(GoalStatus::class)],
            'timeframe' => ['sometimes', 'nullable', Rule::enum(GoalTimeframe::class)],
        ]);

        $goals = Goal::query()
            ->whereBelongsTo($request->user())
            ->withProgress()
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['timeframe'] ?? null, fn ($q, string $timeframe) => $q->where('timeframe', $timeframe))
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByRaw('CASE WHEN target_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('target_date')
            ->orderByDesc('id')
            ->get();

        return GoalResource::collection($goals);
    }

    public function store(StoreGoalRequest $request): JsonResponse
    {
        $goal = $request->user()->goals()->create($request->validated());

        return GoalResource::make($this->fresh($goal))->response()->setStatusCode(201);
    }

    public function show(Goal $goal): GoalResource
    {
        Gate::authorize('view', $goal);

        return GoalResource::make($this->fresh($goal));
    }

    public function update(UpdateGoalRequest $request, Goal $goal): GoalResource
    {
        $goal->update($request->validated());

        return GoalResource::make($this->fresh($goal));
    }

    public function destroy(Goal $goal, DeleteGoal $deleteGoal): Response
    {
        Gate::authorize('delete', $goal);

        $deleteGoal->handle($goal);

        return response()->noContent();
    }

    private function fresh(Goal $goal): Goal
    {
        return Goal::query()->withProgress()->findOrFail($goal->id);
    }
}
