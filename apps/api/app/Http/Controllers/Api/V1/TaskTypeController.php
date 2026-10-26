<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskType\TaskTypeRequest;
use App\Http\Resources\TaskTypeResource;
use App\Models\TaskType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The user's own task types. Deleting a type keeps its tasks and clears their type.
 */
class TaskTypeController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return TaskTypeResource::collection(
            TaskType::query()->whereBelongsTo($request->user())->withCount('tasks')->orderBy('name')->get()
        );
    }

    public function store(TaskTypeRequest $request): JsonResponse
    {
        $type = $request->user()->taskTypes()->create($request->validated());

        return TaskTypeResource::make($type->loadCount('tasks'))->response()->setStatusCode(201);
    }

    public function update(TaskTypeRequest $request, TaskType $taskType): TaskTypeResource
    {
        $taskType->update($request->validated());

        return TaskTypeResource::make($taskType->loadCount('tasks'));
    }

    public function destroy(TaskType $taskType): Response
    {
        Gate::authorize('delete', $taskType);

        $taskType->delete();

        return response()->noContent();
    }
}
