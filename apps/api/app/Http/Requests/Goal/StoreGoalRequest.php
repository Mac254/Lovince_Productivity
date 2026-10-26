<?php

namespace App\Http\Requests\Goal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreGoalRequest extends FormRequest
{
    use GoalRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->goalRules('required');
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->ensureTargetFollowsStart(
                $validator,
                $this->input('start_date'),
                $this->input('target_date'),
            ),
        ];
    }
}
