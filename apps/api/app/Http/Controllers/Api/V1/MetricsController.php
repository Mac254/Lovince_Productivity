<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MetricsService;
use App\Support\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetricsController extends Controller
{
    public function __invoke(Request $request, MetricsService $metrics): JsonResponse
    {
        $days = (int) ($request->validate([
            'days' => ['sometimes', 'integer', Rule::in(Contract::METRIC_RANGES)],
        ])['days'] ?? 30);

        return response()->json(['data' => $metrics->for($request->user(), $days)]);
    }
}
