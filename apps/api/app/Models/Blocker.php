<?php

namespace App\Models;

use App\Enums\BlockerStatus;
use App\Observers\ActivityObserver;
use Database\Factories\BlockerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(ActivityObserver::class)]
#[Fillable(['task_id', 'description', 'status'])]
class Blocker extends Model
{
    /** @use HasFactory<BlockerFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'open'];

    protected static function booted(): void
    {
        static::saving(function (Blocker $blocker) {
            $blocker->resolved_at = $blocker->status === BlockerStatus::Resolved
                ? ($blocker->resolved_at ?? now())
                : null;
        });
    }

    protected function casts(): array
    {
        return [
            'status' => BlockerStatus::class,
            'resolved_at' => 'datetime',
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

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', BlockerStatus::Open);
    }
}
