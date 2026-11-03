<?php

namespace App\Modules\Tasks\Http\Requests;

use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateTaskRequest extends FormRequest
{
    use TaskRules;

    /**
     * Ownership is checked before validation so foreign records are never probed.
     */
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('task'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->taskRules('sometimes'),
            'completed_at' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['completed_at.before_or_equal' => 'You can’t complete a task on a future day.'];
    }

    /**
     * Compare against stored dates when only one side is being changed.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        /** @var Task $task */
        $task = $this->route('task');

        return [
            fn (Validator $validator) => $this->ensureDueDateFollowsStartDate(
                $validator,
                $this->has('start_date') ? $this->input('start_date') : $task->start_date?->toDateString(),
                $this->has('due_date') ? $this->input('due_date') : $task->due_date?->toDateString(),
            ),
            fn (Validator $validator) => $this->ensureCompletionFits($validator, $task),
        ];
    }

    private function ensureCompletionFits(Validator $validator, Task $task): void
    {
        $day = $this->input('completed_at');
        if (! is_string($day) || $validator->errors()->has('completed_at')) {
            return;
        }

        $status = $this->has('status') ? TaskStatus::tryFrom((string) $this->input('status')) : $task->status;
        if ($status !== TaskStatus::Completed) {
            $validator->errors()->add('completed_at', 'Only a completed task has a completion day.');
        } elseif ($day < $task->created_at->toDateString()) {
            $validator->errors()->add('completed_at', 'A task can’t be completed before it was created.');
        }
    }
}
