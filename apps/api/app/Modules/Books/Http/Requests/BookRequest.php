<?php

namespace App\Modules\Books\Http\Requests;

use App\Modules\Books\Enums\BookStatus;
use App\Modules\Books\Models\Book;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $book = $this->route('book');
        if ($book instanceof Book) {
            Gate::authorize('update', $book);
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
            'title' => [$presence, 'string', 'min:1', 'max:'.Contract::BOOK_TITLE_MAX],
            'author' => ['sometimes', 'nullable', 'string', 'max:'.Contract::BOOK_AUTHOR_MAX],
            'status' => ['sometimes', Rule::enum(BookStatus::class)],
            'chapters_total' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.Contract::BOOK_CHAPTERS_MAX],
            'chapters_read' => ['sometimes', 'integer', 'min:0', 'max:'.Contract::BOOK_CHAPTERS_MAX],
            'started_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'finished_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'rating' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.Contract::BOOK_RATING_MAX],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.Contract::DESCRIPTION_MAX],
            'goal_id' => ['sometimes', 'nullable', 'integer', Rule::exists('goals', 'id')->where('user_id', $this->user()->id)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $start = $this->input('started_on') ?? $this->current()?->started_on?->toDateString();
            $end = $this->input('finished_on');
            if (is_string($start) && is_string($end) && $end < $start) {
                $validator->errors()->add('finished_on', 'You can’t finish a book before you start it.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'author'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field)) ?: ($field === 'author' ? null : '')]);
            }
        }
    }

    private function current(): ?Book
    {
        $book = $this->route('book');

        return $book instanceof Book ? $book : null;
    }
}
