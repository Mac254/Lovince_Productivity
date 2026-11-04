<?php

namespace App\Modules\Journal\Http\Requests;

use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;

class JournalRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['present', 'nullable', 'string', 'max:'.Contract::JOURNAL_MAX],
            'base_updated_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
