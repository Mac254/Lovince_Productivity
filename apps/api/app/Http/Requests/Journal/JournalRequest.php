<?php

namespace App\Http\Requests\Journal;

use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;

class JournalRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['body' => ['present', 'nullable', 'string', 'max:'.Contract::JOURNAL_MAX]];
    }
}
