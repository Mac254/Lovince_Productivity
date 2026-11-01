# Reports

Daily report snapshots: what was planned, done, logged and blocked on each day.

- `GET /api/v1/reports`, `POST reports` (regenerate today), `GET reports/{date}`.
- `DailyReportGenerator`, the queued `GenerateDailyReport` job and `php artisan focus:daily-report`, scheduled at 23:55 and 00:05 (see the provider).
- Keeps saved reports' work logs current by listening to the Tasks module's `WorkLogChanged`.

A read model over Tasks, Goals, Blockers and Journal; nothing depends on Reports.
