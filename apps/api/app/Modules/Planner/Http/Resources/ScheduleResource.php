<?php

namespace App\Modules\Planner\Http\Resources;

use App\Modules\Planner\Models\Schedule;
use App\Modules\Planner\Models\ScheduleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Schedule
 */
class ScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'days' => $this->days ?? [],
            'items' => $this->items->map(fn (ScheduleItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'task_type_id' => $item->task_type_id,
                'goal_id' => $item->goal_id,
                'start_minute' => $item->start_minute,
                'minutes' => $item->minutes,
            ])->values(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
