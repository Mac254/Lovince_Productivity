<?php

namespace App\Modules\Suggestions\Sources;

use App\Modules\Suggestions\Contracts\Suggester;
use App\Modules\Suggestions\Contracts\SuggestionDraft;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Suggestions\Enums\SuggestionSource as Source;
use App\Modules\Suggestions\Enums\SuggestionStatus;
use App\Modules\Suggestions\Models\PooledIdea;
use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Tops up the open ideas from `config('suggestions.library')` and the shared pool of fetched ideas
 * to the user's daily number, one category at a time in an order that changes each day. An idea that is open or accepted is never
 * offered twice; one marked done or dismissed rests for its cooldown before it can return.
 */
class IdeaLibrary implements Suggester
{
    /**
     * @param  list<string>  $resting
     */
    public function __construct(private readonly int $perDay, private readonly array $resting = []) {}

    public function drafts(User $user, CarbonImmutable $now): iterable
    {
        $rows = Suggestion::query()->whereBelongsTo($user)->where('source', Source::Library)
            ->get(['key', 'status', 'category', 'done_at', 'dismissed_at']);
        $open = $rows->where('status', SuggestionStatus::New);
        $wanted = $this->perDay - $open->count();
        if ($wanted <= 0) {
            return;
        }

        $blocked = $rows->filter(fn (Suggestion $row) => $this->blocks($row, $now))->map(fn (Suggestion $row) => self::idea($row->key))->flip();
        $offered = $rows->map(fn (Suggestion $row) => self::idea($row->key))->flip();
        $seed = $now->toDateString();
        $openCategories = $open->map(fn (Suggestion $row) => $row->category->value)->flip();

        $ideas = collect(config('suggestions.library'))
            ->concat(PooledIdea::query()->get()->map->toIdea())
            ->reject(fn (array $idea) => $blocked->has($idea['key']) || in_array($idea['key'], $this->resting, true))
            ->sortBy(fn (array $idea) => crc32($idea['key'].$seed));

        yield from $this->spread($ideas, $openCategories)
            ->take($wanted)
            ->map(fn (array $idea) => new SuggestionDraft(
                key: $offered->has($idea['key']) ? "idea:{$idea['key']}:{$seed}" : "idea:{$idea['key']}",
                title: $idea['title'],
                category: SuggestionCategory::from($idea['category']),
                body: $idea['body'] ?? null,
                action: self::action($idea),
                source: Source::Library,
            ));
    }

    public static function idea(string $key): string
    {
        return preg_replace('/^idea:|:\d{4}-\d{2}-\d{2}$/', '', $key);
    }

    private function blocks(Suggestion $row, CarbonImmutable $now): bool
    {
        $cooldown = config('suggestions.cooldown_days');

        return match ($row->status) {
            SuggestionStatus::New, SuggestionStatus::Accepted => true,
            SuggestionStatus::Done => $row->done_at === null || $row->done_at->greaterThan($now->subDays($cooldown['done'])),
            SuggestionStatus::Dismissed => $row->dismissed_at === null || $row->dismissed_at->greaterThan($now->subDays($cooldown['dismissed'])),
        };
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $ideas
     * @param  Collection<string, int>  $openCategories
     * @return Collection<int, array<string, mixed>>
     */
    private function spread(Collection $ideas, Collection $openCategories): Collection
    {
        $queues = $ideas->groupBy('category')
            ->sortBy(fn (Collection $group, string $category) => $openCategories->has($category) ? 1 : 0, SORT_REGULAR)
            ->map->values();
        $spread = collect();
        for ($round = 0; $round < $queues->max(fn (Collection $group) => $group->count()); $round++) {
            foreach ($queues as $group) {
                if ($group->has($round)) {
                    $spread->push($group[$round]);
                }
            }
        }

        return $spread;
    }

    /**
     * @param  array<string, mixed>  $idea
     * @return array<string, mixed>
     */
    private static function action(array $idea): array
    {
        if (isset($idea['route'])) {
            return ['type' => 'open', 'route' => $idea['route']];
        }

        return array_filter(['type' => 'task', 'minutes' => $idea['minutes'] ?? null]);
    }
}
