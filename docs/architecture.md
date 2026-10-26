# Architecture

## Runtime

```
Browser ──► (dev) Vite :5173 ──proxy /api, /sanctum──► Laravel :8000
Browser ──► (prod) Laravel ── serves public/app/index.html for non-API paths
```

The SPA and API always share one origin. That keeps authentication on HTTP-only session cookies with CSRF protection and avoids CORS entirely.

## Frontend (`apps/web`)

- `app/router` — routes and the auth guard. The guard resolves the session once via `GET /auth/user`.
- `features/*` — feature components and their logic (`useTaskActions`, `useTaskEditing`).
- `stores` — Pinia only where state is shared: `auth`, `goals` (goal picker everywhere), `dashboard` (one snapshot for the day strip, dashboard and today pages; concurrent loads are de-duplicated).
- `lib` — date and label helpers. Dates are `YYYY-MM-DD` strings parsed as local calendar dates.

## Packages

| Package | Folders | Notes |
|---|---|---|
| `@focus/tokens` | `primitives/` `semantic/` `component/` `build/` | Zero dependencies. TS source → generated CSS. |
| `@focus/types` | `generated/` `models/` `api/` | `generated/contract.ts` is written by Laravel (`pnpm types`); hand-written models import its enums. |
| `@focus/api-client` | `client/` `errors/` `endpoints/` | Axios. One instance, typed endpoint groups (`api.tasks.list(...)`). |
| `@focus/ui` | `styles/` `primitives/` `components/` `patterns/` | See [design-system.md](design-system.md). |
| `@focus/motion` | `presets/` `composables/` | GSAP composables driven by motion tokens, scoped with `gsap.context`. |

### Shared contract (Laravel → TypeScript)

`apps/api/app/Support/Contract.php` lists every value both sides must agree on: task statuses, priorities, categories, goal statuses and timeframes, due filters, sort keys and validation limits. The Form Requests read their limits from it, and `php artisan focus:types` writes `packages/types/src/generated/contract.ts`:

```ts
export const TASK_STATUSES = ['not_started', 'in_progress', 'blocked', 'completed', 'cancelled'] as const;
export type TaskStatus = (typeof TASK_STATUSES)[number];
export const LIMITS = { task: { title: 200, description: 5000 }, … } as const;
```

`tests/Feature/ContractTest.php` runs `focus:types --check`, so changing a PHP enum without regenerating fails CI.

### API client

- `createHttpClient()` builds the axios instance: base `/api/v1`, cookies, `X-XSRF-TOKEN` from the cookie, 15s timeout, empty query params stripped.
- A 419 refreshes `/sanctum/csrf-cookie` and retries once; a 401 calls the `onUnauthorized` handler.
- Every failure is normalised to `ApiError` with a `kind` (`validation`, `unauthorized`, `not_found`, `rate_limited`, `server`, `network`, `timeout`, `cancelled`, …), a message that is safe to display, `fieldErrors()` for forms and `retryAfter` for throttling. Raw server messages are only shown for validation and throttling.
- Endpoints accept `{ signal }`; `useAsync` aborts the previous request on each run so fast filtering never renders stale results.

## Backend (`apps/api`)

- Controllers are thin: validate (Form Request) → authorize (Policy) → act → return an API Resource.
- `Services/DashboardSummary` computes all dashboard numbers from persisted data.
- `Actions/DeleteGoal` detaches tasks and deletes the goal in one transaction.
- `Task` sets and clears `completed_at` automatically as status changes.

## Data model

| Table | Notes |
|---|---|
| `users` | Laravel defaults |
| `goals` | `user_id` FK (cascade on user delete), `status`, `timeframe` (`weekly` / `long_term`), `start_date`, `target_date`; index on `(user_id, status)` |
| `tasks` | `user_id` FK (cascade), `goal_id` FK **nullOnDelete**, `status`, `priority`, `category`, `start_date`, `due_date`, `completed_at`; indexes on `(user_id, status)`, `(user_id, due_date)`, `(user_id, completed_at)` |
| `quotes` | unique `text`, nullable `author` |
