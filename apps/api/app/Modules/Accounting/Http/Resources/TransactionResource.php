<?php

namespace App\Modules\Accounting\Http\Resources;

use App\Modules\Accounting\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ref = fn ($account) => $account === null ? null : ['id' => $account->id, 'name' => $account->name, 'type' => $account->type];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'direction' => $this->direction,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'date' => $this->date->toDateString(),
            'description' => $this->description,
            'notes' => $this->notes,
            'reference' => $this->reference,
            'account' => $ref($this->account),
            'to_account' => $ref($this->toAccount),
            'category' => $this->category === null ? null : ['id' => $this->category->id, 'name' => $this->category->name, 'kind' => $this->category->kind],
            'reversal_of_id' => $this->reversal_of_id,
            'reversed_by_id' => $this->reversal?->id,
            'recurring_transaction_id' => $this->recurring_transaction_id,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
