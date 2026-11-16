# Reviews

Reviews of a week, a month or a year: what the period held (tasks completed, time logged, focus, blockers, goals and journal entries), what carries over, what the next period holds, the user's own reflection, and the shorter reviews written inside it (a month's weekly reviews, a year's monthly ones).

- `GET /api/v1/reviews?period=` lists finished reviews, newest first. `GET|PUT /api/v1/reviews/{period}/{date}` reads or saves the review of the week (Monday first), month or year that contains the date, up to the current one. Saving stores wins, challenges and the next period's focus, and `completed` marks the review done. `/reviews/{date}` still means the week.
- `ReviewPeriod` says where each period starts and ends; `ReviewSummary` builds the page. A week lists every completed task; a month or year lists the latest 100 and counts them all. The chart has a bar per day, or per month for a year.
- The module owns only `reviews` (one row per user, period and start). Backups keep the `weekly_reviews` key with a `period` per row, so older backups import as weeks.
- Owns the Weekly review settings (which day the dashboard reminds you to review the week).

Depends on Users, Settings, Tasks, Goals, Blockers, Journal and Clock.
