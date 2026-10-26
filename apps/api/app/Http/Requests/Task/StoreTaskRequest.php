<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTaskRequest extends FormRequest
{
    use TaskRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->taskRules('required');
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->ensureDueDateFollowsStartDate(
                $validator,
                $this->input('start_date'),
                $this->input('due_date'),
            ),
        ];
    }
}
