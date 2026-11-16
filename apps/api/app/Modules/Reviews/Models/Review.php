<?php

namespace App\Modules\Reviews\Models;

use App\Modules\Reviews\Enums\ReviewPeriod;
use App\Modules\Users\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['period', 'starts_on', 'wins', 'challenges', 'next_focus', 'completed_at'])]
class Review extends Model
{
    protected $attributes = ['period' => 'week'];

    protected function casts(): array
    {
        return ['period' => ReviewPeriod::class, 'starts_on' => 'date', 'completed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeFor(Builder $query, ReviewPeriod $period, CarbonInterface $start): void
    {
        $query->where('period', $period)->whereDate('starts_on', $start->toDateString());
    }

    public function label(): string
    {
        return $this->period->label($this->starts_on);
    }
}
