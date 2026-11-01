<?php

namespace App\Modules\Reviews\Http\Controllers;

use App\Modules\Reviews\Http\Requests\WeeklyReviewRequest;
use App\Modules\Reviews\Models\WeeklyReview;
use App\Modules\Reviews\Services\WeeklyReviewSummary;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WeeklyReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reviews = WeeklyReview::query()
            ->whereBelongsTo($request->user())
            ->whereNotNull('completed_at')
            ->orderByDesc('week_start')
            ->limit(52)
            ->get(['week_start', 'completed_at']);

        return response()->json(['data' => $reviews->map(fn (WeeklyReview $review) => [
            'week_start' => $review->week_start->toDateString(),
            'completed_at' => $review->completed_at->toIso8601String(),
        ])->all()]);
    }

    public function show(Request $request, string $week, WeeklyReviewSummary $summary): JsonResponse
    {
        return response()->json(['data' => $summary->for($request->user(), $this->weekStart($week))]);
    }

    public function update(WeeklyReviewRequest $request, string $week, WeeklyReviewSummary $summary): JsonResponse
    {
        $start = $this->weekStart($week);
        $validated = $request->validated();

        $review = WeeklyReview::query()->whereBelongsTo($request->user())->whereDate('week_start', $start->toDateString())->first()
            ?? $request->user()->weeklyReviews()->make(['week_start' => $start->toDateString()]);

        $review->fill(collect($validated)->only(['wins', 'challenges', 'next_focus'])->map(fn (?string $text) => $text === null ? null : (trim($text) ?: null))->all());
        if (array_key_exists('completed', $validated)) {
            $review->completed_at = $validated['completed'] ? ($review->completed_at ?? now()) : null;
        }
        $review->save();

        return response()->json(['data' => $summary->for($request->user(), $start)]);
    }

    private function weekStart(string $week): Carbon
    {
        $date = Carbon::createFromFormat('!Y-m-d', $week);
        abort_unless($date && $date->toDateString() === $week, 404);
        $start = $date->startOfWeek();
        abort_if($start->isAfter(today()->startOfWeek()), 404);

        return $start;
    }
}
