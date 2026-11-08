<?php

namespace App\Modules\Accounting\Http\Resources;

use App\Modules\Accounting\Models\FinancialGoal;
use App\Modules\Accounting\Services\GoalProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A goal with what has been saved towards it, which `GoalProgress` sets as `saved`.
 *
 * @mixin FinancialGoal
 */
class GoalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $saved = (int) $this->saved;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'kind' => $this->kind,
            'target_amount' => $this->target_amount,
            'currency' => $this->currency,
            'target_date' => $this->target_date?->toDateString(),
            'account' => $this->account === null ? null : [
                'id' => $this->account->id,
                'name' => $this->account->name,
                'type' => $this->account->type,
            ],
            'saved' => $saved,
            'remaining' => max(0, $this->target_amount - $saved),
            'monthly_needed' => GoalProgress::monthlyNeeded($this->resource),
            'achieved' => $saved >= $this->target_amount,
            'archived' => $this->archived_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
