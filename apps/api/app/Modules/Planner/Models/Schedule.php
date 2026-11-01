<?php

namespace App\Modules\Planner\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'days'])]
class Schedule extends Model
{
    protected function casts(): array
    {
        return ['days' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ScheduleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ScheduleItem::class)->orderBy('start_minute');
    }
}
