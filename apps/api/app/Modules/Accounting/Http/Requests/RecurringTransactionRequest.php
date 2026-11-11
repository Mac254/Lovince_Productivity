<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Modules\Accounting\Enums\Frequency;
use App\Modules\Accounting\Enums\TransactionStatus;
use App\Modules\Accounting\Models\RecurringTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creates or edits a recurring payment. The money is checked exactly as a transaction's; in place
 * of one date it takes a schedule: how often, the first payment and, optionally, the last.
 */
class RecurringTransactionRequest extends TransactionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            ...Arr::except(parent::rules(), ['idempotency_key', 'base_updated_at', 'date']),
            'type' => [$presence, Rule::in(array_map(fn ($type) => $type->value, RecurringTransaction::TYPES))],
            'status' => ['sometimes', Rule::in([TransactionStatus::Pending->value, TransactionStatus::Posted->value])],
            'frequency' => [$presence, Rule::enum(Frequency::class)],
            'start_date' => [$presence, 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'paused' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Whether the schedule changed, so the next due date has to be worked out again.
     */
    public function reschedules(): bool
    {
        return $this->isMethod('POST') || $this->hasAny(['frequency', 'start_date', 'end_date']);
    }

    protected function current(): ?Model
    {
        $recurring = $this->route('recurring');

        return $recurring instanceof RecurringTransaction ? $recurring : null;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->offsetUnset('idempotency_key');
    }

    /**
     * @param  array<string, mixed>  $resolved
     * @return array<string, mixed>|null
     */
    protected function timing(array $resolved, ?Model $current, Validator $validator): ?array
    {
        /** @var RecurringTransaction|null $current */
        if ($current === null || $this->has('status')) {
            $resolved['status'] = TransactionStatus::from($this->input('status', TransactionStatus::Posted->value));
        }
        foreach (['start_date', 'end_date'] as $field) {
            if ($this->has($field)) {
                $resolved[$field] = $this->input($field);
            }
        }
        if ($this->has('frequency')) {
            $resolved['frequency'] = Frequency::from($this->input('frequency'));
        }
        if ($this->has('paused')) {
            $resolved['paused_at'] = $this->boolean('paused') ? ($current?->paused_at ?? now()) : null;
        }

        $start = $resolved['start_date'] ?? $current->start_date->toDateString();
        $end = array_key_exists('end_date', $resolved) ? $resolved['end_date'] : $current?->end_date?->toDateString();
        if ($end !== null && $end < $start) {
            $validator->errors()->add('end_date', 'The last payment can’t come before the first.');

            return null;
        }

        return $resolved;
    }
}
