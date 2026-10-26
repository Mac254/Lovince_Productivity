<?php

use Illuminate\Support\Facades\Schedule;

// Snapshot each day just before it ends (APP_TIMEZONE). Run `php artisan schedule:work`
// locally (included in `pnpm dev`), or a cron entry for `schedule:run` in production.
Schedule::command('focus:daily-report')->dailyAt('23:55')->withoutOverlapping();
