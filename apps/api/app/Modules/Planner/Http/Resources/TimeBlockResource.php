<?php

namespace App\Modules\Planner\Http\Resources;

use App\Modules\Planner\Models\TimeBlock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TimeBlock
 */
class TimeBlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date->toDateString(),
            'start_minute' => $this->start_minute,
            'minutes' => $this->minutes,
            'title' => $this->task->title ?? $this->title,
            'task' => $this->task ? [
                'id' => $this->task->id,
                'title' => $this->task->title,
                'status' => $this->task->status,
                'priority' => $this->task->priority,
            ] : null,
        ];
    }
}
