<?php

namespace App\Modules\Planner\Models;

use App\Modules\Planner\Database\Factories\TimeBlockFactory;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'title', 'date', 'start_minute', 'minutes'])]
#[UseFactory(TimeBlockFactory::class)]
class TimeBlock extends Model
{
    /** @use HasFactory<TimeBlockFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['date' => 'date', 'start_minute' => 'integer', 'minutes' => 'integer'];
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
