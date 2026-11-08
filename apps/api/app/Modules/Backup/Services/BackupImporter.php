<?php

namespace App\Modules\Backup\Services;

use App\Modules\Books\Models\BookReading;
use App\Modules\Reports\Models\DailyReport;
use App\Modules\Reports\Services\DailyReportGenerator;
use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use App\Support\Contract;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BackupImporter
{
    /** @var array<string, true> */
    private array $days = [];

    public function __construct(
        private readonly SettingsRegistry $settings,
        private readonly DailyReportGenerator $reports,
    ) {}

    /**
     * Adds a backup's records to the account, skipping any it already holds, so importing the same
     * file twice changes nothing. Records are matched on what identifies them to a person: task types
     * and schedules by name, tasks by title and creation time, journal entries and weekly reviews by
     * day, focus sessions by start, time blocks by slot. Saved daily reports for the days it touched
     * are rebuilt afterwards, since the import runs without model events.
     *
     * @param  array<string, mixed>  $backup
     * @return array<string, int>
     */
    public function import(User $user, array $backup): array
    {
        $this->days = [];
        $counts = DB::transaction(fn () => Model::withoutEvents(fn () => $this->run($user, $backup)));

        DailyReport::query()->whereBelongsTo($user)->get(['date'])
            ->filter(fn (DailyReport $report) => isset($this->days[$report->date->toDateString()]))
            ->each(fn (DailyReport $report) => $this->reports->generate($user, $report->date->copy()));

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $backup
     * @return array<string, int>
     */
    private function run(User $user, array $backup): array
    {
        $counts = array_fill_keys(['task_types', 'goals', 'tasks', 'blockers', 'journal', 'focus_sessions', 'weekly_reviews', 'time_blocks', 'achievements', 'schedules', 'suggestions', 'books', 'roadmap', 'skipped'], 0);

        if (isset($backup['settings'])) {
            $user->forceFill(['settings' => $this->settings->merge($user->settings, $backup['settings'])])->save();
        }

        $types = $user->taskTypes()->pluck('id', 'name')->all();
        $typeRefs = [];
        foreach ($backup['task_types'] ?? [] as $row) {
            if (! isset($types[$row['name']])) {
                if (count($types) >= Contract::TASK_TYPE_LIMIT) {
                    $counts['skipped']++;

                    continue;
                }
                $types[$row['name']] = $user->taskTypes()->create(['name' => $row['name'], 'tone' => $row['tone']])->id;
                $counts['task_types']++;
            }
            $typeRefs[$row['ref']] = $types[$row['name']];
        }

        $goalKey = fn (array $goal) => implode('|', [$goal['title'], $this->value($goal['timeframe']), $this->date($goal['start_date']), $this->date($goal['target_date'])]);
        $goals = $user->goals()->get()->mapWithKeys(fn ($goal) => [$goalKey($goal->only('title', 'timeframe', 'start_date', 'target_date')) => $goal->id])->all();
        $goalRefs = [];
        foreach ($backup['goals'] ?? [] as $row) {
            $key = $goalKey($row);
            if (isset($goals[$key])) {
                $counts['skipped']++;
            } else {
                $goals[$key] = $user->goals()->create($this->except($row, ['ref']))->id;
                $counts['goals']++;
            }
            $goalRefs[$row['ref']] = $goals[$key];
        }

        $tasks = $user->tasks()->get(['id', 'title', 'created_at'])->mapWithKeys(fn ($task) => [$task->title.'|'.$task->created_at->getTimestamp() => $task->id])->all();
        $taskRefs = [];
        foreach ($backup['tasks'] ?? [] as $row) {
            $key = isset($row['created_at']) ? $row['title'].'|'.$this->stamp($row['created_at']) : null;
            if ($key !== null && isset($tasks[$key])) {
                $taskRefs[$row['ref']] = $tasks[$key];
                $counts['skipped']++;

                continue;
            }

            $task = $user->tasks()->make($this->except($row, ['ref', 'goal_ref', 'task_type_ref', 'notes', 'checklist', 'completed_at', 'created_at']));
            $task->forceFill([
                'goal_id' => $goalRefs[$row['goal_ref'] ?? null] ?? null,
                'task_type_id' => $typeRefs[$row['task_type_ref'] ?? null] ?? null,
                'completed_at' => $row['completed_at'] ?? null,
                'created_at' => isset($row['created_at']) ? Carbon::parse($row['created_at'])->setTimezone(config('app.timezone')) : now(),
            ])->save();
            $taskRefs[$row['ref']] = $task->id;
            $counts['tasks']++;
            $this->touch($row['completed_at'] ?? null, $row['due_date'] ?? null);

            foreach ($row['notes'] ?? [] as $note) {
                $task->notes()->make($note)->forceFill(['user_id' => $user->id])->save();
                $this->touch($note['logged_on']);
            }
            foreach (array_values($row['checklist'] ?? []) as $position => $item) {
                $task->checklistItems()->make([...$item, 'done' => $item['done'] ?? false, 'position' => $position])->forceFill(['user_id' => $user->id])->save();
            }
        }

        $blockerKey = fn (string $description, ?int $taskId) => $description.'|'.$taskId;
        $blockers = $user->blockers()->get(['description', 'task_id'])->mapWithKeys(fn ($blocker) => [$blockerKey($blocker->description, $blocker->task_id) => true])->all();
        foreach ($backup['blockers'] ?? [] as $row) {
            $taskId = $taskRefs[$row['task_ref'] ?? null] ?? null;
            if (isset($blockers[$blockerKey($row['description'], $taskId)])) {
                $counts['skipped']++;

                continue;
            }
            $user->blockers()->make($this->except($row, ['task_ref', 'resolved_at']))->forceFill([
                'task_id' => $taskId,
                'resolved_at' => $row['resolved_at'] ?? null,
            ])->save();
            $blockers[$blockerKey($row['description'], $taskId)] = true;
            $counts['blockers']++;
            $this->touch($row['resolved_at'] ?? null);
        }

        $days = $user->journalEntries()->pluck('date')->map->toDateString()->flip();
        foreach ($backup['journal'] ?? [] as $row) {
            if ($days->has($row['date'])) {
                $counts['skipped']++;

                continue;
            }
            $user->journalEntries()->create($row);
            $days[$row['date']] = true;
            $counts['journal']++;
            $this->touch($row['date']);
        }

        $sessions = $user->focusSessions()->pluck('started_at')->mapWithKeys(fn ($at) => [$at->getTimestamp() => true])->all();
        foreach ($backup['focus_sessions'] ?? [] as $row) {
            $startedAt = Carbon::parse($row['started_at'])->setTimezone(config('app.timezone'))->startOfSecond();
            if (isset($sessions[$startedAt->getTimestamp()])) {
                $counts['skipped']++;

                continue;
            }
            $user->focusSessions()->make([...$this->except($row, ['task_ref']), 'started_at' => $startedAt])->forceFill([
                'task_id' => $taskRefs[$row['task_ref'] ?? null] ?? null,
                'completed' => $row['completed'] ?? false,
            ])->save();
            $sessions[$startedAt->getTimestamp()] = true;
            $counts['focus_sessions']++;
        }

        $weeks = $user->weeklyReviews()->pluck('week_start')->map->toDateString()->flip();
        foreach ($backup['weekly_reviews'] ?? [] as $row) {
            if ($weeks->has($row['week_start'])) {
                $counts['skipped']++;

                continue;
            }
            $user->weeklyReviews()->create($row);
            $weeks[$row['week_start']] = true;
            $counts['weekly_reviews']++;
        }

        $blocks = $user->timeBlocks()->get(['date', 'start_minute'])->mapWithKeys(fn ($block) => [$block->date->toDateString().'|'.$block->start_minute => true])->all();
        foreach ($backup['time_blocks'] ?? [] as $row) {
            $taskId = $taskRefs[$row['task_ref'] ?? null] ?? null;
            $slot = $row['date'].'|'.$row['start_minute'];
            if (isset($blocks[$slot]) || 24 * 60 < $row['start_minute'] + $row['minutes'] || ($taskId === null && blank($row['title'] ?? null))) {
                $counts['skipped']++;

                continue;
            }
            $user->timeBlocks()->create([...$this->except($row, ['task_ref']), 'task_id' => $taskId]);
            $blocks[$slot] = true;
            $counts['time_blocks']++;
        }

        $schedules = $user->schedules()->pluck('id', 'name')->all();
        $scheduleRefs = [];
        foreach ($backup['schedules'] ?? [] as $row) {
            if (isset($schedules[$row['name']])) {
                $scheduleRefs[$row['ref']] = $schedules[$row['name']];
                $counts['skipped']++;

                continue;
            }
            $schedule = $user->schedules()->create(['name' => $row['name'], 'days' => $row['days'] ?? null]);
            foreach ($row['items'] ?? [] as $item) {
                $schedule->items()->create([
                    ...$this->except($item, ['goal_ref', 'task_type_ref']),
                    'goal_id' => $goalRefs[$item['goal_ref'] ?? null] ?? null,
                    'task_type_id' => $typeRefs[$item['task_type_ref'] ?? null] ?? null,
                ]);
            }
            $schedules[$row['name']] = $scheduleRefs[$row['ref']] = $schedule->id;
            $counts['schedules']++;
        }

        $achievements = $user->achievements()->get(['title', 'achieved_on'])->mapWithKeys(fn ($achievement) => [$achievement->title.'|'.$achievement->achieved_on->toDateString() => true])->all();
        foreach ($backup['achievements'] ?? [] as $row) {
            $key = $row['title'].'|'.$row['achieved_on'];
            if (isset($achievements[$key])) {
                $counts['skipped']++;

                continue;
            }
            $user->achievements()->create([
                ...$this->except($row, ['goal_ref', 'task_ref']),
                'goal_id' => $goalRefs[$row['goal_ref'] ?? null] ?? null,
                'task_id' => $taskRefs[$row['task_ref'] ?? null] ?? null,
            ]);
            $achievements[$key] = true;
            $counts['achievements']++;
            $this->touch($row['achieved_on']);
        }

        // Progress comes across as saved, so the log goes in without moving it a second time.
        $books = $user->books()->get(['title', 'author'])->mapWithKeys(fn ($book) => [mb_strtolower($book->title.'|'.$book->author) => true])->all();
        foreach ($backup['books'] ?? [] as $row) {
            $key = mb_strtolower($row['title'].'|'.($row['author'] ?? ''));
            if (isset($books[$key])) {
                $counts['skipped']++;

                continue;
            }
            $book = $user->books()->create([
                ...$this->except($row, ['goal_ref', 'task_refs', 'readings']),
                'goal_id' => $goalRefs[$row['goal_ref'] ?? null] ?? null,
            ]);
            BookReading::withoutEvents(function () use ($book, $user, $row) {
                foreach ($row['readings'] ?? [] as $reading) {
                    $model = new BookReading($reading);
                    $model->user()->associate($user);
                    $model->book()->associate($book);
                    $model->save();
                    $this->touch($reading['read_on']);
                }
            });
            $taskIds = array_values(array_filter(array_map(fn ($ref) => $taskRefs[$ref] ?? null, $row['task_refs'] ?? [])));
            if ($taskIds !== []) {
                Task::query()->whereBelongsTo($user)->whereKey($taskIds)->update(['book_id' => $book->id]);
            }
            $books[$key] = true;
            $counts['books']++;
        }

        $roadmap = $user->roadmapItems()->pluck('title')->mapWithKeys(fn ($title) => [mb_strtolower($title) => true])->all();
        foreach ($backup['roadmap'] ?? [] as $row) {
            $key = mb_strtolower($row['title']);
            if (isset($roadmap[$key])) {
                $counts['skipped']++;

                continue;
            }
            $user->roadmapItems()->create([
                ...$this->except($row, ['goal_ref']),
                'goal_id' => $goalRefs[$row['goal_ref'] ?? null] ?? null,
            ]);
            $roadmap[$key] = true;
            $counts['roadmap']++;
        }

        $suggestionKey = fn (?string $key, string $source, string $title) => $key ?? "{$source}|{$title}";
        $suggestions = $user->suggestions()->get(['key', 'source', 'title'])->mapWithKeys(fn ($suggestion) => [$suggestionKey($suggestion->key, $suggestion->source->value, $suggestion->title) => true])->all();
        foreach ($backup['suggestions'] ?? [] as $row) {
            $key = $suggestionKey($row['key'] ?? null, $row['source'], $row['title']);
            if (isset($suggestions[$key])) {
                $counts['skipped']++;

                continue;
            }
            $action = $row['action'] ?? null;
            if (is_array($action)) {
                foreach (['schedule_ref' => $scheduleRefs, 'goal_ref' => $goalRefs] as $refKey => $refs) {
                    if (array_key_exists($refKey, $action)) {
                        $action = [...$this->except($action, [$refKey]), str_replace('_ref', '_id', $refKey) => $refs[$action[$refKey]] ?? null];
                    }
                }
            }
            $user->suggestions()->make([
                ...$this->except($row, ['task_ref', 'action', 'accepted_at', 'done_at', 'dismissed_at']),
                'action' => $action,
                'task_id' => $taskRefs[$row['task_ref'] ?? null] ?? null,
            ])->forceFill([
                'accepted_at' => $row['accepted_at'] ?? null,
                'done_at' => $row['done_at'] ?? null,
                'dismissed_at' => $row['dismissed_at'] ?? null,
            ])->save();
            $suggestions[$key] = true;
            $counts['suggestions']++;
            $this->touch($row['accepted_at'] ?? null, $row['done_at'] ?? null, $row['dismissed_at'] ?? null);
        }

        return $counts;
    }

    private function touch(mixed ...$moments): void
    {
        foreach ($moments as $moment) {
            if ($moment !== null) {
                $this->days[Carbon::parse($moment)->setTimezone(config('app.timezone'))->toDateString()] = true;
            }
        }
    }

    private function stamp(string $moment): int
    {
        return Carbon::parse($moment)->startOfSecond()->getTimestamp();
    }

    private function date(mixed $date): ?string
    {
        return $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
    }

    private function value(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function except(array $row, array $keys): array
    {
        return array_diff_key($row, array_flip($keys));
    }
}
