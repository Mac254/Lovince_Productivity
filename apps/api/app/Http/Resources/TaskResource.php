<?php

namespace App\Http\Resources;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isOpen = in_array($this->status, TaskStatus::open(), true);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'category' => $this->category,
            'is_highlighted' => (bool) $this->is_highlighted,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_overdue' => $isOpen && $this->due_date !== null && $this->due_date->isBefore(today()),
            'task_type_id' => $this->task_type_id,
            'type' => $this->whenLoaded('type', fn () => $this->type ? [
                'id' => $this->type->id,
                'name' => $this->type->name,
                'tone' => $this->type->tone,
            ] : null),
            'goal_id' => $this->goal_id,
            'goal' => $this->whenLoaded('goal', fn () => $this->goal ? [
                'id' => $this->goal->id,
                'title' => $this->goal->title,
                'tone' => $this->goal->tone,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
