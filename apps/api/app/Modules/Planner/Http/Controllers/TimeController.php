<?php

namespace App\Modules\Planner\Http\Controllers;

use App\Modules\Planner\Services\TimeSummary;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TimeController extends Controller
{
    public function __invoke(Request $request, TimeSummary $time): JsonResponse
    {
        $input = $request->validate([
            'range' => ['sometimes', Rule::in(TimeSummary::RANGES)],
            'date' => ['sometimes', 'date_format:Y-m-d'],
        ]);
        $date = isset($input['date']) ? Carbon::parse($input['date']) : today();

        return response()->json(['data' => $time->for($request->user(), $input['range'] ?? 'week', $date)]);
    }
}
