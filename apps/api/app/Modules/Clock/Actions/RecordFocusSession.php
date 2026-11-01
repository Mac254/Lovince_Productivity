<?php

namespace App\Modules\Clock\Actions;

use App\Modules\Clock\Models\FocusSession;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use App\Support\Contract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RecordFocusSession
{
    /**
     * @param  array{started_at: string, started_on: string, planned_minutes: int, focused_seconds: int, completed: bool, task_id?: int|null}  $data
     */
    public function handle(User $user, array $data): FocusSession
    {
        return DB::transaction(function () use ($user, $data) {
            $startedAt = Carbon::parse($data['started_at'])->setTimezone(config('app.timezone'))->startOfSecond();

            $existing = $user->focusSessions()->where('started_at', $startedAt)->with('task:id,title')->first();
            if ($existing) {
                return $existing;
            }

            $session = $user->focusSessions()->make([
                'started_on' => $data['started_on'],
                'started_at' => $startedAt,
                'ended_at' => $startedAt->copy()->addSeconds($data['focused_seconds']),
                'planned_minutes' => $data['planned_minutes'],
                'focused_seconds' => $data['focused_seconds'],
                'completed' => $data['completed'],
            ]);

            $task = isset($data['task_id']) ? Task::query()->whereBelongsTo($user)->find($data['task_id']) : null;

            if ($task) {
                $session->task()->associate($task);
                $session->task_note_id = $this->logWork($user, $task, $session)->id;
            }

            $session->save();

            return $session->setRelation('task', $task);
        });
    }

    private function logWork(User $user, Task $task, FocusSession $session)
    {
        $minutes = min(max(1, $session->minutes()), Contract::TASK_NOTE_MINUTES_MAX);
        $label = $session->completed ? 'Focus session' : 'Focus session, stopped early';

        $note = $task->notes()->make([
            'logged_on' => $session->started_on->toDateString(),
            'body' => "{$label} ({$minutes} min)",
            'minutes' => $minutes,
        ]);
        $note->user()->associate($user);
        $note->save();

        return $note;
    }
}
