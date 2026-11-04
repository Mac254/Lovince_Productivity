<?php

namespace App\Modules\Achievements\Http\Controllers;

use App\Modules\Achievements\Http\Requests\AchievementRequest;
use App\Modules\Achievements\Http\Resources\AchievementResource;
use App\Modules\Achievements\Models\Achievement;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AchievementController extends Controller
{
    private const RELATIONS = ['goal:id,title,tone', 'task:id,title'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'goal_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'task_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'category' => ['sometimes', 'nullable', Rule::enum(SuggestionCategory::class)],
            'year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:9999'],
        ]);

        $achievements = Achievement::query()
            ->whereBelongsTo($request->user())
            ->with(self::RELATIONS)
            ->when($filters['goal_id'] ?? null, fn ($q, int|string $goalId) => $q->where('goal_id', $goalId))
            ->when($filters['task_id'] ?? null, fn ($q, int|string $taskId) => $q->where('task_id', $taskId))
            ->when($filters['category'] ?? null, fn ($q, string $category) => $q->where('category', $category))
            ->when($filters['year'] ?? null, fn ($q, int|string $year) => $q->whereYear('achieved_on', $year))
            ->orderByDesc('achieved_on')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return AchievementResource::collection($achievements);
    }

    public function store(AchievementRequest $request): JsonResponse
    {
        $achievement = $request->user()->achievements()->create($request->validated());

        return AchievementResource::make($achievement->load(self::RELATIONS))->response()->setStatusCode(201);
    }

    public function update(AchievementRequest $request, Achievement $achievement): AchievementResource
    {
        $achievement->update($request->validated());

        return AchievementResource::make($achievement->load(self::RELATIONS));
    }

    public function destroy(Achievement $achievement): Response
    {
        Gate::authorize('delete', $achievement);

        $achievement->delete();

        return response()->noContent();
    }
}
