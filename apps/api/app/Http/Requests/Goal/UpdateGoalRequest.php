<?php

namespace App\Http\Requests\Goal;

use App\Models\Goal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateGoalRequest extends FormRequest
{
    use GoalRules;

    /**
     * Ownership is checked before validation so foreign records are never probed.
     */
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('goal'));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->goalRules('sometimes');
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        /** @var Goal $goal */
        $goal = $this->route('goal');

        return [
            fn (Validator $validator) => $this->ensureTargetFollowsStart(
                $validator,
                $this->has('start_date') ? $this->input('start_date') : $goal->start_date?->toDateString(),
                $this->has('target_date') ? $this->input('target_date') : $goal->target_date?->toDateString(),
            ),
        ];
    }
}
