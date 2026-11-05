<?php

namespace App\Modules\Suggestions\Contracts;

/**
 * A source of ideas, set in `config('suggestions.feed.driver')`. Called only by the
 * scheduled `suggestions:fetch-ideas`; a feed returns what it managed to get and never throws.
 */
interface IdeaFeed
{
    /**
     * @return list<array{title: string, category: string, minutes?: int|null}> Raw ideas; `IdeaPool` cleans them.
     */
    public function fetch(int $count): array;
}
