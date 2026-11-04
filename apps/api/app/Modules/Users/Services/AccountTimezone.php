<?php

namespace App\Modules\Users\Services;

use App\Modules\Users\Models\User;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each account lives in its own timezone: where its days start for today, overdue, streaks and
 * reports. It is applied for the whole request once the user is known, and around work done for
 * the account outside a request. Stored times are local to the account, so changing the zone
 * moves every stored time of the account to the new zone, keeping the moments they record.
 */
final class AccountTimezone
{
    /** @var list<array{table: string, columns: list<string>, owner: string, parent: ?string}>|null */
    private static ?array $tables = null;

    public static function of(User $user): string
    {
        return $user->timezone ?: config('app.default_timezone');
    }

    public static function apply(User $user): void
    {
        self::use(self::of($user));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public static function run(User $user, Closure $work): mixed
    {
        $previous = config('app.timezone');
        self::apply($user);

        try {
            return $work();
        } finally {
            self::use($previous);
        }
    }

    public static function change(User $user, string $zone): void
    {
        $from = self::of($user);
        if ($from === $zone) {
            return;
        }

        DB::transaction(function () use ($user, $zone, $from) {
            $user->forceFill(['timezone' => $zone])->save();
            self::shift('users', ['email_verified_at', 'created_at', 'updated_at'], fn ($query) => $query->where('id', $user->id), $from, $zone);

            foreach (self::tables() as $table) {
                self::shift($table['table'], $table['columns'], fn ($query) => $table['parent'] === null
                    ? $query->where($table['owner'], $user->id)
                    : $query->whereIn($table['owner'], DB::table($table['parent'])->where('user_id', $user->id)->select('id')), $from, $zone);
            }
        });

        $user->refresh();
        self::use($zone);
    }

    private static function use(string $zone): void
    {
        date_default_timezone_set($zone);
        config(['app.timezone' => $zone]);
    }

    /**
     * @param  list<string>  $columns
     * @param  Closure(Builder): mixed  $scope
     */
    private static function shift(string $table, array $columns, Closure $scope, string $from, string $to): void
    {
        $query = DB::table($table)->select(['id', ...$columns]);
        $scope($query);

        $query->lazyById()->each(function (object $row) use ($table, $columns, $from, $to) {
            $changes = [];
            foreach ($columns as $column) {
                if ($row->{$column} !== null) {
                    $changes[$column] = Carbon::parse($row->{$column}, $from)->setTimezone($to)->format('Y-m-d H:i:s');
                }
            }
            if ($changes) {
                DB::table($table)->where('id', $row->id)->update($changes);
            }
        });
    }

    /**
     * Every table holding the account's times: those with a `user_id`, and those owned through
     * a parent that has one (a schedule's items). Found from the schema, so new modules are covered.
     *
     * @return list<array{table: string, columns: list<string>, owner: string, parent: ?string}>
     */
    private static function tables(): array
    {
        if (self::$tables !== null) {
            return self::$tables;
        }

        $found = [];
        $children = [];
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            if (in_array($name, ['users', 'sessions'], true)) {
                continue;
            }
            $columns = collect(Schema::getColumns($name));
            $times = $columns->filter(fn (array $column) => in_array($column['type_name'], ['datetime', 'timestamp', 'timestamptz'], true))
                ->pluck('name')->values()->all();
            if (! $times || ! $columns->contains('name', 'id')) {
                continue;
            }
            if ($columns->contains('name', 'user_id')) {
                $found[$name] = ['table' => $name, 'columns' => $times, 'owner' => 'user_id', 'parent' => null];
            } else {
                $children[$name] = $times;
            }
        }

        foreach ($children as $name => $times) {
            foreach (Schema::getForeignKeys($name) as $key) {
                if (isset($found[$key['foreign_table']]) && count($key['columns']) === 1 && $key['on_delete'] === 'cascade') {
                    $found[$name] = ['table' => $name, 'columns' => $times, 'owner' => $key['columns'][0], 'parent' => $key['foreign_table']];
                    break;
                }
            }
        }

        return self::$tables = array_values($found);
    }
}
