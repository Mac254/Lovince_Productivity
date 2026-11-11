<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Models\FinancialAccount;
use App\Modules\Accounting\Services\Ledger;
use App\Modules\Accounting\Support\Money;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AccountRequest extends FormRequest
{
    /** @var array<string, mixed> */
    private array $resolved = [];

    public function authorize(): bool
    {
        $account = $this->route('account');
        if ($account instanceof FinancialAccount) {
            Gate::authorize('update', $account);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$presence, 'string', 'min:1', 'max:'.Contract::ACCOUNTING_ACCOUNT_NAME_MAX],
            'type' => [$presence, Rule::enum(AccountType::class)],
            'currency' => [$presence, Rule::in(array_keys(Contract::CURRENCY_DECIMALS))],
            'opening_balance' => ['sometimes', 'nullable'],
            'archived' => ['sometimes', 'boolean'],
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
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        return $this->resolved;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper(trim($this->input('currency')))]);
        }
    }

    private function resolve(Validator $validator): void
    {
        /** @var FinancialAccount|null $account */
        $account = $this->route('account');
        $currency = $this->input('currency', $account?->currency);

        if ($account !== null && $currency !== $account->currency && ($account->hasActivity() || $account->hasLinkedGoals())) {
            $validator->errors()->add('currency', 'The currency can’t change once the account has transactions, recurring payments or a goal.');

            return;
        }

        $resolved = $this->safe()->only(['name', 'type', 'currency']);
        if ($this->has('opening_balance')) {
            $opening = Money::parseSigned($this->input('opening_balance') ?? '0', $currency);
            if ($opening === null) {
                $validator->errors()->add('opening_balance', 'Enter an amount like 1500.50.');

                return;
            }
            if ($account !== null && $opening !== $account->opening_balance && $account->hasReconciled()) {
                $validator->errors()->add('opening_balance', 'The opening balance can’t change once transactions in this account are reconciled.');

                return;
            }
            $resolved['opening_balance'] = $opening;
        } elseif ($account !== null && $currency !== $account->currency) {
            $resolved['opening_balance'] = 0;
        }
        if ($account !== null && $account->archived_at === null && $this->boolean('archived')) {
            $balance = app(Ledger::class)->balances(collect([clone $account]))->first();
            if ($balance->balance !== 0 || $balance->available_balance !== 0) {
                $validator->errors()->add('archived', 'Move the money out or record pending payments first: only an account at zero can be archived.');

                return;
            }
        }
        if ($this->has('archived')) {
            $resolved['archived_at'] = $this->boolean('archived') ? ($account?->archived_at ?? now()) : null;
        }

        $this->resolved = $resolved;
    }
}
