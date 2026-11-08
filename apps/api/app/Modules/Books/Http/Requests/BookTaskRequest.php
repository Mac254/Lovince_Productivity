<?php

namespace App\Modules\Books\Http\Requests;

use App\Modules\Tasks\Enums\TaskRecurrence;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A reading task for a book: a new one, or one of the user's tasks to link.
 */
class BookTaskRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'task_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tasks', 'id')->where('user_id', $this->user()->id)],
            'title' => ['required_without:task_id', 'nullable', 'string', 'min:1', 'max:'.Contract::TASK_TITLE_MAX],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'recurrence' => ['sometimes', 'nullable', Rule::enum(TaskRecurrence::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }
}
