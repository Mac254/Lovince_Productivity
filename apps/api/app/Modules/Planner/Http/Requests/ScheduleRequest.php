<?php

namespace App\Modules\Planner\Http\Requests;

use App\Modules\Planner\Models\Schedule;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ScheduleRequest extends FormRequest
{
    private const DAY_MINUTES = 1440;

    public function authorize(): bool
    {
        $schedule = $this->route('schedule');
        if ($schedule instanceof Schedule) {
            Gate::authorize('update', $schedule);
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $owned = fn (string $table) => Rule::exists($table, 'id')->where('user_id', $this->user()->id);

        return [
            'name' => [$required, 'string', 'max:'.Contract::SCHEDULE_NAME_MAX],
            'days' => ['sometimes', 'nullable', 'array'],
            'days.*' => ['integer', 'distinct', 'between:1,7'],
            'items' => [$required, 'array', 'max:'.Contract::SCHEDULE_ITEM_LIMIT],
            'items.*.title' => ['required', 'string', 'max:'.Contract::TIME_BLOCK_TITLE_MAX],
            'items.*.task_type_id' => ['sometimes', 'nullable', 'integer', $owned('task_types')],
            'items.*.goal_id' => ['sometimes', 'nullable', 'integer', $owned('goals')],
            'items.*.start_minute' => ['required', 'integer', 'min:0', 'max:'.(self::DAY_MINUTES - 1)],
            'items.*.minutes' => ['required', 'integer', 'min:'.Contract::TIME_BLOCK_MINUTES_MIN, 'max:'.Contract::TIME_BLOCK_MINUTES_MAX],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->has('items')) {
                return;
            }

            $items = collect($this->validated('items'))->sortBy('start_minute')->values();
            foreach ($items as $index => $item) {
                if ($item['start_minute'] + $item['minutes'] > self::DAY_MINUTES) {
                    $validator->errors()->add('items', "“{$item['title']}” must end by midnight.");
                }
                $next = $items->get($index + 1);
                if ($next && $next['start_minute'] < $item['start_minute'] + $item['minutes']) {
                    $validator->errors()->add('items', "“{$item['title']}” overlaps “{$next['title']}”.");
                }
            }
        }];
    }
}
