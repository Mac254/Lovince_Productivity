<?php

namespace App\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Logs task and goal changes: which record, who owns it, and which fields changed.
 * Field values (titles, descriptions) stay out of the logs; only status moves are shown.
 */
class ActivityObserver
{
    public function created(Model $model): void
    {
        Log::info($this->event($model, 'created'), $this->context($model));
    }

    public function updated(Model $model): void
    {
        $changed = array_values(array_diff(array_keys($model->getChanges()), ['updated_at', 'completed_at']));

        if ($changed === []) {
            return;
        }

        $context = $this->context($model) + ['changed' => implode(', ', $changed)];

        if ($model->wasChanged('status')) {
            $context['status'] = $this->value($model->getOriginal('status')).' -> '.$this->value($model->getAttribute('status'));
        }

        Log::info($this->event($model, 'updated'), $context);
    }

    public function deleted(Model $model): void
    {
        Log::info($this->event($model, 'deleted'), $this->context($model));
    }

    private function event(Model $model, string $action): string
    {
        return strtolower(class_basename($model)).'.'.$action;
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Model $model): array
    {
        return ['id' => $model->getKey(), 'user_id' => $model->getAttribute('user_id')];
    }

    private function value(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
