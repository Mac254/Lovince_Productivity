# Dashboard

The dashboard summary: today's, overdue, upcoming and highlighted tasks, recent activity, this week's stats and goals, today's focus time against the daily goal, open blockers, a quote, and the weekly review prompt (from the review day set in Weekly review settings until that week's review is done).

- `GET /api/v1/dashboard`, built by `DashboardSummary`; no tables of its own.

A read model over Tasks, Goals, Blockers, Quotes, Clock and Reviews.
