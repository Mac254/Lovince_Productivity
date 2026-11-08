<?php

namespace App\Modules\Roadmap\Models;

use App\Modules\Roadmap\Database\Factories\RoadmapItemFactory;
use App\Modules\Roadmap\Enums\RoadmapStage;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'details', 'area', 'stage', 'position', 'shipped_on'])]
#[UseFactory(RoadmapItemFactory::class)]
class RoadmapItem extends Model
{
    /** @use HasFactory<RoadmapItemFactory> */
    use HasFactory;

    protected $attributes = [
        'stage' => 'next',
        'position' => 0,
    ];

    protected static function booted(): void
    {
        static::saving(function (RoadmapItem $item) {
            if ((! $item->exists || $item->isDirty('stage')) && ! $item->isDirty('position')) {
                $item->position = (int) static::query()
                    ->where('user_id', $item->user_id)
                    ->where('stage', $item->stage)
                    ->max('position') + 1;
            }

            if ($item->stage === RoadmapStage::Shipped) {
                $item->shipped_on ??= today();
            } else {
                $item->shipped_on = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'stage' => RoadmapStage::class,
            'position' => 'integer',
            'shipped_on' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
