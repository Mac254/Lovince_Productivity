<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Http\Requests\BudgetRequest;
use App\Modules\Accounting\Http\Resources\BudgetResource;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Services\AuditTrail;
use App\Modules\Accounting\Services\Statement;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BudgetController extends Controller
{
    public function __construct(private Statement $statement) {}

    /**
     * Every budget with what was spent against it in the month (`YYYY-MM`, this month by default).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['month' => ['sometimes', 'date_format:Y-m']]);
        $from = $request->has('month') ? Carbon::createFromFormat('!Y-m', $request->input('month')) : today()->startOfMonth();
        $to = $from->copy()->endOfMonth()->startOfDay();

        $budgets = Budget::query()->whereBelongsTo($request->user())->with('category')->get();
        $spending = $budgets->pluck('currency')->unique()
            ->mapWithKeys(fn (string $currency) => [$currency => $this->statement->spending($request->user(), $from, $to, $currency)]);

        $budgets = $budgets
            ->sortBy(fn (Budget $budget) => [$budget->currency, $budget->category->name])
            ->values()
            ->each(fn (Budget $budget) => $budget->spent = $spending[$budget->currency][$budget->category_id] ?? 0);

        return BudgetResource::collection($budgets)->additional([
            'meta' => ['month' => $from->format('Y-m'), 'from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    public function store(BudgetRequest $request): JsonResponse
    {
        $budget = DB::transaction(function () use ($request) {
            $budget = $request->user()->accountingBudgets()->create($request->fields());
            AuditTrail::record($request->user(), 'budget.created', $budget, ['category_id' => $budget->category_id, 'amount' => $budget->amount, 'currency' => $budget->currency]);

            return $budget;
        });

        return $this->respond($request, $budget)->response()->setStatusCode(201);
    }

    public function update(BudgetRequest $request, Budget $budget): BudgetResource
    {
        DB::transaction(function () use ($request, $budget) {
            $budget->fill(['amount' => $request->fields()['amount']]);
            $changes = AuditTrail::diff($budget);
            $budget->save();
            if ($changes !== []) {
                AuditTrail::record($request->user(), 'budget.updated', $budget, $changes);
            }
        });

        return $this->respond($request, $budget);
    }

    public function destroy(Request $request, Budget $budget): Response
    {
        Gate::authorize('delete', $budget);

        DB::transaction(function () use ($request, $budget) {
            AuditTrail::record($request->user(), 'budget.deleted', $budget, ['category_id' => $budget->category_id, 'amount' => $budget->amount]);
            $budget->delete();
        });

        return response()->noContent();
    }

    /**
     * The budget with this month's spending.
     */
    private function respond(Request $request, Budget $budget): BudgetResource
    {
        $budget->load('category');
        $spent = $this->statement->spending($request->user(), today()->startOfMonth(), today()->endOfMonth()->startOfDay(), $budget->currency);

        $budget->spent = $spent[$budget->category_id] ?? 0;

        return BudgetResource::make($budget);
    }
}
