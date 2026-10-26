<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Support\Contract;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait TaskRules
{
    /**
     * @return array<string, mixed>
     */
    protected function taskRules(string $presence): array
    {
        return [
            'title' => [$presence, 'string', 'min:1', 'max:'.Contract::TASK_TITLE_MAX],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.Contract::DESCRIPTION_MAX],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'category' => ['sometimes', Rule::enum(TaskCategory::class)],
            'is_highlighted' => ['sometimes', 'boolean'],
            'start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'goal_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('goals', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }

    protected function ensureDueDateFollowsStartDate(Validator $validator, mixed $start, mixed $due): void
    {
        if (is_string($start) && is_string($due) && $due < $start) {
            $validator->errors()->add('due_date', 'The due date must be on or after the start date.');
        }
    }
}
