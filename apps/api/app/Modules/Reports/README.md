# Reports

Daily report snapshots: what was planned, done, logged and blocked on each day, plus the suggestions acted on, the achievements recorded and the reading done (chapters, minutes, books finished).

- `GET /api/v1/reports`, `POST reports` (regenerate today), `GET reports/{date}`.
- `DailyReportGenerator`, the queued `GenerateDailyReport` job and `php artisan focus:daily-report`, run every five minutes with `--due`, which reports each account at 23:55 and finalises at 00:05 in its own timezone (see the provider).
- Keeps saved reports' work logs current by listening to the Tasks module's `WorkLogChanged`.

A read model over Tasks, Goals, Blockers, Journal, Suggestions, Achievements and Books; nothing depends on Reports.
