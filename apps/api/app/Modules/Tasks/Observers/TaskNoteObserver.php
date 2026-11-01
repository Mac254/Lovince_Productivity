<?php

namespace App\Modules\Tasks\Observers;

use App\Modules\Tasks\Events\WorkLogChanged;
use App\Modules\Tasks\Models\TaskNote;
use Illuminate\Support\Carbon;

/**
 * Announces which days' work logs changed, including both days when an entry moves to another
 * date, so saved daily reports can follow them.
 */
class TaskNoteObserver
{
    public function saved(TaskNote $note): void
    {
        $days = collect([$note->logged_on, $note->wasChanged('logged_on') ? $note->getOriginal('logged_on') : null])
            ->filter()
            ->map(fn (Carbon $day) => $day->toDateString())
            ->unique()
            ->map(fn (string $day) => Carbon::parse($day))
            ->values()
            ->all();

        WorkLogChanged::dispatch($note->user, $days);
    }

    public function deleted(TaskNote $note): void
    {
        WorkLogChanged::dispatch($note->user, [$note->logged_on]);
    }
}
