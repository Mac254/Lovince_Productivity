# Achievements

Wins, milestones and things you're proud of, each with the day it happened and optionally a category and the goal or task it came from.

- `/api/v1/achievements` (apiResource without `show`), authorised by `AchievementPolicy`. `GET` filters by `goal_id`, `task_id`, `category` and `year`, newest first.
- Categories reuse `SuggestionCategory`. Deleting a goal or task keeps its achievements and unlinks them.
- Owns `achievements`; adds the `User::achievements` and `Goal::achievements` relations.

Depends on Users, Goals, Tasks and Suggestions (for the categories).
