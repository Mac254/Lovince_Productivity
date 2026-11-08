<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Modules\Accounting\Models\FinancialGoal;
use App\Modules\Accounting\Support\Money;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Money put towards a goal without an account, or taken back out (`withdraw`). The amount is
 * always positive; taking out more than has been saved is refused.
 */
class GoalContributionRequest extends FormRequest
{
    private ?int $amount = null;

    public function authorize(): bool
    {
        Gate::authorize('update', $this->goal());

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required'],
            'withdraw' => ['sometimes', 'boolean'],
            'date' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:today'],
            'note' => ['sometimes', 'nullable', 'string', 'max:'.Contract::ACCOUNTING_DESCRIPTION_MAX],
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
            $goal = $this->goal();
            if ($goal->account_id !== null) {
                $validator->errors()->add('amount', 'This goal follows its account’s balance: record a transfer to that account instead.');

                return;
            }
            $amount = Money::parse($this->input('amount'), $goal->currency);
            if ($amount === null) {
                $validator->errors()->add('amount', 'Enter an amount above zero.');

                return;
            }
            if ($this->boolean('withdraw')) {
                $saved = (int) $goal->contributions()->sum('amount');
                if ($amount > $saved) {
                    $validator->errors()->add('amount', 'You can take out at most '.Money::format($saved, $goal->currency).'.');

                    return;
                }
                $amount = -$amount;
            }
            $this->amount = $amount;
        }];
    }

    /**
     * @return array{amount: int, date: string, note: string|null}
     */
    public function fields(): array
    {
        $note = $this->input('note');

        return [
            'amount' => (int) $this->amount,
            'date' => $this->input('date', today()->toDateString()),
            'note' => is_string($note) && trim($note) !== '' ? trim($note) : null,
        ];
    }

    private function goal(): FinancialGoal
    {
        return $this->route('goal');
    }
}
