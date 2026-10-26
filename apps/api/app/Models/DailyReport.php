<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable-in-spirit snapshot of one day, regenerated only for the current day.
 */
#[Fillable([
    'date', 'completed_count', 'created_count', 'due_count', 'due_completed_count',
    'overdue_count', 'open_blockers_count', 'data', 'generated_at',
])]
class DailyReport extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'data' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
