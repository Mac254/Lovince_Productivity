<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * A date range in the account's time zone, the current month by default, and one currency.
 */
class PeriodRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
            'currency' => ['sometimes', 'nullable', Rule::in(array_keys(Contract::CURRENCY_DECIMALS))],
        ];
    }

    public function from(): Carbon
    {
        return $this->has('from') ? Carbon::createFromFormat('Y-m-d', $this->input('from'))->startOfDay() : today()->startOfMonth();
    }

    public function to(): Carbon
    {
        return $this->has('to') ? Carbon::createFromFormat('Y-m-d', $this->input('to'))->startOfDay() : today()->endOfMonth()->startOfDay();
    }
}
