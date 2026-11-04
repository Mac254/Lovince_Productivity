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
