<?php

namespace App\Modules\Reviews\Http\Controllers;

use App\Modules\Reviews\Enums\ReviewPeriod;
use App\Modules\Reviews\Http\Requests\ReviewRequest;
use App\Modules\Reviews\Models\Review;
use App\Modules\Reviews\Services\ReviewSummary;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    private const LIST_LIMIT = 120;

    public function index(Request $request): JsonResponse
    {
        $request->validate(['period' => ['sometimes', Rule::enum(ReviewPeriod::class)]]);

        $reviews = Review::query()
            ->whereBelongsTo($request->user())
            ->whereNotNull('completed_at')
            ->when($request->query('period'), fn ($q, string $period) => $q->where('period', $period))
            ->orderByDesc('starts_on')
            ->limit(self::LIST_LIMIT)
            ->get(['period', 'starts_on', 'completed_at']);

        return response()->json(['data' => $reviews->map(fn (Review $review) => [
            'period' => $review->period->value,
            'start' => $review->starts_on->toDateString(),
            'label' => $review->label(),
            'completed_at' => $review->completed_at->toIso8601String(),
        ])->all()]);
    }

    public function show(Request $request, ReviewSummary $summary, string $period, string $date): JsonResponse
    {
        $kind = $this->period($period);

        return response()->json(['data' => $summary->for($request->user(), $kind, $this->start($kind, $date))]);
    }

    public function update(ReviewRequest $request, ReviewSummary $summary, string $period, string $date): JsonResponse
    {
        $kind = $this->period($period);
        $start = $this->start($kind, $date);
        $validated = $request->validated();

        $review = Review::query()->whereBelongsTo($request->user())->for($kind, $start)->first()
            ?? $request->user()->reviews()->make(['period' => $kind, 'starts_on' => $start->toDateString()]);

        $review->fill(collect($validated)->only(['wins', 'challenges', 'next_focus'])->map(fn (?string $text) => $text === null ? null : (trim($text) ?: null))->all());
        if (array_key_exists('completed', $validated)) {
            $review->completed_at = $validated['completed'] ? ($review->completed_at ?? now()) : null;
        }
        $review->save();

        return response()->json(['data' => $summary->for($request->user(), $kind, $start)]);
    }

    /** `/reviews/{date}` predates months and years, so it still means the week. */
    public function showWeek(Request $request, ReviewSummary $summary, string $date): JsonResponse
    {
        return $this->show($request, $summary, ReviewPeriod::Week->value, $date);
    }

    public function updateWeek(ReviewRequest $request, ReviewSummary $summary, string $date): JsonResponse
    {
        return $this->update($request, $summary, ReviewPeriod::Week->value, $date);
    }

    private function period(string $period): ReviewPeriod
    {
        return ReviewPeriod::tryFrom($period) ?? abort(404);
    }

    /** The period that contains the date, up to the current one. */
    private function start(ReviewPeriod $period, string $date): Carbon
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        abort_unless($parsed && $parsed->format('Y-m-d') === $date, 404);
        $start = $period->start(Carbon::createFromFormat('!Y-m-d', $date));
        abort_if($start->isAfter($period->start(today())), 404);

        return $start;
    }
}
