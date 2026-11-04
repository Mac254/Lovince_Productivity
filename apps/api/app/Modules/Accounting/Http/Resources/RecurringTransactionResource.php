<?php

namespace App\Modules\Accounting\Http\Resources;

use App\Modules\Accounting\Models\RecurringTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RecurringTransaction
 */
class RecurringTransactionResource extends JsonResource
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
            'description' => $this->description,
            'notes' => $this->notes,
            'reference' => $this->reference,
            'account' => $ref($this->account),
            'to_account' => $ref($this->toAccount),
            'category' => $this->category === null ? null : ['id' => $this->category->id, 'name' => $this->category->name, 'kind' => $this->category->kind],
            'frequency' => $this->frequency,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'next_date' => $this->next_date?->toDateString(),
            'occurrences' => $this->occurrences,
            'paused' => $this->paused_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
