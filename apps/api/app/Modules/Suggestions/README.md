# Suggestions

Things to do that make a day better, each with a category (focus, growth, health, wellbeing, social, fun) and a status (new, accepted, done, dismissed). `accepted_at`, `done_at` and `dismissed_at` record when each status was reached, so Reports and Metrics can count them by day.

- Driven by sources in `Sources/`, each a `Suggester` that reads the user's data: `PlanTomorrow` (apply a schedule when tomorrow is empty), `OverdueTasks`, `StalledTasks` (in progress for a week with nothing logged), `QuietGoals` (an active goal with nothing finished this week), `CelebrateGoals` (a goal completed in the last two weeks with no achievement), `StreakMilestones` (7, 14, 30… days of activity in a row), `UnreviewedWeek` (Monday to Wednesday, last week had work but no review), `EveningJournal` (no entry after 6 pm) and `IdeaLibrary`, which tops up ideas from `Config/suggestions.php`. A new source is one class added to `SuggestionEngine::sources()`.
- A key per suggestion stops repeats; open insights whose reason has passed are withdrawn. Library ideas are picked one category at a time in a daily order, rotate out after `rotate_after_days` untouched, and rest for `cooldown_days` once done or dismissed before they can return.
- `GET /api/v1/suggestions?status=` lists them (`new` by default, `all` for the catalogue) and refreshes at most hourly; `POST /suggestions/refresh` refreshes now; `POST /suggestions` adds your own; `PATCH` and `DELETE /suggestions/{id}` manage them.
- `POST /suggestions/{id}/accept` (optional `date`) acts on one: applies the schedule, records an achievement, adds a task due that day, or for `open` suggestions (a route and optional params) just marks it accepted.
- Owns `suggestions` and the Suggestions settings.

Depends on Users, Settings, Tasks, Goals, Journal, Planner, Reviews, Achievements and Metrics (activity streaks).
