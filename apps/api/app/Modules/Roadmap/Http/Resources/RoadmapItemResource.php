<?php

namespace App\Modules\Roadmap\Http\Resources;

use App\Modules\Roadmap\Models\RoadmapItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RoadmapItem */
class RoadmapItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'details' => $this->details,
            'area' => $this->area,
            'stage' => $this->stage->value,
            'position' => $this->position,
            'done_on' => $this->done_on?->toDateString(),
            'goal_id' => $this->goal_id,
            'goal' => $this->whenLoaded('goal', fn () => $this->goal ? ['id' => $this->goal->id, 'title' => $this->goal->title, 'tone' => $this->goal->tone] : null),
            'money_goal_id' => $this->money_goal_id,
            'money_goal' => $this->whenLoaded('moneyGoal', fn () => $this->moneyGoal ? [
                'id' => $this->moneyGoal->id,
                'name' => $this->moneyGoal->name,
                'currency' => $this->moneyGoal->currency,
                'saved' => (int) $this->moneyGoal->saved,
                'target_amount' => $this->moneyGoal->target_amount,
            ] : null),
            'target_amount' => $this->target_amount,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'milestones' => $this->whenLoaded('milestones', fn () => $this->milestones->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'due_date' => $task->due_date?->toDateString(),
                'recurrence' => $task->recurrence,
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
