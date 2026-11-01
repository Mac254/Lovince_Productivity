<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Http\Requests\ChecklistItemRequest;
use App\Modules\Tasks\Http\Resources\ChecklistItemResource;
use App\Modules\Tasks\Models\ChecklistItem;
use App\Modules\Tasks\Models\Task;
use App\Shared\Http\Controllers\Controller;
use App\Support\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChecklistController extends Controller
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    public function index(Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        return ChecklistItemResource::collection($task->checklistItems()->get());
    }

    public function store(ChecklistItemRequest $request, Task $task): JsonResponse
    {
        if ($task->checklistItems()->count() >= Contract::CHECKLIST_LIMIT) {
            throw ValidationException::withMessages(['title' => 'A checklist holds at most '.Contract::CHECKLIST_LIMIT.' items.']);
        }

        $item = $task->checklistItems()->make($request->validated());
        $item->position = (int) $task->checklistItems()->max('position') + 1;
        $item->user()->associate($request->user());
        $item->save();

        return ChecklistItemResource::make($item)->response()->setStatusCode(201);
    }

    public function update(ChecklistItemRequest $request, ChecklistItem $item): ChecklistItemResource
    {
        $item->update($request->validated());

        if ($item->wasChanged('done') && $item->done) {
            $this->completeTaskWhenDone($request, $item->task);
        }

        return ChecklistItemResource::make($item);
    }

    public function reorder(Request $request, Task $task): AnonymousResourceCollection
    {
        Gate::authorize('update', $task);
        $ids = $request->validate([
            'ids' => ['required', 'array', 'max:'.Contract::CHECKLIST_LIMIT],
            'ids.*' => ['integer', 'distinct'],
        ])['ids'];

        $items = $task->checklistItems()->get()->keyBy('id');
        abort_unless(collect($ids)->sort()->values()->all() === $items->keys()->sort()->values()->all(), 422, 'Send every item on the checklist once.');

        DB::transaction(fn () => collect($ids)->each(fn (int $id, int $index) => $items[$id]->update(['position' => $index + 1])));

        return ChecklistItemResource::collection($task->checklistItems()->get());
    }

    public function destroy(ChecklistItem $item): Response
    {
        Gate::authorize('delete', $item);
        $item->delete();

        return response()->noContent();
    }

    private function completeTaskWhenDone(Request $request, Task $task): void
    {
        $enabled = $this->settings->resolve($request->user()->settings)['tasks']['complete_on_checklist'];
        $open = in_array($task->status, TaskStatus::open(), true);

        if ($enabled && $open && ! $task->checklistItems()->where('done', false)->exists()) {
            $task->update(['status' => TaskStatus::Completed]);
        }
    }
}
