# Metrics

Trends over the last 7, 30 or 90 days: daily totals, focus time (streak, daily goal, time of day), a heatmap that counts focus time too, tasks by category, priority and type, active goals' progress, and plain-language insights (`Insights`): overdue work, pace against the previous range, on-time rate, estimate accuracy, focus streak and peak hour, best weekday and main category.

- `GET /api/v1/metrics?days=`.
- `MetricsService` computes them; no tables of its own.

A read model over Tasks, Goals, Blockers and Clock.
