<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Modules\Accounting\Enums\TransactionStatus;
use App\Modules\Accounting\Enums\TransactionType;
use App\Modules\Accounting\Models\Transaction;
use App\Support\Contract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTransactionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:'.Contract::SEARCH_MAX],
            'type' => ['sometimes', 'nullable', Rule::enum(TransactionType::class)],
            'status' => ['sometimes', 'nullable', Rule::enum(TransactionStatus::class)],
            'account_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'currency' => ['sometimes', 'nullable', Rule::in(array_keys(Contract::CURRENCY_DECIMALS))],
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.Contract::PER_PAGE_MAX],
        ];
    }

    /**
     * The user's transactions matching the filters, newest first.
     *
     * @return Builder<Transaction>
     */
    public function transactions(): Builder
    {
        $filters = $this->validated();

        return Transaction::query()
            ->whereBelongsTo($this->user())
            ->with(Transaction::RELATIONS)
            ->when($filters['search'] ?? null, fn (Builder $q, string $search) => $q->where(fn (Builder $q) => $q
                ->where('description', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%")
                ->orWhere('notes', 'like', "%{$search}%")))
            ->when($filters['type'] ?? null, fn (Builder $q, string $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filters['account_id'] ?? null, fn (Builder $q, int|string $id) => $q->where(fn (Builder $q) => $q->where('account_id', $id)->orWhere('to_account_id', $id)))
            ->when($filters['category_id'] ?? null, fn (Builder $q, int|string $id) => $q->where('category_id', $id))
            ->when($filters['currency'] ?? null, fn (Builder $q, string $currency) => $q->where('currency', $currency))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->whereDate('date', '<=', $to))
            ->orderByDesc('date')
            ->orderByDesc('id');
    }
}
