<?php

namespace App\Modules\Dashboard\Http\Controllers;

use App\Modules\Dashboard\Services\DashboardSummary;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardSummary $summary): JsonResponse
    {
        return response()->json(['data' => $summary->for($request->user())]);
    }
}
