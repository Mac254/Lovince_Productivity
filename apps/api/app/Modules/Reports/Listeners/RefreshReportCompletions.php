<?php

namespace App\Modules\Reports\Listeners;

use App\Modules\Reports\Models\DailyReport;
use App\Modules\Reports\Services\DailyReportGenerator;
use App\Modules\Tasks\Events\CompletionsChanged;

/**
 * Rebuilds saved reports for earlier days whose completions changed, so work recorded late counts on its day.
 */
class RefreshReportCompletions
{
    public function __construct(private DailyReportGenerator $reports) {}

    public function handle(CompletionsChanged $event): void
    {
        foreach ($event->days as $day) {
            if (DailyReport::query()->whereBelongsTo($event->user)->whereDate('date', $day)->exists()) {
                $this->reports->generate($event->user, $day);
            }
        }
    }
}
