# Planner

Time blocking: blocks of time on a day, each for a task or with its own title, shown on a timeline on the Today page.

- `GET /api/v1/time-blocks?date=` lists a day's blocks; `POST`, `PATCH /time-blocks/{id}` and `DELETE /time-blocks/{id}` manage them. Blocks end by midnight and may not overlap on the same day.
- Owns `time_blocks`. Deleting a task keeps its blocks, without the task.
- Owns the Day planner settings (whether the timeline shows, the hours it covers and the snap step).

Depends on Users, Settings and Tasks.
