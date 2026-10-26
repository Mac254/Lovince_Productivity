<?php

namespace App\Http\Requests\Blocker;

use App\Enums\BlockerStatus;
use App\Models\Blocker;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BlockerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $blocker = $this->route('blocker');
        if ($blocker instanceof Blocker) {
            Gate::authorize('update', $blocker);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'description' => [$presence, 'string', 'min:1', 'max:'.Contract::BLOCKER_MAX],
            'status' => ['sometimes', Rule::enum(BlockerStatus::class)],
            'task_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tasks', 'id')->where('user_id', $this->user()->id)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('description'))) {
            $this->merge(['description' => trim($this->input('description'))]);
        }
    }
}
