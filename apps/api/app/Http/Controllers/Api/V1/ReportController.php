<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DailyReportResource;
use App\Models\DailyReport;
use App\Services\DailyReportGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $reports = DailyReport::query()
            ->whereBelongsTo($request->user())
            ->orderByDesc('date')
            ->paginate(30);

        return DailyReportResource::collection($reports);
    }

    public function show(Request $request, string $date): DailyReportResource
    {
        $report = DailyReport::query()->whereBelongsTo($request->user())->whereDate('date', $date)->firstOrFail();

        return DailyReportResource::make($report)->detailed();
    }

    /**
     * (Re)generates today's report on demand. Past days are left as they were recorded.
     */
    public function store(Request $request, DailyReportGenerator $generator): JsonResponse
    {
        $report = $generator->generate($request->user(), today());

        return DailyReportResource::make($report)->detailed()->response()->setStatusCode(201);
    }
}
