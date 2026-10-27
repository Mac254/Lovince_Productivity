<?php

namespace App\Http\Requests\TaskNote;

use App\Models\TaskNote;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TaskNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $note = $this->route('note');
        if ($note instanceof TaskNote) {
            Gate::authorize('update', $note);
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
            'body' => [$presence, 'string', 'min:1', 'max:'.Contract::TASK_NOTE_MAX],
            'logged_on' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:today'],
            'minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.Contract::TASK_NOTE_MINUTES_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['logged_on.before_or_equal' => 'You can’t log work for a future day.'];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }
    }
}
