<?php

namespace App\Models;

use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Observers\ActivityObserver;
use App\Observers\TaskObserver;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([ActivityObserver::class, TaskObserver::class])]
#[Fillable(['goal_id', 'task_type_id', 'title', 'description', 'status', 'priority', 'category', 'is_highlighted', 'start_date', 'due_date'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /** Relations every task response carries. */
    public const RELATIONS = ['goal:id,title,tone', 'type:id,name,tone'];

    protected $attributes = [
        'status' => 'not_started',
        'priority' => 'medium',
        'category' => 'general',
    ];

    protected static function booted(): void
    {
        static::saving(function (Task $task) {
            // A task has one type: a custom type, or a built-in category. Whichever changed last wins.
            if ($task->isDirty('category') && ! $task->isDirty('task_type_id') && $task->category !== TaskCategory::General) {
                $task->task_type_id = null;
            } elseif ($task->task_type_id !== null) {
                $task->category = TaskCategory::General;
            }

            if ($task->status === TaskStatus::Completed) {
                $task->completed_at ??= now();
            } else {
                $task->completed_at = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'category' => TaskCategory::class,
            'is_highlighted' => 'boolean',
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * @return BelongsTo<TaskType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(TaskType::class, 'task_type_id');
    }

    /**
     * @return HasMany<TaskNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(TaskNote::class);
    }

    /**
     * @return HasMany<Blocker, $this>
     */
    public function blockers(): HasMany
    {
        return $this->hasMany(Blocker::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', TaskStatus::openValues());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrderByPriority(Builder $query): void
    {
        $cases = collect(TaskPriority::cases())
            ->map(fn (TaskPriority $priority) => "WHEN '{$priority->value}' THEN {$priority->rank()}")
            ->implode(' ');

        $query->orderByRaw("CASE priority {$cases} END DESC");
    }
}
