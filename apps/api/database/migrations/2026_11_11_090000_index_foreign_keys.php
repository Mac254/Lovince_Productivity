<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SQLite does not index a foreign key by itself, so lookups by these columns and the cascades on
 * them scanned the whole table. Tables of a removed module are skipped.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'accounting_budgets' => ['category_id'],
        'accounting_goals' => ['account_id'],
        'accounting_journal_entries' => ['transaction_id'],
        'accounting_journal_lines' => ['journal_entry_id', 'category_id'],
        'accounting_recurring_transactions' => ['account_id', 'to_account_id', 'category_id'],
        'accounting_transactions' => ['account_id', 'to_account_id', 'category_id', 'recurring_transaction_id'],
        'achievements' => ['goal_id', 'task_id'],
        'blockers' => ['task_id'],
        'books' => ['goal_id'],
        'focus_sessions' => ['task_id', 'task_note_id'],
        'roadmap_items' => ['goal_id', 'money_goal_id'],
        'schedule_items' => ['schedule_id', 'task_type_id', 'goal_id'],
        'schedules' => ['user_id'],
        'suggestions' => ['task_id'],
        'task_checklist_items' => ['user_id'],
        'tasks' => ['goal_id', 'task_type_id', 'book_id', 'next_occurrence_id'],
        'time_blocks' => ['task_id'],
        'wallpapers' => ['user_id'],
    ];

    public function up(): void
    {
        $this->each(fn (Blueprint $table, string $column) => $table->index($column));
    }

    public function down(): void
    {
        $this->each(fn (Blueprint $table, string $column) => $table->dropIndex([$column]), exists: true);
    }

    private function each(Closure $change, bool $exists = false): void
    {
        foreach (self::COLUMNS as $name => $columns) {
            if (! Schema::hasTable($name)) {
                continue;
            }
            $columns = array_filter($columns, fn (string $column) => Schema::hasColumn($name, $column)
                && Schema::hasIndex($name, $this->indexName($name, $column)) === $exists);
            if ($columns !== []) {
                Schema::table($name, fn (Blueprint $table) => array_map(fn (string $column) => $change($table, $column), $columns));
            }
        }
    }

    private function indexName(string $table, string $column): string
    {
        return strtolower("{$table}_{$column}_index");
    }
};
