<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Http\Requests\GoalRequest;
use App\Modules\Accounting\Http\Resources\GoalResource;
use App\Modules\Accounting\Models\FinancialGoal;
use App\Modules\Accounting\Services\AuditTrail;
use App\Modules\Accounting\Services\GoalProgress;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GoalController extends Controller
{
    public function __construct(private GoalProgress $progress) {}

    /**
     * Open goals by target date, those without a date last; archived ones too with `?archived=1`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['archived' => ['sometimes', 'boolean']]);

        $goals = FinancialGoal::query()
            ->whereBelongsTo($request->user())
            ->when(! $request->boolean('archived'), fn ($q) => $q->whereNull('archived_at'))
            ->orderByRaw('CASE WHEN archived_at IS NULL THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN target_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('target_date')
            ->orderBy('name')
            ->get();

        return GoalResource::collection($this->progress->measure($goals));
    }

    public function store(GoalRequest $request): JsonResponse
    {
        $goal = DB::transaction(function () use ($request) {
            $goal = $request->user()->accountingGoals()->create($request->fields());
            AuditTrail::record($request->user(), 'goal.created', $goal, [
                'kind' => $goal->kind->value,
                'target_amount' => $goal->target_amount,
                'currency' => $goal->currency,
            ]);

            return $goal;
        });

        return $this->respond($goal)->response()->setStatusCode(201);
    }

    public function show(FinancialGoal $goal): GoalResource
    {
        Gate::authorize('view', $goal);

        return $this->respond($goal);
    }

    public function update(GoalRequest $request, FinancialGoal $goal): GoalResource
    {
        DB::transaction(function () use ($request, $goal) {
            $goal->fill($request->fields());
            $changes = AuditTrail::diff($goal);
            $goal->save();
            if ($changes !== []) {
                AuditTrail::record($request->user(), 'goal.updated', $goal, $changes);
            }
        });

        return $this->respond($goal);
    }

    /**
     * Deletes the goal and its contributions. A linked account and its transactions stay.
     */
    public function destroy(Request $request, FinancialGoal $goal): Response
    {
        Gate::authorize('delete', $goal);

        DB::transaction(function () use ($request, $goal) {
            AuditTrail::record($request->user(), 'goal.deleted', $goal, ['name' => $goal->name]);
            $goal->delete();
        });

        return response()->noContent();
    }

    private function respond(FinancialGoal $goal): GoalResource
    {
        return GoalResource::make($this->progress->measure(collect([$goal->refresh()]))->first());
    }
}
