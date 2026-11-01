<?php

namespace App\Modules\Clock\Http\Requests;

use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FocusTimerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxMs = Contract::FOCUS_SESSION_MINUTES_MAX * 60_000;

        return [
            'revision' => ['required', 'integer', 'min:0'],
            'state' => ['required', 'array'],
            'state.preset_id' => ['required', 'string', 'max:20'],
            'state.kind' => ['required', Rule::in(['focus', 'break'])],
            'state.status' => ['required', Rule::in(['idle', 'running', 'paused', 'done'])],
            'state.duration_ms' => ['required', 'integer', 'min:0', 'max:'.$maxMs],
            'state.ends_at' => ['present', 'nullable', 'integer', 'min:0'],
            'state.paused_remaining_ms' => ['present', 'nullable', 'integer', 'min:0', 'max:'.$maxMs],
            'state.started_at' => ['present', 'nullable', 'integer', 'min:0'],
            'state.task' => ['present', 'nullable', 'array'],
            'state.task.id' => ['required_with:state.task', 'integer'],
            'state.task.title' => ['required_with:state.task', 'string', 'max:'.Contract::TASK_TITLE_MAX],
        ];
    }
}
