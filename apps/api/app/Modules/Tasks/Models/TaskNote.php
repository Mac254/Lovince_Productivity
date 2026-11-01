<?php

namespace App\Modules\Tasks\Models;

use App\Modules\Tasks\Database\Factories\TaskNoteFactory;
use App\Modules\Tasks\Observers\TaskNoteObserver;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a task's work log: what was done on a day, optionally with the time it took.
 */
#[ObservedBy(TaskNoteObserver::class)]
#[Fillable(['logged_on', 'body', 'minutes'])]
#[UseFactory(TaskNoteFactory::class)]
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
