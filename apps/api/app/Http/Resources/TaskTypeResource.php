<?php

namespace App\Http\Resources;

use App\Models\TaskType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskType
 */
class TaskTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tone' => $this->tone,
            'tasks_count' => $this->whenCounted('tasks'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
