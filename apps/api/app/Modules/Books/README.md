# Books

The books you've read, are reading, have on the shelf or still need to find, with how far you are and a log of each sitting.

- `/api/v1/books` (apiResource), authorised by `BookPolicy`. `GET` filters by `status` and `goal_id`. Each book carries its progress (chapters read of the total), minutes read, the last day read and its open tasks; `GET books/{book}` adds its reading log and tasks.
- Status is `to_find`, `to_read`, `reading` or `finished`. Starting a book sets `started_on`; finishing it sets `finished_on` and, when the chapter count is known, marks every chapter read. Moving a finished book back clears `finished_on`.
- `POST books/{book}/readings`, `PATCH`/`DELETE book-readings/{reading}`: a reading log entry (day, chapters, minutes, note). Each moves the book's `chapters_read` by its chapters: the first starts a book still to find or to read, reaching the last chapter finishes it, and removing chapters from a finished book reopens it.
- `POST books/{book}/tasks` creates a reading task (it takes the book's goal) or, with `task_id`, links one of your tasks; `DELETE books/{book}/tasks/{task}` unlinks it. A repeating task's next copy keeps the link.
- Owns `books`, `book_readings` and the `tasks.book_id` column; adds the `User::books`, `Goal::books` and `Task::book` relations. Deleting a book removes its reading log and unlinks its tasks; deleting a goal unlinks its books.
- Settings (`books`): `show_in_sidebar`, `start_tab` (the Books page's first tab) and `show_in_insights` (the reading card on reports and metrics).

Depends on Users, Goals and Tasks. Reports, Metrics, Search and Backup read it.
