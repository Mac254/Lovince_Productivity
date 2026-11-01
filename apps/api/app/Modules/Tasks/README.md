# Tasks

Tasks, the user's own task types, and each task's work log (task notes).

- `/api/v1/tasks` (apiResource), `/api/v1/task-types`, `tasks/{task}/notes`, `task-notes/{note}`; authorised by the Task, TaskType and TaskNote policies.
- A task may carry `estimate_minutes`; task responses from `/tasks` include `logged_minutes` (the sum of its work log), and Metrics compares the two. The Tasks settings turn estimates on or off.
- Owns `TaskStatus`, `TaskPriority`, `TaskCategory`, and `TypeBreakdown` (tasks per type, used by Dashboard, Metrics and Reports).
- `WorkLogChanged` is dispatched when work log entries for some days change; Reports listens.
- Provides goal progress (`GoalTaskProgress` for the Goals `GoalProgress` contract), the `Goal::tasks` relation, and detaches tasks from a goal being deleted.
- `DemoSeeder` fills an empty account with sample goals and tasks (`FOCUS_SEED_DEMO`).

Task types live here rather than in their own module: a task and its type refer to each other. Depends on Users and Goals (a task belongs to a goal).
