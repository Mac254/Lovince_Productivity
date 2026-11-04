<?php

namespace App\Modules\Search\Services;

use App\Modules\Achievements\Models\Achievement;
use App\Modules\Blockers\Models\Blocker;
use App\Modules\Goals\Models\Goal;
use App\Modules\Journal\Models\JournalEntry;
use App\Modules\Planner\Models\Schedule;
use App\Modules\Reviews\Models\WeeklyReview;
use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Suggestions\Enums\SuggestionStatus;
use App\Modules\Suggestions\Models\Suggestion;
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
            ...$this->achievements($user, $pattern, $query),
            ...$this->suggestions($user, $pattern, $query),
            ...$this->reviews($user, $pattern, $query),
            ...$this->schedules($user, $pattern, $query),
        ];
    }

    private function achievements(User $user, string $pattern, string $query): array
    {
        return Achievement::query()->whereBelongsTo($user)
            ->where(fn (Builder $q) => $this->like($q, ['title', 'description'], $pattern))
            ->latest('achieved_on')->limit(self::PER_KIND)->get()
            ->map(fn (Achievement $achievement) => $this->result('achievement', $achievement->id, $achievement->title, $achievement->description, $query, $achievement->achieved_on->toDateString(), $achievement->task_id))
            ->all();
    }

    private function suggestions(User $user, string $pattern, string $query): array
    {
        return Suggestion::query()->whereBelongsTo($user)
            ->where('status', '!=', SuggestionStatus::Dismissed)
            ->where(fn (Builder $q) => $this->like($q, ['title', 'body'], $pattern))
            ->latest('updated_at')->limit(self::PER_KIND)->get()
            ->map(fn (Suggestion $suggestion) => $this->result('suggestion', $suggestion->id, $suggestion->title, $suggestion->body, $query, null, $suggestion->task_id))
            ->all();
    }

    private function reviews(User $user, string $pattern, string $query): array
    {
        return WeeklyReview::query()->whereBelongsTo($user)
            ->where(fn (Builder $q) => $this->like($q, ['wins', 'challenges', 'next_focus'], $pattern))
            ->latest('week_start')->limit(self::PER_KIND)->get()
            ->map(fn (WeeklyReview $review) => $this->result(
                'review',
                $review->id,
                'Week of '.$review->week_start->toFormattedDateString(),
                collect([$review->wins, $review->challenges, $review->next_focus])->first(fn (?string $text) => $text !== null && Str::contains($text, $query, true)),
                $query,
                $review->week_start->toDateString(),
            ))
            ->all();
    }

    private function schedules(User $user, string $pattern, string $query): array
    {
        return Schedule::query()->whereBelongsTo($user)
            ->where(fn (Builder $q) => $this->like($q, ['name'], $pattern))
            ->orderBy('name')->limit(self::PER_KIND)->get()
            ->map(fn (Schedule $schedule) => $this->result('schedule', $schedule->id, $schedule->name, null, $query, null))
            ->all();
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
