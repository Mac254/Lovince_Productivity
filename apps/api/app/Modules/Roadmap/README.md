# Roadmap

Your plans, phase by phase: what you're working on now, what comes next, what can wait, and what's done. Built for long plans such as the money roadmap (emergency fund → savings pot → investing), where each phase is a stretch of months with its own milestones.

- `/api/v1/roadmap-items` (apiResource, `{item}`), authorised by `RoadmapItemPolicy`. `GET` returns every item ordered by position.
- Each item has a title, optional details and area (Money, Books…), a `stage` of `now`, `next`, `later` or `done`, and a `position` within its stage. Moving an item to another stage puts it at the end of that stage. Marking it done sets `done_on` (today unless given); moving it back clears it.
- An item can follow a goal (`goal_id`) over a stretch of dates (`starts_on`, `ends_on`). Its `milestones` are that goal's tasks (cancelled and repeating ones aside) due within those dates, worked out on every read by `Services/PhaseMilestones`, so ticking a task off or moving its due date moves the phase with it. Without dates, every task of the goal counts.
- `POST roadmap-items/reorder` with `stage` and `ids` puts those items into the stage in the order sent, so one call handles both reordering and moving across stages.
- Settings (`roadmap`): `show_in_sidebar` and `show_done` (keeps the Done lane on the page). The page moves items by drag and drop or from each card's menu; both use `reorder` or `stage`.
- Owns `roadmap_items`; adds the `User::roadmapItems` and `Goal::roadmapItems` relations. Deleting a goal unlinks its phases.

Depends on Users, Goals and Tasks. Search and Backup read it.
