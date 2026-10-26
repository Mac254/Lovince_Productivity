<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BlockerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Blocker\BlockerRequest;
use App\Http\Resources\BlockerResource;
use App\Models\Blocker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BlockerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::enum(BlockerStatus::class)],
            'task_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $blockers = Blocker::query()
            ->whereBelongsTo($request->user())
            ->with('task:id,title')
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['task_id'] ?? null, fn ($q, int|string $taskId) => $q->where('task_id', $taskId))
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->latest()
            ->limit(200)
            ->get();

        return BlockerResource::collection($blockers);
    }

    public function store(BlockerRequest $request): JsonResponse
    {
        $blocker = $request->user()->blockers()->create($request->validated());

        return BlockerResource::make($blocker->load('task:id,title'))->response()->setStatusCode(201);
    }

    public function update(BlockerRequest $request, Blocker $blocker): BlockerResource
    {
        $blocker->update($request->validated());

        return BlockerResource::make($blocker->load('task:id,title'));
    }

    public function destroy(Blocker $blocker): Response
    {
        Gate::authorize('delete', $blocker);

        $blocker->delete();

        return response()->noContent();
    }
}
