<?php

namespace App\Modules\Search\Services;

use App\Modules\Blockers\Models\Blocker;
use App\Modules\Goals\Models\Goal;
use App\Modules\Journal\Models\JournalEntry;
use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SearchService
{
    public const PER_KIND = 5;

    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * @return list<array{kind: string, id: int, title: string, excerpt: ?string, date: ?string, task_id: ?int}>
     */
    public function for(User $user, string $query): array
    {
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query).'%';
        $journal = $this->settings->resolve($user->settings)['journal']['searchable'];

        return [
            ...$this->tasks($user, $pattern, $query),
            ...$this->goals($user, $pattern, $query),
            ...$this->blockers($user, $pattern, $query),
            ...($journal ? $this->journal($user, $pattern, $query) : []),
        ];
    }

    private function tasks(User $user, string $pattern, string $query): array
    {
        return Task::query()->whereBelongsTo($user)
            ->where(fn (Builder $q) => $this->like($q, ['title', 'description'], $pattern))
            ->orderByRaw('completed_at IS NOT NULL')->latest('updated_at')
            ->limit(self::PER_KIND)->get()
            ->map(fn (Task $task) => $this->result('task', $task->id, $task->title, $task->description, $query, $task->due_date?->toDateString(), $task->id))
            ->all();
    }

    private function goals(User $user, string $pattern, string $query): array
    {
        return Goal::query()->whereBelongsTo($user)
            ->where(fn (Builder $q) => $this->like($q, ['title', 'description'], $pattern))
            ->latest('updated_at')->limit(self::PER_KIND)->get()
            ->map(fn (Goal $goal) => $this->result('goal', $goal->id, $goal->title, $goal->description, $query, $goal->target_date?->toDateString()))
            ->all();
    }

    private function blockers(User $user, string $pattern, string $query): array
    {
        return Blocker::query()->whereBelongsTo($user)->with('task:id,title')
            ->where(fn (Builder $q) => $this->like($q, ['description'], $pattern))
            ->latest('updated_at')->limit(self::PER_KIND)->get()
            ->map(fn (Blocker $blocker) => $this->result('blocker', $blocker->id, $blocker->task->title ?? 'Blocker', $blocker->description, $query, null, $blocker->task_id))
            ->all();
    }

    private function journal(User $user, string $pattern, string $query): array
    {
        return JournalEntry::query()->whereBelongsTo($user)
            ->where(fn (Builder $q) => $this->like($q, ['body'], $pattern))
            ->latest('date')->limit(self::PER_KIND)->get()
            ->map(fn (JournalEntry $entry) => $this->result('journal', $entry->id, $entry->date->toFormattedDateString(), $entry->body, $query, $entry->date->toDateString()))
            ->all();
    }

    /**
     * @param  list<string>  $columns
     */
    private function like(Builder $query, array $columns, string $pattern): void
    {
        foreach ($columns as $column) {
            $query->orWhereRaw("lower({$column}) like lower(?) escape '!'", [$pattern]);
        }
    }

    private function result(string $kind, int $id, string $title, ?string $text, string $query, ?string $date, ?int $taskId = null): array
    {
        $excerpt = $text === null ? null : Str::excerpt(Str::squish($text), $query, ['radius' => 60]);

        return [
            'kind' => $kind,
            'id' => $id,
            'title' => $title,
            'excerpt' => $excerpt === '' ? null : ($excerpt ?? Str::limit(Str::squish($text), 120)),
            'date' => $date,
            'task_id' => $taskId,
        ];
    }
}
