# Metrics

Trends over the last 7, 30 or 90 days: daily totals, focus time (daily goal, time of day), a heatmap that counts focus time too, tasks by category, priority and type, active goals' progress, suggestions (offered, acted on, dismissed, acceptance rate and by category), achievements (count, from goals, by category, latest), reading (chapters, minutes, days read, books finished, the shelf by status and the books in progress), and plain-language insights (`Insights`): overdue work, pace against the previous range, on-time rate, estimate accuracy, the activity streak, peak focus hour, best weekday, main category, suggestion follow-through and achievements.

- `GET /api/v1/metrics?days=`.
- `MetricsService` computes them; no tables of its own.

A read model over Tasks, Goals, Blockers, Clock, Suggestions, Achievements and Books. The heatmap counts reading sittings as activity.
