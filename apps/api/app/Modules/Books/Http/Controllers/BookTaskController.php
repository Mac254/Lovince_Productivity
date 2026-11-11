<?php

namespace App\Modules\Books\Http\Controllers;

use App\Modules\Books\Http\Requests\BookTaskRequest;
use App\Modules\Books\Models\Book;
use App\Modules\Tasks\Http\Resources\TaskResource;
use App\Modules\Tasks\Models\Task;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The tasks that get a book read: a new reading task, or one of the user's tasks linked to it.
 * A new task takes the book's goal, so reading it counts towards that goal's progress.
 */
class BookTaskController extends Controller
{
    public function store(BookTaskRequest $request, Book $book): JsonResponse
    {
        if ($request->filled('task_id')) {
            $task = Task::query()->whereBelongsTo($request->user())->findOrFail($request->integer('task_id'));
        } else {
            $task = new Task([
                'title' => $request->input('title'),
                'due_date' => $request->input('due_date'),
                'recurrence' => $request->input('recurrence'),
                'goal_id' => $book->goal_id,
            ]);
            $task->user()->associate($request->user());
        }

        $task->forceFill(['book_id' => $book->id])->save();

        return TaskResource::make($task->load(Task::RELATIONS)->loadSum('notes', 'minutes')->loadChecklistCounts())
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Book $book, Task $task): Response
    {
        Gate::authorize('update', $book);
        Gate::authorize('update', $task);

        if ($task->getAttribute('book_id') === $book->id) {
            $task->forceFill(['book_id' => null])->save();
        }

        return response()->noContent();
    }
}
