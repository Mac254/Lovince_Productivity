<?php

namespace App\Modules\Suggestions\Http\Resources;

use App\Modules\Suggestions\Models\Suggestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Suggestion
 */
class SuggestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'title' => $this->title,
            'body' => $this->body,
            'category' => $this->category,
            'status' => $this->status,
            'action' => $this->action ?? ['type' => 'task'],
            'task' => $this->task ? ['id' => $this->task->id, 'title' => $this->task->title] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'done_at' => $this->done_at?->toIso8601String(),
            'dismissed_at' => $this->dismissed_at?->toIso8601String(),
        ];
    }
}
