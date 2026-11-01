# Journal

One journal entry per day, addressed by date, plus the day's context for writing about it.

- `GET /api/v1/journal`, `GET|PUT|DELETE journal/{date}`, `GET journal/{date}/context`.
- `DayContext` reads the day's completed tasks, work log, blockers and still-open tasks.
- Owns the Journal settings (writing font).

Depends on Users, and reads Tasks and Blockers for the day context.
