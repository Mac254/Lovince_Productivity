<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['text', 'author'])]
class Quote extends Model
{
    /**
     * Avoids repeating the quote currently on screen when there is an alternative.
     */
    public static function pickRandom(?int $excludeId = null): ?self
    {
        return static::query()
            ->when($excludeId, fn ($q, int $id) => $q->whereKeyNot($id))
            ->inRandomOrder()
            ->first()
            ?? static::query()->inRandomOrder()->first();
    }
}
