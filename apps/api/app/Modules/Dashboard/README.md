# Dashboard

The dashboard summary: today's, overdue, upcoming and highlighted tasks, recent activity, this week's stats and goals, today's focus time against the daily goal, open blockers, this week's achievements and journal entries, a quote, and the weekly review prompt (from the review day set in Weekly review settings until that week's review is done).

- `GET /api/v1/dashboard`, built by `DashboardSummary`; no tables of its own.

A read model over Tasks, Goals, Blockers, Achievements, Journal, Quotes, Clock and Reviews. The web dashboard adds the month's money from `accounting/dashboard`.
