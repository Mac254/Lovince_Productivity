<?php

namespace App\Modules\Roadmap\Http\Requests;

use App\Modules\Roadmap\Enums\RoadmapStage;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderRoadmapRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage' => ['required', Rule::enum(RoadmapStage::class)],
            'ids' => ['required', 'array', 'max:'.Contract::REORDER_MAX],
            'ids.*' => ['integer', 'distinct', Rule::exists('roadmap_items', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
