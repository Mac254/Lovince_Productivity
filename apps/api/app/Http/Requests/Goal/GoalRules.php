<?php

namespace App\Http\Requests\Goal;

use App\Enums\GoalStatus;
use App\Enums\GoalTimeframe;
use App\Enums\GoalTone;
use App\Support\Contract;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait GoalRules
{
    /**
     * @return array<string, mixed>
     */
    protected function goalRules(string $presence): array
    {
        return [
            'title' => [$presence, 'string', 'min:1', 'max:'.Contract::GOAL_TITLE_MAX],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.Contract::DESCRIPTION_MAX],
            'status' => ['sometimes', Rule::enum(GoalStatus::class)],
            'timeframe' => ['sometimes', Rule::enum(GoalTimeframe::class)],
            'tone' => ['sometimes', Rule::enum(GoalTone::class)],
            'start_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'target_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }

    protected function ensureTargetFollowsStart(Validator $validator, mixed $start, mixed $target): void
    {
        if (is_string($start) && is_string($target) && $target < $start) {
            $validator->errors()->add('target_date', 'The target date must be on or after the start date.');
        }
    }
}
