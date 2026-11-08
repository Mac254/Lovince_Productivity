# Roadmap

Where LovinceTracker is heading: what you're building now, what comes next, what can wait, and what has shipped.

- `/api/v1/roadmap-items` (apiResource, `{item}`), authorised by `RoadmapItemPolicy`. `GET` returns every item ordered by position.
- Each item has a title, optional details and area (Tasks, Money, Books…), a `stage` of `now`, `next`, `later` or `shipped`, and a `position` within its stage. Moving an item to another stage puts it at the end of that stage. Shipping it sets `shipped_on` (today unless given); moving it back clears it.
- `POST roadmap-items/reorder` with `stage` and `ids` puts those items into the stage in the order sent, so one call handles both reordering and moving across stages.
- Settings (`roadmap`): `show_in_sidebar` and `show_shipped` (keeps the Shipped lane on the page). The page moves items by drag and drop or from each card's menu; both use `reorder` or `stage`.
- Owns `roadmap_items`; adds the `User::roadmapItems` relation.

Depends on Users. Search and Backup read it.
