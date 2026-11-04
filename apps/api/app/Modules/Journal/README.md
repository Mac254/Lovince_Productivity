# Journal

One journal entry per day, addressed by date, plus the day's context for writing about it.

- `GET /api/v1/journal`, `GET|PUT journal/{date}` (saving an empty body deletes the entry; an optional `base_updated_at`, the `updated_at` the text was written from or `null` for a new entry, answers `409` if the entry changed since, so offline saves never overwrite newer text), `GET journal/{date}/context`.
- `DayContext` reads the day's completed tasks, work log, blockers and still-open tasks.
- Owns the Journal settings (writing font).

Depends on Users, and reads Tasks and Blockers for the day context.
