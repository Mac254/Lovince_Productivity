<?php

namespace App\Modules\Planner\Http\Controllers;

use App\Modules\Planner\Http\Requests\TimeBlockRequest;
use App\Modules\Planner\Http\Resources\TimeBlockResource;
use App\Modules\Planner\Models\TimeBlock;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TimeBlockController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $date = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']])['date'] ?? today()->toDateString();

        return TimeBlockResource::collection(
            TimeBlock::query()
                ->whereBelongsTo($request->user())
                ->whereDate('date', $date)
                ->with('task:id,title,status,priority')
                ->orderBy('start_minute')
                ->get()
        );
    }

    public function store(TimeBlockRequest $request): JsonResponse
    {
        $block = $request->user()->timeBlocks()->create($request->blockData());

        return TimeBlockResource::make($block->load('task:id,title,status,priority'))->response()->setStatusCode(201);
    }

    public function update(TimeBlockRequest $request, TimeBlock $block): TimeBlockResource
    {
        $block->update($request->blockData());

        return TimeBlockResource::make($block->load('task:id,title,status,priority'));
    }

    public function destroy(TimeBlock $block): Response
    {
        Gate::authorize('delete', $block);
        $block->delete();

        return response()->noContent();
    }
}
