<?php

namespace App\Modules\Roadmap\Http\Requests;

use App\Modules\Roadmap\Enums\RoadmapStage;
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
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer', 'distinct', Rule::exists('roadmap_items', 'id')->where('user_id', $this->user()->id)],
        ];
    }
}
