<?php

namespace App\Modules\Roadmap\Http\Requests;

use App\Modules\Roadmap\Enums\RoadmapStage;
use App\Modules\Roadmap\Models\RoadmapItem;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
            'shipped_on' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
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
