<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Http\Requests\GoalContributionRequest;
use App\Modules\Accounting\Http\Resources\GoalContributionResource;
use App\Modules\Accounting\Models\FinancialGoal;
use App\Modules\Accounting\Models\GoalContribution;
use App\Modules\Accounting\Services\AuditTrail;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The money recorded against a goal that has no account, newest first.
 */
class GoalContributionController extends Controller
{
    public function index(FinancialGoal $goal): AnonymousResourceCollection
    {
        Gate::authorize('view', $goal);

        return GoalContributionResource::collection($goal->contributions()->orderByDesc('date')->orderByDesc('id')->get());
    }

    public function store(GoalContributionRequest $request, FinancialGoal $goal): JsonResponse
    {
        $contribution = DB::transaction(function () use ($request, $goal) {
            $contribution = $goal->contributions()->create($request->fields());
            AuditTrail::record($request->user(), 'goal.contribution_added', $goal, ['amount' => $contribution->amount]);

            return $contribution;
        });

        return GoalContributionResource::make($contribution)->response()->setStatusCode(201);
    }

    /**
     * Removing money put in can't leave the goal below zero.
     */
    public function destroy(Request $request, FinancialGoal $goal, GoalContribution $contribution): Response
    {
        Gate::authorize('update', $goal);

        if ($contribution->amount > 0 && $goal->contributions()->sum('amount') - $contribution->amount < 0) {
            throw ValidationException::withMessages(['contribution' => 'Remove the money taken out after it first.']);
        }

        DB::transaction(function () use ($request, $goal, $contribution) {
            AuditTrail::record($request->user(), 'goal.contribution_removed', $goal, ['amount' => $contribution->amount]);
            $contribution->delete();
        });

        return response()->noContent();
    }
}
