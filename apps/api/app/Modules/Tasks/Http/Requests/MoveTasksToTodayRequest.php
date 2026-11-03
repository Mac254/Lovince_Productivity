<?php

namespace App\Modules\Tasks\Http\Requests;

use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;

class MoveTasksToTodayRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.Contract::PER_PAGE_MAX],
            'ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }
}
