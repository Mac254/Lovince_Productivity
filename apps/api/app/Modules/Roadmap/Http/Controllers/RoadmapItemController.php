<?php

namespace App\Modules\Roadmap\Http\Controllers;

use App\Modules\Accounting\Services\GoalProgress;
use App\Modules\Roadmap\Http\Requests\ReorderRoadmapRequest;
use App\Modules\Roadmap\Http\Requests\RoadmapItemRequest;
use App\Modules\Roadmap\Http\Resources\RoadmapItemResource;
use App\Modules\Roadmap\Models\RoadmapItem;
use App\Modules\Roadmap\Services\PhaseMilestones;
use App\Modules\Users\Models\User;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RoadmapItemController extends Controller
{
    public function __construct(private readonly PhaseMilestones $milestones, private readonly GoalProgress $progress) {}

    /**
     * @param  Collection<int, RoadmapItem>  $items
     * @return Collection<int, RoadmapItem>
     */
    private function withProgress(Collection $items, User $user): Collection
    {
        $items->loadMissing(['goal:id,title,tone', 'moneyGoal']);
        $this->progress->measure($items->pluck('moneyGoal')->filter()->values());

        return $this->milestones->attach($items, $user);
    }

    private function detailed(RoadmapItem $item): RoadmapItem
    {
        return $this->withProgress(new Collection([$item]), $item->user)->first();
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $items = RoadmapItem::query()->whereBelongsTo($request->user())
            ->orderBy('position')
            ->orderBy('id')
            ->limit(500)
            ->get();

        return RoadmapItemResource::collection($this->withProgress($items, $request->user()));
    }

    public function store(RoadmapItemRequest $request): JsonResponse
    {
        $item = $request->user()->roadmapItems()->create($request->validated());

        return RoadmapItemResource::make($this->detailed($item))->response()->setStatusCode(201);
    }

    public function show(RoadmapItem $item): RoadmapItemResource
    {
        Gate::authorize('view', $item);

        return RoadmapItemResource::make($this->detailed($item));
    }

    public function update(RoadmapItemRequest $request, RoadmapItem $item): RoadmapItemResource
    {
        $item->update($request->validated());

        return RoadmapItemResource::make($this->detailed($item));
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
