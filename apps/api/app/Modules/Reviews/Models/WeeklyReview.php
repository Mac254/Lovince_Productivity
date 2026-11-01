<?php

namespace App\Modules\Reviews\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['week_start', 'wins', 'challenges', 'next_focus', 'completed_at'])]
class WeeklyReview extends Model
{
    protected function casts(): array
    {
        return ['week_start' => 'date', 'completed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
