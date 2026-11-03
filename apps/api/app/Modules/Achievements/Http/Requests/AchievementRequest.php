<?php

namespace App\Modules\Achievements\Http\Requests;

use App\Modules\Achievements\Models\Achievement;
use App\Modules\Suggestions\Enums\SuggestionCategory;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AchievementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $achievement = $this->route('achievement');
        if ($achievement instanceof Achievement) {
            Gate::authorize('update', $achievement);
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
            'title' => [$presence, 'string', 'min:1', 'max:'.Contract::ACHIEVEMENT_TITLE_MAX],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.Contract::DESCRIPTION_MAX],
            'achieved_on' => [$presence, 'date_format:Y-m-d'],
            'category' => ['sometimes', 'nullable', Rule::enum(SuggestionCategory::class)],
            'goal_id' => ['sometimes', 'nullable', 'integer', Rule::exists('goals', 'id')->where('user_id', $this->user()->id)],
            'task_id' => ['sometimes', 'nullable', 'integer', Rule::exists('tasks', 'id')->where('user_id', $this->user()->id)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }
}
