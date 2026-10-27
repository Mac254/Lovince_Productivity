<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskNote\TaskNoteRequest;
use App\Http\Resources\TaskNoteResource;
use App\Models\Task;
use App\Models\TaskNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * A task's work log. Listed newest day first; within a day, latest entry first.
 */
class TaskNoteController extends Controller
{
    public function index(Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        return TaskNoteResource::collection(
            $task->notes()->orderByDesc('logged_on')->orderByDesc('id')->get()
        );
    }

    public function store(TaskNoteRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('update', $task);

        $note = $task->notes()->make($request->validated());
        $note->logged_on ??= today();
        $note->user()->associate($request->user());
        $note->save();

        return TaskNoteResource::make($note)->response()->setStatusCode(201);
    }

    public function update(TaskNoteRequest $request, TaskNote $note): TaskNoteResource
    {
        $note->update($request->validated());

        return TaskNoteResource::make($note);
    }

    public function destroy(TaskNote $note): Response
    {
        Gate::authorize('delete', $note);

        $note->delete();

        return response()->noContent();
    }
}
