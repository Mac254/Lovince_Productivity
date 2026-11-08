<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Modules\Accounting\Enums\GoalKind;
use App\Modules\Accounting\Models\FinancialAccount;
use App\Modules\Accounting\Models\FinancialGoal;
use App\Modules\Accounting\Support\Money;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creates or changes a goal. A linked account must be the user's own, open and in the goal's
 * currency; a goal with recorded contributions keeps its currency and stays unlinked.
 */
class GoalRequest extends FormRequest
{
    /** @var array<string, mixed> */
    private array $resolved = [];

    public function authorize(): bool
    {
        $goal = $this->route('goal');
        if ($goal instanceof FinancialGoal) {
            Gate::authorize('update', $goal);
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
            'name' => [$presence, 'string', 'min:1', 'max:'.Contract::ACCOUNTING_GOAL_NAME_MAX],
            'kind' => [$presence, Rule::enum(GoalKind::class)],
            'currency' => [$presence, Rule::in(array_keys(Contract::CURRENCY_DECIMALS))],
            'target_amount' => [$presence],
            'target_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'account_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('accounting_accounts', 'id')->where('user_id', $this->user()->id),
            ],
            'archived' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['account_id.exists' => 'Choose one of your accounts.'];
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
        /** @var FinancialGoal|null $goal */
        $goal = $this->route('goal');
        $currency = $this->input('currency', $goal?->currency);
        $accountId = $this->has('account_id') ? $this->input('account_id') : $goal?->account_id;
        $accountId = $accountId === null ? null : (int) $accountId;
        $contributed = $goal !== null && $goal->contributions()->exists();

        if ($goal !== null && $currency !== $goal->currency && $contributed) {
            $validator->errors()->add('currency', 'The currency can’t change once money is recorded against the goal.');

            return;
        }
        if ($accountId !== null) {
            $account = FinancialAccount::query()->find($accountId);
            if ($account->archived_at !== null && $accountId !== $goal?->account_id) {
                $validator->errors()->add('account_id', 'This account is archived.');

                return;
            }
            if ($account->currency !== $currency) {
                $validator->errors()->add('account_id', "Choose an account in {$currency}.");

                return;
            }
            if ($contributed && $goal->account_id === null) {
                $validator->errors()->add('account_id', 'Remove the money recorded against this goal before linking an account.');

                return;
            }
        }

        $resolved = $this->safe()->only(['name', 'kind', 'currency', 'account_id']);
        if ($this->has('target_amount')) {
            $target = Money::parse($this->input('target_amount'), $currency);
            if ($target === null) {
                $validator->errors()->add('target_amount', 'Enter a target above zero.');

                return;
            }
            $resolved['target_amount'] = $target;
        } elseif ($goal !== null && $currency !== $goal->currency) {
            $validator->errors()->add('target_amount', 'Enter the target in the new currency.');

            return;
        }
        if ($this->has('target_date')) {
            $date = $this->input('target_date');
            if ($date !== null && $date !== $goal?->target_date?->toDateString() && $date < today()->toDateString()) {
                $validator->errors()->add('target_date', 'Pick today or a later date.');

                return;
            }
            $resolved['target_date'] = $date;
        }
        if ($this->has('archived')) {
            $resolved['archived_at'] = $this->boolean('archived') ? ($goal?->archived_at ?? now()) : null;
        }

        $this->resolved = $resolved;
    }
}
