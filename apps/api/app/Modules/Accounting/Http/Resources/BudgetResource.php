<?php

namespace App\Modules\Accounting\Http\Resources;

use App\Modules\Accounting\Models\Budget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A budget with what has been spent against it in the month asked for, which the controller
 * sets as `spent`.
 *
 * @mixin Budget
 */
class BudgetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => ['id' => $this->category->id, 'name' => $this->category->name, 'archived' => $this->category->archived_at !== null],
            'amount' => $this->amount,
            'currency' => $this->currency,
            'spent' => $spent = max(0, (int) $this->spent),
            'remaining' => $this->amount - $spent,
        ];
    }
}
