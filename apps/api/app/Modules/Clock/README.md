# Clock

The full-screen focus clock and timer: personal wallpapers and their settings.

- `GET|POST /api/v1/wallpapers`, `GET wallpapers/{id}/image`, `DELETE wallpapers/{id}`. Raster images only, stored privately and served only to their owner.
- `GET /api/v1/focus-sessions?date=YYYY-MM-DD` returns that day's focus sessions with totals and the 7 days ending on it; `POST focus-sessions` records a finished or stopped session (at least a minute), and a session on a task also adds a work log entry; `DELETE focus-sessions/{id}` also removes the work log entry it added.
- Owns the Clock settings (wallpaper, time format, seconds, ticking) and the Focus timer settings.

Depends on Users, Settings and Tasks.
