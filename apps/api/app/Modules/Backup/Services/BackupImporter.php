<?php

namespace App\Modules\Backup\Services;

use App\Modules\Settings\Services\SettingsRegistry;
use App\Modules\Users\Models\User;
use App\Support\Contract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BackupImporter
{
    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * Adds a backup's records to the account. Task types are matched by name, and days that already
     * have a journal entry or weekly review keep what is there.
     *
     * @param  array<string, mixed>  $backup
     * @return array<string, int>
     */
    public function import(User $user, array $backup): array
    {
        return DB::transaction(fn () => Model::withoutEvents(fn () => $this->run($user, $backup)));
    }

    /**
     * @param  array<string, mixed>  $backup
     * @return array<string, int>
     */
    private function run(User $user, array $backup): array
    {
        $counts = array_fill_keys(['task_types', 'goals', 'tasks', 'blockers', 'journal', 'focus_sessions', 'weekly_reviews', 'time_blocks', 'skipped'], 0);

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

        $goalRefs = [];
        foreach ($backup['goals'] ?? [] as $row) {
            $goalRefs[$row['ref']] = $user->goals()->create($this->except($row, ['ref']))->id;
            $counts['goals']++;
        }

        $taskRefs = [];
        foreach ($backup['tasks'] ?? [] as $row) {
            $task = $user->tasks()->make($this->except($row, ['ref', 'goal_ref', 'task_type_ref', 'notes', 'checklist', 'completed_at', 'created_at']));
            $task->forceFill([
                'goal_id' => $goalRefs[$row['goal_ref'] ?? null] ?? null,
                'task_type_id' => $typeRefs[$row['task_type_ref'] ?? null] ?? null,
                'completed_at' => $row['completed_at'] ?? null,
                'created_at' => $row['created_at'] ?? now(),
            ])->save();
            $taskRefs[$row['ref']] = $task->id;
            $counts['tasks']++;

            foreach ($row['notes'] ?? [] as $note) {
                $task->notes()->make($note)->forceFill(['user_id' => $user->id])->save();
            }
            foreach (array_values($row['checklist'] ?? []) as $position => $item) {
                $task->checklistItems()->make([...$item, 'done' => $item['done'] ?? false, 'position' => $position])->forceFill(['user_id' => $user->id])->save();
            }
        }

        foreach ($backup['blockers'] ?? [] as $row) {
            $user->blockers()->make($this->except($row, ['task_ref', 'resolved_at']))->forceFill([
                'task_id' => $taskRefs[$row['task_ref'] ?? null] ?? null,
                'resolved_at' => $row['resolved_at'] ?? null,
            ])->save();
            $counts['blockers']++;
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
        }

        foreach ($backup['focus_sessions'] ?? [] as $row) {
            $user->focusSessions()->make($this->except($row, ['task_ref']))->forceFill([
                'task_id' => $taskRefs[$row['task_ref'] ?? null] ?? null,
                'completed' => $row['completed'] ?? false,
            ])->save();
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

        foreach ($backup['time_blocks'] ?? [] as $row) {
            $taskId = $taskRefs[$row['task_ref'] ?? null] ?? null;
            if (24 * 60 < $row['start_minute'] + $row['minutes'] || ($taskId === null && blank($row['title'] ?? null))) {
                $counts['skipped']++;

                continue;
            }
            $user->timeBlocks()->create([...$this->except($row, ['task_ref']), 'task_id' => $taskId]);
            $counts['time_blocks']++;
        }

        return $counts;
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
