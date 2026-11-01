<?php

namespace App\Modules\Tasks\Http\Requests;

use App\Modules\Tasks\Models\ChecklistItem;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ChecklistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');
        Gate::authorize('update', $item instanceof ChecklistItem ? $item : $this->route('task'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'min:1', 'max:'.Contract::CHECKLIST_ITEM_MAX],
            'done' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }
}
