<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Http\Requests\PeriodRequest;
use App\Modules\Accounting\Services\Overview;
use App\Modules\Accounting\Services\Statement;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private Statement $statement) {}

    public function incomeStatement(PeriodRequest $request): JsonResponse
    {
        return $this->respond($request, $this->statement->incomeStatement(...));
    }

    public function cashFlow(PeriodRequest $request): JsonResponse
    {
        return $this->respond($request, $this->statement->cashFlow(...));
    }

    /**
     * Month by month across the range, at most `Statement::TREND_MONTHS` months ending at `to`.
     */
    public function trend(PeriodRequest $request): JsonResponse
    {
        $from = $request->from()->copy()->startOfMonth()->max($request->to()->copy()->startOfMonth()->subMonthsNoOverflow(Statement::TREND_MONTHS - 1));

        return $this->respond($request, fn ($user, $_, $to, $currency) => ['months' => $this->statement->trend($user, $from, $to, $currency)]);
    }

    private function respond(PeriodRequest $request, callable $report): JsonResponse
    {
        $user = $request->user();
        $currency = Overview::currencyFor($user, $request->input('currency'));

        return response()->json(['data' => [
            'currency' => $currency,
            'from' => $request->from()->toDateString(),
            'to' => $request->to()->toDateString(),
            ...$report($user, $request->from(), $request->to(), $currency),
        ]]);
    }
}
