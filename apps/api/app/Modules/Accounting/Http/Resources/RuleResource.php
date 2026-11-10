<?php

namespace App\Modules\Accounting\Http\Resources;

use App\Modules\Accounting\Models\MoneyRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MoneyRule
 */
class RuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'reason' => $this->reason,
            'pinned' => $this->pinned,
            'position' => $this->position,
            'suggestion' => $this->suggestion,
            'archived' => $this->archived_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
