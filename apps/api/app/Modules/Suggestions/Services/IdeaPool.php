<?php

namespace App\Modules\Suggestions\Services;

use App\Modules\Suggestions\Contracts\IdeaFeed;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Suggestions\Enums\SuggestionSource;
use App\Modules\Suggestions\Enums\SuggestionStatus;
use App\Modules\Suggestions\Models\PooledIdea;
use App\Modules\Suggestions\Models\Suggestion;
use Illuminate\Support\Str;

/**
 * Fills the shared pool from the configured feed, letting in only ideas that pass every check:
 * plain letters, digits and simple punctuation, sentence case with no names or brands, two to twelve
 * words starting with an approved verb, and no blocked word or phrase. Anything else is dropped,
 * never repaired. Ideas are keyed by slug, so each is stored once and none repeats a built-in idea.
 * Each run re-checks the whole pool, so tightening the rules also clears what is already there.
 */
class IdeaPool
{
    /**
     * @return int Ideas added.
     */
    public function fill(): int
    {
        $config = config('suggestions.feed');
        if (! $config['enabled']) {
            return 0;
        }

        $feed = app($config['driver']);
        assert($feed instanceof IdeaFeed);
        $now = now();

        $rows = collect($feed->fetch((int) $config['pulls']))
            ->map(fn (array $idea) => $this->clean($idea))
            ->filter()
            ->unique('key')
            ->map(fn (array $idea) => $idea + ['feed' => class_basename($config['driver']), 'created_at' => $now, 'updated_at' => $now]);

        $added = PooledIdea::query()->insertOrIgnore($rows->values()->all());
        $this->prune((int) $config['keep']);

        return $added;
    }

    public static function key(string $title): string
    {
        return 'web-'.Str::limit(Str::slug($title), 80, '');
    }

    /**
     * Drops ideas the rules no longer allow, withdrawing any still open, then keeps the newest `$keep`.
     */
    private function prune(int $keep): void
    {
        $rejected = PooledIdea::query()->get()
            ->reject(fn (PooledIdea $idea) => $this->clean(['title' => $idea->title, 'category' => $idea->category->value]) !== null)
            ->pluck('key');
        if ($rejected->isNotEmpty()) {
            PooledIdea::query()->whereIn('key', $rejected)->delete();
            Suggestion::query()
                ->where('source', SuggestionSource::Library)
                ->where('status', SuggestionStatus::New)
                ->where(fn ($query) => $rejected->each(fn (string $key) => $query->orWhere('key', "idea:{$key}")->orWhere('key', 'like', "idea:{$key}:%")))
                ->delete();
        }

        $oldestKept = PooledIdea::query()->latest('id')->skip(max(0, $keep - 1))->value('id');
        if ($oldestKept !== null) {
            PooledIdea::query()->where('id', '<', $oldestKept)->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $idea
     * @return array<string, mixed>|null
     */
    private function clean(array $idea): ?array
    {
        $category = SuggestionCategory::tryFrom(is_string($idea['category'] ?? null) ? $idea['category'] : '');
        $title = is_string($idea['title'] ?? null) ? $this->text($idea['title']) : null;
        if ($category === null || $title === null) {
            return null;
        }

        $key = self::key($title);
        $library = collect(config('suggestions.library'))->map(fn (array $entry) => self::key($entry['title']));
        if ($library->contains($key)) {
            return null;
        }

        $minutes = $idea['minutes'] ?? null;

        return [
            'key' => $key,
            'category' => $category->value,
            'title' => $title,
            'minutes' => is_int($minutes) && $minutes > 0 && $minutes <= 120 ? $minutes : null,
        ];
    }

    private function text(string $raw): ?string
    {
        $config = config('suggestions.feed');
        $text = trim(preg_replace('/ +/', ' ', str_replace('’', "'", $raw)) ?? '');
        $text = rtrim($text, '.');

        // Only letters, digits, spaces, apostrophes, hyphens and commas: no markup, links, symbols or emoji.
        if (! preg_match("/^[A-Za-z0-9 ',-]+$/", $text) || mb_strlen($text) > $config['max_length']) {
            return null;
        }

        // Sentence case with no capitals after the first letter rules out names, brands and shouting.
        $words = explode(' ', $text);
        if (preg_match('/[A-Z]/', substr($text, 1)) || count($words) < 2 || count($words) > 12) {
            return null;
        }

        if (! in_array(strtolower($words[0]), $config['verbs'], true)) {
            return null;
        }

        $blocked = implode('|', array_map(
            fn (string $phrase) => str_ends_with($phrase, '*') ? preg_quote(rtrim($phrase, '*'), '/').'\\w*' : preg_quote($phrase, '/').'s?',
            $config['blocked'],
        ));
        if ($blocked !== '' && preg_match("/\\b({$blocked})\\b/i", $text)) {
            return null;
        }

        return ucfirst(str_replace("'", '’', $text));
    }
}
