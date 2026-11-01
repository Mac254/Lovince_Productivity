<?php

namespace App\Modules\Reports\Listeners;

use App\Modules\Reports\Services\DailyReportGenerator;
use App\Modules\Tasks\Events\WorkLogChanged;

/**
 * Keeps saved daily reports' work logs in step with the entries for their day.
 */
class RefreshReportWorkLogs
{
    public function __construct(private DailyReportGenerator $reports) {}

    public function handle(WorkLogChanged $event): void
    {
        foreach ($event->days as $day) {
            $this->reports->refreshWorkLog($event->user, $day);
        }
    }
}
