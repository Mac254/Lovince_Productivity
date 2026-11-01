<?php

namespace App\Modules\Clock\Models;

use App\Modules\Clock\Database\Factories\FocusSessionFactory;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['started_on', 'started_at', 'ended_at', 'planned_minutes', 'focused_seconds', 'completed'])]
#[UseFactory(FocusSessionFactory::class)]
class FocusSession extends Model
{
    /** @use HasFactory<FocusSessionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'planned_minutes' => 'integer',
            'focused_seconds' => 'integer',
            'completed' => 'boolean',
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
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function minutes(): int
    {
        return (int) round($this->focused_seconds / 60);
    }
}
