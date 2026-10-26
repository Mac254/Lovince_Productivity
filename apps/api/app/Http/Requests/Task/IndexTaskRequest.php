<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskCategory;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTaskRequest extends FormRequest
{
    public const DUE_FILTERS = ['today', 'overdue', 'week', 'upcoming', 'none'];

    public const SORTS = ['due_date', 'priority', 'created_at', 'updated_at', 'title'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:'.Contract::SEARCH_MAX],
            'status' => ['sometimes', 'nullable', Rule::in([...array_column(TaskStatus::cases(), 'value'), 'open'])],
            'priority' => ['sometimes', 'nullable', Rule::enum(TaskPriority::class)],
            'category' => ['sometimes', 'nullable', Rule::enum(TaskCategory::class)],
            'due' => ['sometimes', 'nullable', Rule::in(self::DUE_FILTERS)],
            'goal_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'task_type_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'highlighted' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'nullable', Rule::in(self::SORTS)],
            'direction' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.Contract::PER_PAGE_MAX],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
