<?php

namespace App\Modules\Clock\Http\Requests;

use App\Modules\Tasks\Models\Task;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFocusSessionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'started_at' => ['required', 'date', 'before_or_equal:now'],
            'started_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:tomorrow'],
            'planned_minutes' => ['required', 'integer', 'min:1', 'max:'.Contract::FOCUS_SESSION_MINUTES_MAX],
            'focused_seconds' => ['required', 'integer', 'min:60', 'max:'.Contract::FOCUS_SESSION_MINUTES_MAX * 60],
            'completed' => ['required', 'boolean'],
            'task_id' => [
                'nullable', 'integer',
                Rule::exists(Task::class, 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }

    public function messages(): array
    {
        return ['focused_seconds.min' => 'Sessions shorter than a minute are not saved.'];
    }
}
