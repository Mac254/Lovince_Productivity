<?php

namespace App\Modules\Reviews\Http\Requests;

use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;

class WeeklyReviewRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'wins' => ['sometimes', 'nullable', 'string', 'max:'.Contract::REVIEW_MAX],
            'challenges' => ['sometimes', 'nullable', 'string', 'max:'.Contract::REVIEW_MAX],
            'next_focus' => ['sometimes', 'nullable', 'string', 'max:'.Contract::REVIEW_MAX],
            'completed' => ['sometimes', 'boolean'],
        ];
    }
}
