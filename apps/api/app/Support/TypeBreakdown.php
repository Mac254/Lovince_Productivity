<?php

namespace App\Support;

use App\Enums\TaskCategory;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Counts tasks per type the way the UI shows them: the user's own types first (by name),
 * then the built-in categories for tasks without one. Rows with nothing in them are left out.
 */
final class TypeBreakdown
{
    /**
     * @param  Collection<int, Task>  $completed
     * @param  Collection<int, Task>  $open
     * @return list<array{key: string, label: string, type_id: int|null, category: string|null, tone: string|null, completed: int, open: int}>
     */
    public static function rows(User $user, Collection $completed, Collection $open): array
    {
        $count = fn (Collection $tasks, callable $match): int => $tasks->filter($match)->count();

        $custom = TaskType::query()->whereBelongsTo($user)->orderBy('name')->get()
            ->map(fn (TaskType $type) => [
                'key' => "type:{$type->id}",
                'label' => $type->name,
                'type_id' => $type->id,
                'category' => null,
                'tone' => $type->tone->value,
                'completed' => $count($completed, fn (Task $t) => $t->task_type_id === $type->id),
                'open' => $count($open, fn (Task $t) => $t->task_type_id === $type->id),
            ]);

        $builtIn = collect(TaskCategory::cases())->map(fn (TaskCategory $category) => [
            'key' => "category:{$category->value}",
            'label' => ucfirst($category->value),
            'type_id' => null,
            'category' => $category->value,
            'tone' => null,
            'completed' => $count($completed, fn (Task $t) => $t->task_type_id === null && $t->category === $category),
            'open' => $count($open, fn (Task $t) => $t->task_type_id === null && $t->category === $category),
        ]);

        return $custom->concat($builtIn)
            ->filter(fn (array $row) => $row['completed'] + $row['open'] > 0)
            ->values()
            ->all();
    }
}
