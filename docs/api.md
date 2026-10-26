# API

Base path `/api/v1`. All endpoints except `POST /auth/login` require an authenticated session. Writes need the `X-XSRF-TOKEN` header (taken from the `XSRF-TOKEN` cookie; `GET /sanctum/csrf-cookie` issues one).

## Conventions

- Success: `{ "data": … }`; lists that paginate add `links` and `meta` (`current_page`, `last_page`, `per_page`, `total`).
- `201` on create, `204` on delete and logout.
- `401` unauthenticated · `404` missing or not yours · `419` CSRF mismatch · `422` validation `{ message, errors: { field: [msg] } }` · `429` throttled.

## Auth

- `POST /auth/login` `{ email, password, remember }` → same body as `/auth/me`. Throttled (5 failures per email+IP per minute).
- `GET /auth/me` → the signed-in user and this device's sign-in:

```json
{
  "data": { "id": 1, "name": "Stephen", "email": "…", "avatar_url": null },
  "meta": { "session": { "remembered": true, "expires_at": "2026-11-27T10:00:00+00:00", "idle_timeout_minutes": 120, "remember_days": 30 } }
}
```

  `remembered` means “keep me signed in” is active: an encrypted, HTTP-only remember cookie restores the session after it idles out and is reissued for another `remember_days` each time that happens (sliding). Without it, the sign-in ends after `idle_timeout_minutes` of inactivity. `401` when signed out.
- `POST /auth/logout` → `204`. Invalidates the session, clears the remember cookie and rotates the remember token, so old cookies stop working.

No bearer tokens are issued; nothing auth-related is stored in `localStorage`.

## Tasks

`GET /tasks` query parameters:

| Param | Values |
|---|---|
| `search` | ≤100 chars, matches title and description |
| `status` | `open` (not started, in progress, blocked) or `not_started`, `in_progress`, `blocked`, `completed`, `cancelled` |
| `priority` | `low`, `medium`, `high`, `urgent` |
| `category` | `general`, `development`, `integration` |
| `due` | `today`, `overdue`, `week`, `upcoming`, `none` |
| `goal_id` | integer |
| `sort` | `due_date`, `priority`, `created_at`, `updated_at`, `title` (default: open first, then nearest due date) |
| `direction` | `asc`, `desc` |
| `per_page` | 1–100 (default 25) |

Task body (`POST` requires `title`; `PATCH` accepts any subset): `title`, `description`, `status`, `priority`, `category`, `start_date`, `due_date` (`YYYY-MM-DD`, due ≥ start), `goal_id` (must be your goal or `null`).

Response fields include `is_overdue`, `completed_at` and `goal { id, title }`.

## Goals

`GET /goals?status=&timeframe=` · body: `title`, `description`, `status` (`active`, `on_hold`, `completed`, `cancelled`), `timeframe` (`weekly`, `long_term`), `start_date`, `target_date` (target ≥ start). Responses include `progress`, `tasks_count` (non-cancelled) and `completed_tasks_count`. Deleting a goal keeps its tasks.

## Dashboard

`GET /dashboard` returns `date`, `week`, `stats` (`due_today`, `overdue`, `in_progress`, `open`, `completed_today`, `completed_this_week`, `completed_total`, `active_goals`, `week_due_total`, `week_due_completed`, `weekly_completion_rate` — `null` when nothing is due this week), the lists `today_tasks`, `overdue_tasks`, `completed_today_tasks`, `upcoming_tasks` (next 14 days), `recent_activity`, `weekly_goals`, `active_goals`, an `integration` summary and a `quote` (or `null`).

## Quotes

`GET /quotes/random?exclude={id}` → `{ data: { id, text, author } }` or `{ data: null }`.
