<?php

namespace App\Http\Requests\Task;

use App\Models\Task;
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
        return $this->taskRules('sometimes');
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
        ];
    }
}
