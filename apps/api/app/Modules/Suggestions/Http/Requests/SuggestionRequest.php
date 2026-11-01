<?php

namespace App\Modules\Suggestions\Http\Requests;

use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Modules\Suggestions\Enums\SuggestionStatus;
use App\Modules\Suggestions\Models\Suggestion;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $suggestion = $this->route('suggestion');
        if ($suggestion instanceof Suggestion) {
            Gate::authorize('update', $suggestion);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:'.Contract::SUGGESTION_TITLE_MAX],
            'body' => ['sometimes', 'nullable', 'string', 'max:'.Contract::SUGGESTION_BODY_MAX],
            'category' => [$required, Rule::enum(SuggestionCategory::class)],
            'status' => ['sometimes', Rule::enum(SuggestionStatus::class)],
        ];
    }
}
