<?php

namespace App\Modules\Books\Http\Requests;

use App\Modules\Books\Models\BookReading;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class BookReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reading = $this->route('reading');
        if ($reading instanceof BookReading) {
            Gate::authorize('update', $reading);
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
            'read_on' => [$presence, 'date_format:Y-m-d'],
            'chapters' => ['sometimes', 'integer', 'min:0', 'max:'.Contract::BOOK_READING_CHAPTERS_MAX],
            'minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.Contract::TASK_NOTE_MINUTES_MAX],
            'note' => ['sometimes', 'nullable', 'string', 'max:'.Contract::BOOK_READING_NOTE_MAX],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->isMethod('POST')) {
                return;
            }
            if ((int) $this->input('chapters', 0) === 0 && ! $this->filled('minutes') && ! $this->filled('note')) {
                $validator->errors()->add('chapters', 'Log at least a chapter, some minutes or a note.');
            }
        }];
    }
}
