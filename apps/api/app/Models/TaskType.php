<?php

namespace App\Models;

use App\Enums\GoalTone;
use Database\Factories\TaskTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user's own kind of task (Study, Personal, Review…). Deleting one keeps its tasks.
 */
#[Fillable(['name', 'tone'])]
class TaskType extends Model
{
    /** @use HasFactory<TaskTypeFactory> */
    use HasFactory;

    protected $attributes = ['tone' => 'blue'];

    protected function casts(): array
    {
        return ['tone' => GoalTone::class];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
