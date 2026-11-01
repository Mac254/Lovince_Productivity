<?php

namespace App\Modules\Planner\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ApplyScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('view', $this->route('schedule'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'replace' => ['sometimes', 'boolean'],
        ];
    }
}
