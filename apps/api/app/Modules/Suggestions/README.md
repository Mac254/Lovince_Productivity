# Suggestions

Things to do that make a day better, each with a category (focus, growth, health, wellbeing, social, fun) and a status (new, accepted, done, dismissed).

- Driven by sources in `Sources/`, each a `Suggester` that reads the user's data: `PlanTomorrow` (apply a schedule when tomorrow is empty), `OverdueTasks`, `QuietGoals` (an active goal with nothing finished this week), `EveningJournal` (no entry after 6 pm) and `IdeaLibrary`, which tops up ideas from `Config/suggestions.php`. A key per suggestion stops repeats; open insights whose reason has passed are withdrawn. A new source is one class added to `SuggestionEngine::sources()`.
- `GET /api/v1/suggestions?status=` lists them (new by default) and refreshes at most hourly; `POST /suggestions/refresh` refreshes now; `POST /suggestions` adds your own; `PATCH` and `DELETE /suggestions/{id}` manage them.
- `POST /suggestions/{id}/accept` (optional `date`) acts on one: applies the schedule, adds a task due that day, or for `open` suggestions just marks it accepted.
- Owns `suggestions` and the Suggestions settings.

Depends on Users, Settings, Tasks, Goals, Journal and Planner.
