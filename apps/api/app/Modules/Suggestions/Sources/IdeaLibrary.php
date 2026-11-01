<?php

namespace App\Modules\Suggestions\Sources;

use App\Modules\Suggestions\Contracts\Suggester;
use App\Modules\Suggestions\Contracts\SuggestionDraft;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Suggestions\Enums\SuggestionSource as Source;
use App\Modules\Suggestions\Enums\SuggestionStatus;
use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;

/**
 * Tops up the open ideas from `config('suggestions.library')` to the user's daily number, in an
 * order that changes each day, never offering an idea twice.
 */
class IdeaLibrary implements Suggester
{
    public function __construct(private readonly int $perDay) {}

    public function drafts(User $user, CarbonImmutable $now): iterable
    {
        $open = Suggestion::query()->whereBelongsTo($user)->where('source', Source::Library)->where('status', SuggestionStatus::New)->count();
        $wanted = $this->perDay - $open;
        if ($wanted <= 0) {
            return;
        }

        $seen = Suggestion::query()->whereBelongsTo($user)->where('source', Source::Library)->pluck('key')->all();
        $seed = $now->toDateString();

        yield from collect(config('suggestions.library'))
            ->reject(fn (array $idea) => in_array("idea:{$idea['key']}", $seen, true))
            ->sortBy(fn (array $idea) => crc32($idea['key'].$seed))
            ->take($wanted)
            ->map(fn (array $idea) => new SuggestionDraft(
                key: "idea:{$idea['key']}",
                title: $idea['title'],
                category: SuggestionCategory::from($idea['category']),
                body: $idea['body'] ?? null,
                source: Source::Library,
            ));
    }
}
