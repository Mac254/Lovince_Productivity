<?php

namespace App\Modules\Achievements\Http\Resources;

use App\Modules\Achievements\Models\Achievement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Achievement
 */
class AchievementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'achieved_on' => $this->achieved_on->toDateString(),
            'category' => $this->category,
            'goal_id' => $this->goal_id,
            'goal' => $this->whenLoaded('goal', fn () => $this->goal ? [
                'id' => $this->goal->id,
                'title' => $this->goal->title,
                'tone' => $this->goal->tone,
            ] : null),
            'task_id' => $this->task_id,
            'task' => $this->whenLoaded('task', fn () => $this->task ? ['id' => $this->task->id, 'title' => $this->task->title] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
