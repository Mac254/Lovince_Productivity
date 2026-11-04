<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Modules\Accounting\Enums\CategoryKind;
use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Support\Money;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Sets a monthly limit on a spending category. The category and currency are fixed once set;
 * only the amount changes.
 */
class BudgetRequest extends FormRequest
{
    private ?int $amount = null;

    public function authorize(): bool
    {
        $budget = $this->route('budget');
        if ($budget instanceof Budget) {
            Gate::authorize('update', $budget);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if (! $this->isMethod('POST')) {
            return ['amount' => ['required'], 'category_id' => ['prohibited'], 'currency' => ['prohibited']];
        }

        return [
            'category_id' => [
                'required', 'integer',
                Rule::exists('accounting_categories', 'id')->where('user_id', $this->user()->id)->where('kind', CategoryKind::Expense->value)->whereNull('archived_at'),
                Rule::unique('accounting_budgets', 'category_id')->where('user_id', $this->user()->id)->where('currency', $this->input('currency')),
            ],
            'currency' => ['required', Rule::in(array_keys(Contract::CURRENCY_DECIMALS))],
            'amount' => ['required'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.exists' => 'Choose one of your spending categories.',
            'category_id.unique' => 'This category already has a budget in this currency.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $currency = $this->input('currency') ?? $this->route('budget')->currency;
            $this->amount = Money::parse($this->input('amount'), $currency);
            if ($this->amount === null) {
                $validator->errors()->add('amount', 'Enter a monthly amount above zero.');
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        return [...$this->safe()->only(['category_id', 'currency']), 'amount' => $this->amount];
    }
}
