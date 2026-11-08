<?php

namespace App\Modules\Roadmap\Http\Requests;

use App\Modules\Roadmap\Enums\RoadmapStage;
use App\Modules\Roadmap\Models\RoadmapItem;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RoadmapItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');
        if ($item instanceof RoadmapItem) {
            Gate::authorize('update', $item);
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
            'title' => [$presence, 'string', 'min:1', 'max:'.Contract::ROADMAP_TITLE_MAX],
            'details' => ['sometimes', 'nullable', 'string', 'max:'.Contract::DESCRIPTION_MAX],
            'area' => ['sometimes', 'nullable', 'string', 'max:'.Contract::ROADMAP_AREA_MAX],
            'stage' => ['sometimes', Rule::enum(RoadmapStage::class)],
            'done_on' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'goal_id' => ['sometimes', 'nullable', 'integer', Rule::exists('goals', 'id')->where('user_id', $this->user()->id)],
            'money_goal_id' => ['sometimes', 'nullable', 'integer', Rule::exists('accounting_goals', 'id')->where('user_id', $this->user()->id)],
            'target_amount' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.Contract::ACCOUNTING_AMOUNT_MAX],
            'starts_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'ends_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $item = $this->route('item');
            $current = $item instanceof RoadmapItem ? $item : null;
            $start = $this->has('starts_on') ? $this->input('starts_on') : $current?->starts_on?->toDateString();
            $end = $this->has('ends_on') ? $this->input('ends_on') : $current?->ends_on?->toDateString();
            if (is_string($start) && is_string($end) && $end < $start) {
                $validator->errors()->add('ends_on', 'A phase can’t end before it starts.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'area'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field)) ?: ($field === 'area' ? null : '')]);
            }
        }
    }
}
