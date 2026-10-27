<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Journal\JournalRequest;
use App\Http\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use App\Support\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * One journal entry per day, addressed by date (`YYYY-MM-DD`). Saving an empty body deletes it.
 */
class JournalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:'.Contract::SEARCH_MAX],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => [
                'nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from',
                function (string $attribute, mixed $value, \Closure $fail) use ($request) {
                    $from = $request->query('from');
                    if (is_string($from) && strtotime($value) - strtotime($from) > 62 * 86400) {
                        $fail('Ask for at most 63 days at a time.');
                    }
                },
            ],
        ]);
        $range = isset($filters['from'], $filters['to']);

        $entries = JournalEntry::query()
            ->whereBelongsTo($request->user())
            ->when($filters['search'] ?? null, fn ($q, string $search) => $q->where('body', 'like', "%{$search}%"))
            ->when($range, fn ($q) => $q->whereDate('date', '>=', $filters['from'])->whereDate('date', '<=', $filters['to']))
            ->orderByDesc('date')
            // A calendar range (up to 63 days) comes back in one page.
            ->paginate($range ? 63 : 30);

        return JournalEntryResource::collection($entries);
    }

    public function show(Request $request, string $date): JsonResponse
    {
        $entry = $this->entry($request, $this->day($date));

        return response()->json(['data' => $entry ? JournalEntryResource::make($entry)->resolve() : null]);
    }

    public function update(JournalRequest $request, string $date): JsonResponse
    {
        $day = $this->day($date);
        $body = trim((string) $request->validated('body'));

        if ($body === '') {
            $this->entry($request, $day)?->delete();

            return response()->json(['data' => null]);
        }

        $entry = $this->entry($request, $day) ?? $request->user()->journalEntries()->make(['date' => $day]);
        $entry->body = $body;
        $entry->save();

        return response()->json(['data' => JournalEntryResource::make($entry)->resolve()]);
    }

    public function destroy(Request $request, string $date): Response
    {
        $this->entry($request, $this->day($date))?->delete();

        return response()->noContent();
    }

    private function entry(Request $request, string $day): ?JournalEntry
    {
        return JournalEntry::query()->whereBelongsTo($request->user())->whereDate('date', $day)->first();
    }

    /**
     * Valid calendar dates up to today only.
     */
    private function day(string $date): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        abort_unless($parsed && $parsed->format('Y-m-d') === $date && $date <= today()->toDateString(), 404);

        return $date;
    }
}
