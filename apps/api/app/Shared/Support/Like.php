<?php

namespace App\Shared\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Case-insensitive substring search whose `%` and `_` match themselves, not any text.
 */
final class Like
{
    public static function pattern(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
    }

    /**
     * Matches when any of `$columns` contains the term. Group it in a `where` closure.
     *
     * @param  list<string>  $columns
     */
    public static function any(Builder $query, array $columns, string $pattern): void
    {
        foreach ($columns as $column) {
            $query->orWhereRaw("lower({$column}) like lower(?) escape '!'", [$pattern]);
        }
    }
}
