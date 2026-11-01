# Metrics

Trends over the last 7, 30 or 90 days: daily totals, a heatmap, tasks by category, priority and type, and active goals' progress.

- `GET /api/v1/metrics?days=`.
- `MetricsService` computes them; no tables of its own.

A read model over Tasks, Goals and Blockers.
