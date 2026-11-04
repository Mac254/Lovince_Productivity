<?php

namespace App\Modules\Suggestions\Http\Controllers;

use App\Modules\Suggestions\Enums\SuggestionSource;
use App\Modules\Suggestions\Enums\SuggestionStatus;
use App\Modules\Suggestions\Http\Requests\SuggestionRequest;
use App\Modules\Suggestions\Http\Resources\SuggestionResource;
use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Suggestions\Services\AcceptSuggestion;
use App\Modules\Suggestions\Services\SuggestionEngine;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SuggestionController extends Controller
{
    private const LIST_LIMIT = 100;

    private const CATALOGUE_LIMIT = 500;

    public function __construct(private readonly SuggestionEngine $engine) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $status = $request->validate([
            'status' => ['sometimes', Rule::in([...array_column(SuggestionStatus::cases(), 'value'), 'all'])],
        ])['status'] ?? SuggestionStatus::New->value;

        return SuggestionResource::collection(
            Suggestion::query()
                ->whereBelongsTo($request->user())
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->with('task:id,title')
                ->orderByRaw("CASE source WHEN 'insight' THEN 0 WHEN 'custom' THEN 1 ELSE 2 END")
                ->latest('updated_at')
                ->limit($status === 'all' ? self::CATALOGUE_LIMIT : self::LIST_LIMIT)
                ->get()
        );
    }

    /**
     * With `stale`, refreshes only when this hour's refresh hasn't run yet, which is how the page
     * keeps time-of-day suggestions current without listing having side effects.
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['stale' => ['sometimes', 'boolean']]);
        $added = $request->boolean('stale') ? $this->engine->refreshIfStale($request->user()) : $this->engine->refresh($request->user());

        return response()->json(['data' => ['added' => $added]]);
    }

    public function store(SuggestionRequest $request): JsonResponse
    {
        $suggestion = $request->user()->suggestions()->create($request->validated() + ['source' => SuggestionSource::Custom]);

        return SuggestionResource::make($suggestion)->response()->setStatusCode(201);
    }

    public function update(SuggestionRequest $request, Suggestion $suggestion): SuggestionResource
    {
        $suggestion->update($request->validated());

        return SuggestionResource::make($suggestion->load('task:id,title'));
    }

    public function accept(Request $request, Suggestion $suggestion, AcceptSuggestion $accept): SuggestionResource
    {
        Gate::authorize('update', $suggestion);
        $date = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']])['date'] ?? today()->toDateString();

        return SuggestionResource::make($accept($suggestion, $date)->load('task:id,title'));
    }

    public function destroy(Suggestion $suggestion): Response
    {
        Gate::authorize('delete', $suggestion);
        $suggestion->delete();

        return response()->noContent();
    }
}
