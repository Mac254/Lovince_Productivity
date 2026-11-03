<?php

namespace App\Modules\Achievements\Models;

use App\Modules\Achievements\Database\Factories\AchievementFactory;
use App\Modules\Goals\Models\Goal;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use App\Shared\Support\ActivityObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(ActivityObserver::class)]
#[Fillable(['goal_id', 'task_id', 'title', 'description', 'achieved_on', 'category'])]
#[UseFactory(AchievementFactory::class)]
class Achievement extends Model
{
    /** @use HasFactory<AchievementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'achieved_on' => 'date',
            'category' => SuggestionCategory::class,
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
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
