<?php

namespace App\Modules\Books\Http\Resources;

use App\Modules\Books\Models\Book;
use App\Modules\Tasks\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Book
 */
class BookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $attributes = $this->resource->getAttributes();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'status' => $this->status,
            'chapters_total' => $this->chapters_total,
            'chapters_read' => $this->chapters_read,
            'progress' => $this->progress(),
            'started_on' => $this->started_on?->toDateString(),
            'finished_on' => $this->finished_on?->toDateString(),
            'rating' => $this->rating,
            'notes' => $this->notes,
            'goal_id' => $this->goal_id,
            'goal' => $this->whenLoaded('goal', fn () => $this->goal ? [
                'id' => $this->goal->id,
                'title' => $this->goal->title,
                'tone' => $this->goal->tone,
            ] : null),
            'minutes_read' => $this->when(array_key_exists('readings_sum_minutes', $attributes), fn () => (int) $this->readings_sum_minutes),
            'last_read_on' => $this->when(array_key_exists('readings_max_read_on', $attributes), fn () => $this->readings_max_read_on ? substr((string) $this->readings_max_read_on, 0, 10) : null),
            'open_tasks' => $this->when(array_key_exists('open_tasks_count', $attributes), fn () => (int) $this->open_tasks_count),
            'readings' => BookReadingResource::collection($this->whenLoaded('readings')),
            'tasks' => $this->whenLoaded('tasks', fn () => $this->tasks->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'due_date' => $task->due_date?->toDateString(),
                'recurrence' => $task->recurrence,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
