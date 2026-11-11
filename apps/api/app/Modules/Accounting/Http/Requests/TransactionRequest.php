<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Modules\Accounting\Enums\TransactionDirection;
use App\Modules\Accounting\Enums\TransactionStatus;
use App\Modules\Accounting\Enums\TransactionType;
use App\Modules\Accounting\Models\Category;
use App\Modules\Accounting\Models\FinancialAccount;
use App\Modules\Accounting\Models\RecurringTransaction;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Support\Money;
use App\Support\Contract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creates or edits a transaction. The amount arrives as a decimal string and is converted to
 * minor units in the account's currency here; the currency and direction are never taken from
 * the client except for an adjustment's direction.
 */
class TransactionRequest extends FormRequest
{
    /** @var array<string, mixed> */
    private array $resolved = [];

    public function authorize(): bool
    {
        $current = $this->current();
        if ($current !== null) {
            Gate::authorize('update', $current);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $presence = $creating ? 'required' : 'sometimes';
        $owned = fn (string $table) => Rule::exists($table, 'id')->where('user_id', $this->user()->id);

        return [
            'idempotency_key' => $creating ? ['required', 'string', 'regex:/^[A-Za-z0-9_-]{8,64}$/'] : ['prohibited'],
            'base_updated_at' => $creating ? ['prohibited'] : ['sometimes', 'date'],
            'type' => [$presence, Rule::enum(TransactionType::class)],
            'account_id' => [$presence, 'integer', $owned('accounting_accounts')],
            'to_account_id' => ['sometimes', 'nullable', 'integer', $owned('accounting_accounts')],
            'category_id' => ['sometimes', 'nullable', 'integer', $owned('accounting_categories')],
            'direction' => ['sometimes', 'nullable', Rule::enum(TransactionDirection::class)],
            'amount' => [$presence],
            'status' => ['sometimes', Rule::in([TransactionStatus::Pending->value, TransactionStatus::Posted->value, ...($creating ? [] : [TransactionStatus::Reconciled->value])])],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'description' => [$presence, 'string', 'min:1', 'max:'.Contract::ACCOUNTING_DESCRIPTION_MAX],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.Contract::ACCOUNTING_NOTES_MAX],
            'reference' => ['sometimes', 'nullable', 'string', 'max:'.Contract::ACCOUNTING_REFERENCE_MAX],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $validator->errors()->isEmpty() && $this->resolve($validator)];
    }

    /**
     * The transaction's fields as they should be stored.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        return $this->resolved;
    }

    /**
     * The record being edited, or null when creating one.
     */
    protected function current(): ?Model
    {
        $transaction = $this->route('transaction');

        return $transaction instanceof Transaction ? $transaction : null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->isMethod('POST')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
        foreach (['description', 'notes', 'reference'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    /**
     * Adds when the money moves and its status, or reports why they can't stand.
     *
     * @param  array<string, mixed>  $resolved
     * @return array<string, mixed>|null
     */
    protected function timing(array $resolved, ?Model $current, Validator $validator): ?array
    {
        if ($current === null || $this->has('date')) {
            $resolved['date'] = $this->input('date', today()->toDateString());
        }
        if ($current === null || $this->has('status')) {
            $resolved['status'] = TransactionStatus::from($this->input('status', TransactionStatus::Posted->value));
        }

        $date = $resolved['date'] ?? $current->date->toDateString();
        $status = $resolved['status'] ?? $current->status;
        if (($current === null || $this->has('date') || $this->has('status')) && $status->isPosted() && $date > today()->toDateString()) {
            $validator->errors()->add('date', 'Money that hasn’t moved yet can only be pending. Post it on the day it clears.');

            return null;
        }

        return $resolved;
    }

    private function resolve(Validator $validator): void
    {
        /** @var Transaction|RecurringTransaction|null $current */
        $current = $this->current();
        $pick = fn (string $field, mixed $fallback = null) => $this->has($field) ? $this->input($field) : ($current?->getAttribute($field) ?? $fallback);

        $type = TransactionType::from($this->input('type') ?? $current->type->value);
        $account = FinancialAccount::query()->whereBelongsTo($this->user())->find($pick('account_id'));
        if ($account === null) {
            $validator->errors()->add('account_id', 'Choose one of your accounts.');

            return;
        }
        if ($account->archived_at !== null && $account->id !== $current?->account_id) {
            $validator->errors()->add('account_id', 'This account is archived.');
        }

        $toAccountId = $type === TransactionType::Transfer ? $pick('to_account_id') : null;
        if ($type === TransactionType::Transfer) {
            $toAccount = FinancialAccount::query()->whereBelongsTo($this->user())->find($toAccountId);
            if ($toAccount === null) {
                $validator->errors()->add('to_account_id', 'Choose the account the money goes to.');
            } elseif ($toAccount->id === $account->id) {
                $validator->errors()->add('to_account_id', 'Choose a different account to transfer to.');
            } elseif ($toAccount->currency !== $account->currency) {
                $validator->errors()->add('to_account_id', 'Transfers between currencies are not supported yet.');
            } elseif ($toAccount->archived_at !== null && $toAccount->id !== $current?->to_account_id) {
                $validator->errors()->add('to_account_id', 'This account is archived.');
            }
        }

        $categoryId = $type->categoryKind() === null ? null : $pick('category_id');
        if ($categoryId !== null) {
            $category = Category::query()->whereBelongsTo($this->user())->find($categoryId);
            if ($category === null || $category->kind !== $type->categoryKind()) {
                $validator->errors()->add('category_id', "Choose a {$type->categoryKind()->value} category.");
            } elseif ($category->archived_at !== null && $category->id !== $current?->category_id) {
                $validator->errors()->add('category_id', 'This category is archived.');
            }
        }

        $direction = $type->direction()
            ?? TransactionDirection::tryFrom((string) ($this->input('direction') ?? $current?->direction?->value));
        if ($direction === null) {
            $validator->errors()->add('direction', 'Say whether the adjustment adds or removes money.');
        }

        $amount = $this->has('amount')
            ? Money::parse($this->input('amount'), $account->currency)
            : ($current->currency === $account->currency ? $current->amount : null);
        if ($amount === null) {
            $decimals = Money::decimals($account->currency);
            $validator->errors()->add('amount', $decimals > 0
                ? "Enter an amount above zero with up to {$decimals} decimal places."
                : "Enter a whole amount above zero for {$account->currency}.");
        }

        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $resolved = [
            'type' => $type,
            'direction' => $direction,
            'account_id' => $account->id,
            'to_account_id' => $toAccountId === null ? null : (int) $toAccountId,
            'category_id' => $categoryId === null ? null : (int) $categoryId,
            'amount' => $amount,
            'currency' => $account->currency,
        ];
        foreach (['description', 'notes', 'reference'] as $field) {
            if ($this->has($field)) {
                $resolved[$field] = $this->input($field);
            }
        }

        $resolved = $this->timing($resolved, $current, $validator);
        if ($resolved === null) {
            return;
        }

        $this->resolved = $resolved;
    }
}
