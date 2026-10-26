<?php

namespace App\Models;

use App\Enums\GoalStatus;
use App\Enums\GoalTimeframe;
use App\Enums\GoalTone;
use App\Enums\TaskStatus;
use App\Observers\ActivityObserver;
use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(ActivityObserver::class)]
#[Fillable(['title', 'description', 'status', 'timeframe', 'tone', 'start_date', 'target_date'])]
class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => GoalStatus::class,
            'timeframe' => GoalTimeframe::class,
            'tone' => GoalTone::class,
            'start_date' => 'date',
            'target_date' => 'date',
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
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Cancelled tasks are excluded from progress so dropping
     * scope does not drag a goal's percentage down.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithProgress(Builder $query): void
    {
        $query->withCount([
            'tasks as eligible_tasks_count' => fn (Builder $q) => $q->where('status', '!=', TaskStatus::Cancelled),
            'tasks as completed_tasks_count' => fn (Builder $q) => $q->where('status', TaskStatus::Completed),
        ]);
    }

    public function progress(): int
    {
        $eligible = (int) ($this->eligible_tasks_count ?? 0);

        if ($eligible === 0) {
            return 0;
        }

        return (int) round(((int) $this->completed_tasks_count / $eligible) * 100);
    }
}
