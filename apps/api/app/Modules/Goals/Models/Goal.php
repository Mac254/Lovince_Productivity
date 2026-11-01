<?php

namespace App\Modules\Goals\Models;

use App\Modules\Goals\Contracts\GoalProgress;
use App\Modules\Goals\Database\Factories\GoalFactory;
use App\Modules\Goals\Enums\GoalStatus;
use App\Modules\Goals\Enums\GoalTimeframe;
use App\Modules\Goals\Enums\GoalTone;
use App\Modules\Users\Models\User;
use App\Shared\Support\ActivityObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(ActivityObserver::class)]
#[Fillable(['title', 'description', 'status', 'timeframe', 'tone', 'start_date', 'target_date'])]
#[UseFactory(GoalFactory::class)]
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
     * Counts the tasks progress is measured by. Tasks own both the counting and the `tasks`
     * relation, so they provide it (see GoalProgress).
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithProgress(Builder $query): void
    {
        app(GoalProgress::class)->apply($query);
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
