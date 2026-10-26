<?php

namespace App\Http\Requests\TaskType;

use App\Enums\GoalTone;
use App\Models\TaskType;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TaskTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = $this->route('task_type');
        if ($type instanceof TaskType) {
            Gate::authorize('update', $type);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->route('task_type');
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [
                $presence, 'string', 'min:1', 'max:'.Contract::TASK_TYPE_NAME_MAX,
                Rule::unique('task_types', 'name')
                    ->where('user_id', $this->user()->id)
                    ->ignore($type instanceof TaskType ? $type->id : null),
            ],
            'tone' => ['sometimes', Rule::enum(GoalTone::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->isMethod('POST') && $this->user()->taskTypes()->count() >= Contract::TASK_TYPE_LIMIT) {
                $validator->errors()->add('name', 'You can have up to '.Contract::TASK_TYPE_LIMIT.' task types.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'You already have a type with this name.'];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim(preg_replace('/\s+/', ' ', $this->input('name')))]);
        }
    }
}
