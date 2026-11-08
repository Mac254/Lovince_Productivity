<?php

namespace App\Modules\Roadmap\Http\Controllers;

use App\Modules\Roadmap\Http\Requests\ReorderRoadmapRequest;
use App\Modules\Roadmap\Http\Requests\RoadmapItemRequest;
use App\Modules\Roadmap\Http\Resources\RoadmapItemResource;
use App\Modules\Roadmap\Models\RoadmapItem;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RoadmapItemController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = RoadmapItem::query()->whereBelongsTo($request->user())
            ->orderBy('position')
            ->orderBy('id')
            ->limit(500)
            ->get();

        return RoadmapItemResource::collection($items);
    }

    public function store(RoadmapItemRequest $request): JsonResponse
    {
        $item = $request->user()->roadmapItems()->create($request->validated());

        return RoadmapItemResource::make($item)->response()->setStatusCode(201);
    }

    public function show(RoadmapItem $item): RoadmapItemResource
    {
        Gate::authorize('view', $item);

        return RoadmapItemResource::make($item);
    }

    public function update(RoadmapItemRequest $request, RoadmapItem $item): RoadmapItemResource
    {
        $item->update($request->validated());

        return RoadmapItemResource::make($item);
    }

    public function destroy(RoadmapItem $item): Response
    {
        Gate::authorize('delete', $item);

        $item->delete();

        return response()->noContent();
    }

    /**
     * Puts the given items into a stage in the order sent.
     */
    public function reorder(ReorderRoadmapRequest $request): AnonymousResourceCollection
    {
        $stage = $request->validated('stage');
        $ids = $request->validated('ids');

        DB::transaction(function () use ($request, $stage, $ids) {
            $items = RoadmapItem::query()->whereBelongsTo($request->user())->whereKey($ids)->get()->keyBy('id');
            foreach ($ids as $position => $id) {
                $item = $items[$id];
                $item->fill(['stage' => $stage, 'position' => $position])->save();
            }
        });

        return $this->index($request);
    }
}
