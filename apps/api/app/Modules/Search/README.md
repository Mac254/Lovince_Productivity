# Search

One search box over everything you've written: tasks, goals, blockers and journal entries.

- `GET /api/v1/search?q=` returns up to five matches of each kind with a plain-text excerpt around the match.
- Journal entries are left out when the journal's `searchable` setting is off.
- No tables of its own.

A read model over Tasks, Goals, Blockers and Journal.
