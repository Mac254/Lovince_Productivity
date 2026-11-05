<?php

namespace App\Modules\Suggestions\Services;

use App\Modules\Suggestions\Contracts\IdeaFeed;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Suggestions\Models\PooledIdea;
use Illuminate\Support\Str;

/**
 * Fills the shared pool from the configured feed. Each idea is cleaned to plain sentence-case text,
 * keyed by its slug so the same idea is stored once, and dropped if it is too long, looks like markup
 * or a link, mentions a blocked word or repeats a built-in idea. The oldest ideas beyond `keep` go.
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
        $library = collect(config('suggestions.library'))->map(fn (array $idea) => self::key($idea['title']))->flip();
        $now = now();

        $rows = collect($feed->fetch((int) $config['pulls']))
            ->map(fn (array $idea) => $this->clean($idea))
            ->filter()
            ->reject(fn (array $idea) => $library->has($idea['key']))
            ->unique('key')
            ->map(fn (array $idea) => $idea + ['feed' => class_basename($config['driver']), 'created_at' => $now, 'updated_at' => $now]);

        $added = PooledIdea::query()->insertOrIgnore($rows->values()->all());

        $oldestKept = PooledIdea::query()->latest('id')->skip(max(0, (int) $config['keep'] - 1))->value('id');
        if ($oldestKept !== null) {
            PooledIdea::query()->where('id', '<', $oldestKept)->delete();
        }

        return $added;
    }

    public static function key(string $title): string
    {
        return 'web-'.Str::limit(Str::slug($title), 80, '');
    }

    /**
     * @param  array<string, mixed>  $idea
     * @return array<string, mixed>|null
     */
    private function clean(array $idea): ?array
    {
        $category = SuggestionCategory::tryFrom((string) ($idea['category'] ?? ''));
        $title = $this->text((string) ($idea['title'] ?? ''));
        if ($category === null || $title === null) {
            return null;
        }

        $minutes = $idea['minutes'] ?? null;

        return [
            'key' => self::key($title),
            'category' => $category->value,
            'title' => $title,
            'minutes' => is_int($minutes) && $minutes > 0 && $minutes <= 600 ? $minutes : null,
        ];
    }

    private function text(string $raw): ?string
    {
        $text = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\p{C}]+/u', ' ', $text) ?? '';
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '', ' .!;,:-');
        $length = mb_strlen($text);
        if ($length < 4 || $length > config('suggestions.feed.max_length')) {
            return null;
        }

        if (preg_match('/https?:|www\.|[<>{}\[\]\\\\|@#$^*=_`~]/i', $text)) {
            return null;
        }

        $blocked = implode('|', array_map(fn (string $word) => preg_quote($word, '/'), config('suggestions.feed.blocked')));
        if ($blocked !== '' && preg_match("/\b({$blocked})\b/iu", $text)) {
            return null;
        }

        // Sentence case: shouted text and shouted words calm down, short acronyms like DIY stay.
        $text = mb_strtoupper($text) === $text
            ? mb_strtolower($text)
            : preg_replace_callback('/\b\p{Lu}{4,}\b/u', fn (array $word) => mb_strtolower($word[0]), $text);

        return Str::ucfirst(str_replace("'", '’', $text));
    }
}
