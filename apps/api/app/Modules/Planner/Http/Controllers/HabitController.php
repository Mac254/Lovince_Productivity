<?php

namespace App\Modules\Planner\Http\Controllers;

use App\Modules\Planner\Services\HabitSummary;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HabitController extends Controller
{
    public function __invoke(Request $request, HabitSummary $habits): JsonResponse
    {
        $input = $request->validate(['days' => ['sometimes', 'integer', Rule::in(HabitSummary::DAYS)]]);

        return response()->json(['data' => $habits->for($request->user(), (int) ($input['days'] ?? 30))]);
    }
}
