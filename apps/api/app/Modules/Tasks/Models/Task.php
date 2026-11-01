<?php

namespace App\Modules\Tasks\Models;

use App\Modules\Goals\Models\Goal;
use App\Modules\Tasks\Database\Factories\TaskFactory;
use App\Modules\Tasks\Enums\TaskCategory;
use App\Modules\Tasks\Enums\TaskPriority;
use App\Modules\Tasks\Enums\TaskRecurrence;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Observers\TaskObserver;
use App\Modules\Users\Models\User;
use App\Shared\Support\ActivityObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([ActivityObserver::class, TaskObserver::class])]
#[Fillable(['goal_id', 'task_type_id', 'title', 'description', 'status', 'priority', 'category', 'is_highlighted', 'start_date', 'due_date', 'estimate_minutes', 'recurrence', 'cover'])]
#[UseFactory(TaskFactory::class)]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /** Relations every task response carries. */
    public const RELATIONS = ['goal:id,title,tone', 'type:id,name,tone'];

    public const COVER_DISK = 'local';

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

            if ($task->isDirty('cover') && $task->cover !== null && $task->cover_path !== null) {
                $task->removeUploadedCover();
                $task->cover_path = null;
            }

            if ($task->status === TaskStatus::Completed) {
                $task->completed_at ??= now();
            } else {
                $task->completed_at = null;
            }
        });

        static::deleted(fn (Task $task) => $task->removeUploadedCover());
    }

    public function removeUploadedCover(): void
    {
        $path = $this->getOriginal('cover_path') ?? $this->cover_path;
        if ($path) {
            Storage::disk(self::COVER_DISK)->delete($path);
        }
    }

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'category' => TaskCategory::class,
            'is_highlighted' => 'boolean',
            'estimate_minutes' => 'integer',
            'recurrence' => TaskRecurrence::class,
            'next_occurrence_id' => 'integer',
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
     * @return HasMany<ChecklistItem, $this>
     */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeWithChecklistCounts(Builder $query): void
    {
        $query->withCount(self::checklistCounts());
    }

    public function loadChecklistCounts(): static
    {
        return $this->loadCount(self::checklistCounts());
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function checklistCounts(): array
    {
        return ['checklistItems as checklist_total', 'checklistItems as checklist_done' => fn (Builder $q) => $q->where('done', true)];
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
