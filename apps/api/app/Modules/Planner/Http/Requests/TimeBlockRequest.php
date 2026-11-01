<?php

namespace App\Modules\Planner\Http\Requests;

use App\Modules\Planner\Models\TimeBlock;
use App\Modules\Tasks\Models\Task;
use App\Support\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TimeBlockRequest extends FormRequest
{
    private const DAY_MINUTES = 1440;

    public function authorize(): bool
    {
        $block = $this->route('block');
        if ($block instanceof TimeBlock) {
            Gate::authorize('update', $block);
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
            'task_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Task::class, 'id')->where('user_id', $this->user()->id)],
            'title' => ['sometimes', 'nullable', 'string', 'max:'.Contract::TIME_BLOCK_TITLE_MAX],
            'date' => [$required, 'date_format:Y-m-d'],
            'start_minute' => [$required, 'integer', 'min:0', 'max:'.(self::DAY_MINUTES - 1)],
            'minutes' => [$required, 'integer', 'min:'.Contract::TIME_BLOCK_MINUTES_MIN, 'max:'.Contract::TIME_BLOCK_MINUTES_MAX],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function blockData(): array
    {
        $data = $this->validated();
        if (! empty($data['task_id']) && blank($data['title'] ?? null)) {
            $data['title'] = Task::query()->whereKey($data['task_id'])->value('title');
        }

        return $data;
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $block = $this->route('block');
            $current = $block instanceof TimeBlock ? $block : null;
            $data = $validator->validated() + [
                'task_id' => $current?->task_id,
                'title' => $current?->title,
                'date' => $current?->date->toDateString(),
                'start_minute' => $current?->start_minute,
                'minutes' => $current?->minutes,
            ];

            if (! $data['task_id'] && blank($data['title'])) {
                $validator->errors()->add('title', 'Give the block a title or pick a task.');
            }

            $end = $data['start_minute'] + $data['minutes'];
            if ($end > self::DAY_MINUTES) {
                $validator->errors()->add('minutes', 'The block must end by midnight.');

                return;
            }

            $overlaps = TimeBlock::query()
                ->whereBelongsTo($this->user())
                ->whereDate('date', $data['date'])
                ->when($current, fn ($q) => $q->whereKeyNot($current->getKey()))
                ->where('start_minute', '<', $end)
                ->whereRaw('start_minute + minutes > ?', [$data['start_minute']])
                ->exists();

            if ($overlaps) {
                $validator->errors()->add('start_minute', 'This overlaps another block.');
            }
        }];
    }
}
