<?php

namespace App\Modules\Accounting\Http\Resources;

use App\Modules\Accounting\Models\FinancialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinancialAccount
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'currency' => $this->currency,
            'opening_balance' => $this->opening_balance,
            'balance' => $this->balance,
            'available_balance' => $this->available_balance,
            'total_inflows' => $this->total_inflows,
            'total_outflows' => $this->total_outflows,
            'archived' => $this->archived_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
