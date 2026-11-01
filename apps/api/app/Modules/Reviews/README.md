# Reviews

The weekly review: what the week held (tasks completed, time logged, focus, blockers, goals and journal entries), what carries over, what next week holds, and the user's own reflection.

- `GET /api/v1/reviews` lists completed reviews; `GET|PUT /api/v1/reviews/{date}` reads or saves the review for the week containing that date (Monday first, up to this week). Saving stores wins, challenges and next week's focus, and `completed` marks the review done.
- `WeeklyReviewSummary` builds the page; the module owns only `weekly_reviews`.
- Owns the Weekly review settings (which day the dashboard reminds you).

Depends on Users, Settings, Tasks, Goals, Blockers, Journal and Clock.
