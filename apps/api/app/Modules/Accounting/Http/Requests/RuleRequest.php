<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Modules\Accounting\Models\MoneyRule;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Creates or changes a money rule. A rule added from a suggestion carries its key.
 */
class RuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rule = $this->route('rule');
        if ($rule instanceof MoneyRule) {
            Gate::authorize('update', $rule);
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
            'title' => [$presence, 'string', 'min:1', 'max:'.Contract::ACCOUNTING_RULE_TITLE_MAX],
            'reason' => ['sometimes', 'nullable', 'string', 'max:'.Contract::ACCOUNTING_RULE_REASON_MAX],
            'pinned' => ['sometimes', 'boolean'],
            'suggestion' => ['sometimes', 'nullable', 'string', 'max:'.Contract::ACCOUNTING_RULE_SUGGESTION_MAX, 'regex:/^[a-z0-9-]+$/'],
            'archived' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        /** @var MoneyRule|null $rule */
        $rule = $this->route('rule');
        $fields = $this->safe()->only(['title', 'reason', 'pinned']);
        if ($rule === null && $this->filled('suggestion')) {
            $fields['suggestion'] = $this->input('suggestion');
        }
        if ($this->has('archived')) {
            $fields['archived_at'] = $this->boolean('archived') ? ($rule?->archived_at ?? now()) : null;
        }

        return $fields;
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'reason'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $field === 'reason' && $value === '' ? null : $value]);
            }
        }
    }
}
