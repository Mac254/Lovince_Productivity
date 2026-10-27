<?php

namespace App\Models;

use Database\Factories\TaskNoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a task's work log: what was done on a day, optionally with the time it took.
 */
#[Fillable(['logged_on', 'body', 'minutes'])]
class TaskNote extends Model
{
    /** @use HasFactory<TaskNoteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['logged_on' => 'date', 'minutes' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
