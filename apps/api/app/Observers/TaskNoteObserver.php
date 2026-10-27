<?php

namespace App\Observers;

use App\Models\TaskNote;
use App\Services\DailyReportGenerator;
use Illuminate\Support\Carbon;

/**
 * Keeps saved daily reports' work logs in step with the entries for their day,
 * including both days when an entry moves to another date.
 */
class TaskNoteObserver
{
    public function __construct(private DailyReportGenerator $reports) {}

    public function saved(TaskNote $note): void
    {
        $days = collect([$note->logged_on, $note->wasChanged('logged_on') ? $note->getOriginal('logged_on') : null])
            ->filter()
            ->map(fn (Carbon $day) => $day->toDateString())
            ->unique();

        foreach ($days as $day) {
            $this->reports->refreshWorkLog($note->user, Carbon::parse($day));
        }
    }

    public function deleted(TaskNote $note): void
    {
        $this->reports->refreshWorkLog($note->user, $note->logged_on);
    }
}
