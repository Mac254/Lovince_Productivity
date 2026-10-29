<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\IndexTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Support\Contract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    public function index(IndexTaskRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $today = today();

        $query = Task::query()
            ->whereBelongsTo($request->user())
            ->with(Task::RELATIONS)
            ->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(
                fn (Builder $q) => $q
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
            ))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $status === 'open'
                ? $q->open()
                : $q->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $q, string $priority) => $q->where('priority', $priority))
            ->when($filters['category'] ?? null, fn (Builder $q, string $category) => $q->where('category', $category))
            ->when($filters['goal_id'] ?? null, fn (Builder $q, int|string $goalId) => $q->where('goal_id', $goalId))
            ->when($filters['task_type_id'] ?? null, fn (Builder $q, int|string $typeId) => $q->where('task_type_id', $typeId))
            ->when($request->boolean('highlighted'), fn (Builder $q) => $q->where('is_highlighted', true))
            ->when($filters['due'] ?? null, fn (Builder $q, string $due) => match ($due) {
                'today' => $q->whereDate('due_date', $today),
                'overdue' => $q->open()->whereDate('due_date', '<', $today),
                'week' => $q->whereBetween('due_date', [$today->copy()->startOfWeek()->toDateString(), $today->copy()->endOfWeek()->toDateString()]),
                'upcoming' => $q->whereDate('due_date', '>', $today),
                'none' => $q->whereNull('due_date'),
            });

        $this->applySort($query, $filters['sort'] ?? null, $filters['direction'] ?? null);

        return TaskResource::collection(
            $query->paginate($filters['per_page'] ?? Contract::PER_PAGE_DEFAULT)->withQueryString()
        );
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = $request->user()->tasks()->create($request->validated());

        return TaskResource::make($task->load(Task::RELATIONS))->response()->setStatusCode(201);
    }

    public function show(Task $task): TaskResource
    {
        Gate::authorize('view', $task);

        return TaskResource::make($task->load(Task::RELATIONS));
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $task->update($request->validated());

        return TaskResource::make($task->load(Task::RELATIONS));
    }

    public function destroy(Task $task): Response
    {
        Gate::authorize('delete', $task);

        // The task, its work log and the reports that listed it change together or not at all.
        DB::transaction(fn () => $task->delete());

        return response()->noContent();
    }

    /**
     * Default ordering surfaces open work first, then the nearest deadlines.
     *
     * @param  Builder<Task>  $query
     */
    private function applySort(Builder $query, ?string $sort, ?string $direction): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        if ($sort === 'priority') {
            $query->orderByPriority();
        } elseif ($sort !== null) {
            $query->orderBy($sort, $direction);
        } else {
            $closed = collect([TaskStatus::Completed, TaskStatus::Cancelled])
                ->map(fn (TaskStatus $s) => "'{$s->value}'")
                ->implode(',');
            $query->orderByRaw("CASE WHEN status IN ({$closed}) THEN 1 ELSE 0 END")
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('due_date');
        }

        $query->orderByDesc('id');
    }
}
