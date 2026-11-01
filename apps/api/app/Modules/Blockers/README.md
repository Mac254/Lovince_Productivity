# Blockers

Things blocking work, optionally on a task, open until resolved.

- `/api/v1/blockers` (apiResource without `show`), authorised by `BlockerPolicy`.
- Owns `BlockerStatus`; adds the `User::blockers` and `Task::blockers` relations.

Depends on Users and Tasks (a blocker can be raised on a task).
