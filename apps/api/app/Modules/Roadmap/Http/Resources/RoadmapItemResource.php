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
            'shipped_on' => $this->shipped_on?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
