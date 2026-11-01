# Backup

Everything you've written, as one JSON file you can keep or move to another Focus install.

- `GET /api/v1/backup` downloads settings, task types, goals, tasks (with their work log and checklist), blockers, journal entries, focus sessions, weekly reviews and time blocks.
- `POST /api/v1/backup` with `file` adds a backup's records to your account and returns how many of each were added. Task types are matched by name; days that already have a journal entry or a weekly review keep what's there. Records keep their links to each other through each file's `ref` ids.
- No tables of its own.

Depends on every module that owns user records.
