<?php

namespace App\Modules\Clock\Http\Resources;

use App\Modules\Clock\Models\FocusSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FocusSession
 */
class FocusSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'started_on' => $this->started_on->toDateString(),
            'started_at' => $this->started_at->toIso8601String(),
            'ended_at' => $this->ended_at->toIso8601String(),
            'planned_minutes' => $this->planned_minutes,
            'focused_seconds' => $this->focused_seconds,
            'minutes' => $this->minutes(),
            'completed' => $this->completed,
            'task' => $this->task ? ['id' => $this->task->id, 'title' => $this->task->title] : null,
            'task_note_id' => $this->task_note_id,
        ];
    }
}
