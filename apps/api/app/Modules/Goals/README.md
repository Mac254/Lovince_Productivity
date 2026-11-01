# Goals

Goals with a status, timeframe, tone and dates, and their progress.

- `/api/v1/goals` (apiResource), authorised by `GoalPolicy`.
- `DeleteGoal` deletes in a transaction; the Tasks module detaches the goal's tasks as it goes.
- Owns `GoalStatus`, `GoalTimeframe` and `GoalTone` (also the palette task types use).

Progress is counted from tasks, which Goals does not know about: `Goal::withProgress()` asks the `GoalProgress` contract, which the Tasks module implements. Depends only on Users.
