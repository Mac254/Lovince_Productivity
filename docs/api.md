# API

This page covers the conventions and the core endpoints. Every module documents its own endpoints in `apps/api/app/Modules/<Module>/README.md`, and `php artisan route:list --path=api/v1` lists them all.

Base path `/api/v1`. All endpoints except `POST /auth/login` require an authenticated session. Writes need the `X-XSRF-TOKEN` header (taken from the `XSRF-TOKEN` cookie; `GET /sanctum/csrf-cookie` issues one).

## Conventions

- Success: `{ "data": … }`; lists that paginate add `links` and `meta` (`current_page`, `last_page`, `per_page`, `total`).
- `201` on create, `204` on delete and logout.
- `401` unauthenticated · `404` missing or not yours · `409` changed elsewhere since the copy the change was based on · `419` CSRF mismatch · `422` validation `{ message, errors: { field: [msg] } }` · `429` throttled.

## Auth

- `POST /auth/login` `{ email, password, remember }` → same body as `/auth/me`. Throttled (5 failures per email+IP per minute).
- `GET /auth/me` → the signed-in user and this device's sign-in:

```json
{
  "data": { "id": 1, "name": "Stephen", "email": "…", "avatar_url": null },
  "meta": {
    "session": { "remembered": true, "expires_at": "2026-11-27T10:00:00+00:00", "idle_timeout_minutes": 120, "remember_days": 30, "timezone": "Africa/Nairobi" },
    "settings": { "…": "…" }
  }
}
```

  `remembered` means “keep me signed in” is active: an encrypted, HTTP-only remember cookie restores the session after it idles out and is reissued for another `remember_days` each time that happens (sliding). Without it, the sign-in ends after `idle_timeout_minutes` of inactivity. `timezone` is the account's timezone (`APP_TIMEZONE` until the user picks one with `PATCH /profile { timezone }`); the API and the web app start each day there, so today, overdue, streaks and reports match on any device. `settings` are the resolved settings (see the Settings module). `401` when signed out.
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

Task body (`POST` requires `title`; `PATCH` accepts any subset): `title`, `description`, `status`, `priority`, `category`, `start_date`, `due_date` (`YYYY-MM-DD`, due ≥ start), `goal_id` (must be your goal or `null`), `task_type_id` (must be your type or `null`). Filter with `?task_type_id=`. `PATCH` also accepts `completed_at` (`YYYY-MM-DD`, to complete on an earlier day) and `base_updated_at` (the `updated_at` the change was based on; `409` if the task changed since, which is how offline changes avoid overwriting newer ones).

Response fields include `is_overdue`, `completed_at`, `goal { id, title, tone }` and `type { id, name, tone }`.

## Task types

Your own kinds of task, shown next to the built-in categories. `GET /task-types` (with `tasks_count`) · `POST /task-types` · `PATCH /task-types/{id}` · `DELETE /task-types/{id}`. Body: `name` (1–40 chars, unique per user, whitespace collapsed), `tone` (a goal tone, default `blue`). Up to 30 types. Deleting a type keeps its tasks and clears their `task_type_id`.

## Goals

`GET /goals?status=&timeframe=` · body: `title`, `description`, `status` (`active`, `on_hold`, `completed`, `cancelled`), `timeframe` (`weekly`, `long_term`), `start_date`, `target_date` (target ≥ start). Responses include `progress`, `tasks_count` (non-cancelled) and `completed_tasks_count`. Deleting a goal keeps its tasks.

## Dashboard

`GET /dashboard` returns `date`, `week`, `stats` (`due_today`, `overdue`, `in_progress`, `open`, `completed_today`, `completed_this_week`, `completed_total`, `active_goals`, `week_due_total`, `week_due_completed`, `weekly_completion_rate` — `null` when nothing is due this week), the lists `today_tasks`, `overdue_tasks`, `completed_today_tasks`, `upcoming_tasks` (next 14 days), `recent_activity`, `weekly_goals`, `active_goals`, an `integration` summary, `streak` (`current`, `best`, `active_today`), `achievements` (`this_week`, `total`, `latest` — up to three), `journal` (`written_today`, `this_week`) and a `quote` (or `null`). The web dashboard reads the month's money from `GET /accounting/dashboard`.

## Accounting

Personal money on a double-entry ledger, all under `/accounting`. Amounts are sent as decimal strings (`"1500.50"`) and returned as integer minor units with their `currency` (`KES`, `UGX`, `TZS`, `RWF`, `ETB`, `NGN`, `ZAR`, `USD`, `EUR`, `GBP`).

- **Accounts** `GET|POST /accounts` · `GET|PATCH|DELETE /accounts/{id}`. Body: `name`, `type` (`cash`, `bank`, `mobile_money`, `savings`, `sacco`, `money_market`, `investment`, `credit`, `other`), `currency`, `opening_balance`, `archived`. Responses include `balance` and `available_balance` (less pending outflows). Only an account with no transactions can be deleted.
- **Categories** `GET|POST /categories` · `PATCH|DELETE /categories/{id}`. Body: `kind` (`income`, `expense`; create only), `name`, `archived`. Default or used categories are archived, not deleted.
- **Transactions** `GET /transactions?search=&type=&status=&account_id=&category_id=&currency=&from=&to=&per_page=` · `POST /transactions` (needs an `Idempotency-Key` header, 8–64 of `A-Z a-z 0-9 _ -`; repeating it returns the first transaction) · `GET|PATCH /transactions/{id}` · `POST /transactions/{id}/void` · `POST /transactions/{id}/reverse` · `GET /transactions/export` (CSV, same filters, 10 a minute). Body: `type` (`income`, `expense`, `transfer`, `refund`, `adjustment`, `interest`, `fee`), `account_id`, `to_account_id` (transfers), `category_id`, `direction`, `amount`, `status` (`pending`, `posted`), `date`, `description`, `notes`, `reference`. Transactions are never deleted; a posted one is corrected by editing, voiding or reversing.
- **Budgets** `GET /budgets?month=YYYY-MM` (this month by default; `meta` gives `month`, `from`, `to`) · `POST /budgets` · `PATCH|DELETE /budgets/{id}`. Create with `category_id` (your unarchived expense category), `currency` and `amount`, one per category and currency; update takes `amount` only. Responses add `spent` and `remaining` for the month.
- **Recurring** `GET|POST /recurring` · `PATCH|DELETE /recurring/{id}`. The transaction body (types `income`, `expense`, `transfer`, `interest`, `fee`) plus `frequency` (`weekly`, `monthly`, `yearly`), `start_date`, `end_date` (≥ start, or `null`) and `paused`. Due dates are posted every fifteen minutes; a new rule catches up on past dates, an edited or resumed one carries on from today. Responses include `next_date` and `occurrences`.
- **Overview** `GET /dashboard?from=&to=&currency=` (this month by default): balances, `income`, `expenses`, `net_cash_flow`, `previous_totals`, `top_expenses`, `pending`, `recent`, a six-month `trend`, `budgets` (`amount`, `spent`, `over`, `items`), `upcoming` (recurring payments in the next 14 days) and `insights`.
- **Reports** `GET /reports/income-statement`, `GET /reports/cash-flow` and `GET /reports/trend`, each taking `from`, `to` (both or neither) and `currency`.

## Quotes

`GET /quotes/random?exclude={id}` → `{ data: { id, text, author } }` or `{ data: null }`.
