# Backup

Everything you've written, as one JSON file you can keep or move to another LovinceTracker install.

- `GET /api/v1/backup` downloads settings, task types, goals, tasks (with their work log and checklist), blockers, journal entries, focus sessions, weekly reviews, time blocks, day schedules, suggestions you've kept, achievements and books with their reading log (format version 2; version 1 files still import). Uploaded images (task covers, wallpapers, avatar) stay out; preset covers come across.
- `POST /api/v1/backup` with `file` adds a backup's records to your account and returns how many of each were added. Records already in the account are skipped, so importing the same file twice adds nothing: task types and schedules match by name, tasks by title and creation time, goals by title and dates, focus sessions by start, time blocks by slot, journal entries and weekly reviews by day, books by title and author. Records keep their links to each other through each file's `ref` ids. Saved daily reports for the days the import touched are rebuilt.
- No tables of its own.

Depends on every module that owns user records, and on Reports to rebuild saved reports.
