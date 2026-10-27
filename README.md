# Focus

A secure, minimal productivity dashboard for managing tasks, goals, deadlines and personal progress.

Focus is a modular monorepo: a Vue 3 + TypeScript frontend, a Laravel API, shared UI components, shared TypeScript contracts, a central API client and reusable GSAP motion utilities. The interface uses the Quantus palette — Haiti, Blue Chalk, Electric Violet and Turbo — with clear typography, notched cards and accessible interaction states.

## Features

- **Dashboard** — KPI cards, today's tasks with quick filters, progress, highlights (starred tasks), deadlines, open blockers, goals, integration tracker, recent activity, quote
- **Tasks** — search, filters, pagination, highlights, and a detail page with description, blockers, dates and related tasks
- **Goals** — weekly and long-term, each with a **tone** (green, blue, amber, violet, rose, cyan) that colours its cards and tasks; detail page with tasks grouped by state
- **Blockers** — log what's stopping progress (optionally against a task, marking it blocked), resolve and reopen
- **Journal** — one private entry per day with autosave; searchable
- **Daily reports** — stored snapshots generated every night at 23:55 (and on demand): completed, carried over, overdue, blockers, goal progress, the day's work log and journal. A saved report's work log stays up to date when entries for that day are added, edited or removed.
- **Metrics** — ApexCharts: completed vs created trend, six-month activity heatmap, breakdowns by priority and type, on-time rate, median cycle time
- **Focus clock** — full-screen clock with built-in wallpapers, 12/24h, next task and today's progress
- **Settings** — key–value rows for profile (photo, name, email), start page (Dashboard, Today or focus clock), appearance (dark, light or system theme, reduce motion, daily quote), clock and timer (time format, seconds, ticking sound, chime, automatic breaks, desktop notifications), security (password, this device's sign-in) and a reset for this device's preferences. The top bar also has a dark/light switch
- Development and integration tracked as separate deliverables; random quotes; animated icons; responsive layout

## Stack

| | |
|---|---|
| Frontend | Vue 3, TypeScript, Vite, Tailwind CSS 4, Vue Router, Pinia, Axios, ApexCharts, GSAP, Iconify (Solar via unplugin-icons), Vitest, Vue Test Utils |
| Backend | PHP 8.3+, Laravel 13, Sanctum (session auth), Eloquent, Form Requests, Policies, PHPUnit, Pint |
| Database | SQLite locally; MySQL or PostgreSQL in production |
| Tooling | Node 26.12+, pnpm 12.15+ workspaces, Composer, ESLint, Prettier |

## Repository structure

```text
focus/
├── apps/
│   ├── web/                    Vue SPA
│   │   └── src/
│   │       ├── app/router/
│   │       ├── layouts/
│   │       ├── pages/
│   │       ├── features/       dashboard, tasks, goals, productivity, quotes
│   │       ├── composables/
│   │       ├── stores/         auth, goals, dashboard (Pinia)
│   │       ├── lib/            dates, labels
│   │       ├── styles/
│   │       └── main.ts
│   └── api/                    Laravel API
│       ├── app/
│       │   ├── Http/Controllers/Api/V1/
│       │   ├── Http/Requests/  Http/Resources/
│       │   ├── Models/  Policies/  Enums/
│       │   ├── Services/       DashboardSummary
│       │   └── Actions/        DeleteGoal
│       ├── database/           migrations, factories, seeders
│       ├── routes/  tests/
├── packages/
│   ├── tokens/                 @focus/tokens — primitives/ semantic/ component/ → css/
│   ├── ui/                     @focus/ui — styles/ primitives/ components/ patterns/
│   ├── types/                  @focus/types — generated/ (from Laravel) models/ api/
│   ├── api-client/             @focus/api-client — client/ errors/ endpoints/ (axios)
│   └── motion/                 @focus/motion — presets/ composables/ (GSAP)
├── docs/                       architecture, design system, API, security
├── pnpm-workspace.yaml
└── package.json
```

| Package | Responsibility |
|---|---|
| `@focus/tokens` | Design decisions as tokens: colour ramps → semantic roles → component tokens, type scale, spacing, radius, elevation, motion. Generates CSS; contrast-tested |
| `@focus/ui` | Primitives (Stack, Text, Heading, Icon…), components (Button, Card, Dialog, Select, DropdownMenu…) and patterns (PageHeader, EmptyState, ConfirmDialog…) built only on tokens |
| `@focus/types` | Enums and limits generated from Laravel, plus Task, Goal, Quote, Dashboard, input and filter types |
| `@focus/api-client` | Axios client: CSRF, 419 retry, 401 hook, cancellation, typed `ApiError`, endpoint groups (`api.tasks`, `api.goals`, …) |
| `@focus/motion` | `useStaggerIn`, `useAnimatedNumber`, reduced-motion detection — durations and easings from tokens |

Packages are consumed as TypeScript/Vue source — no build step. TypeScript types do not validate runtime data; Laravel remains the source of truth for validation and authorization.

## Local development

Prerequisites: Node **26.12+** (see `.node-version`), pnpm **12.15+** (`corepack enable` picks the pinned version), PHP 8.3+ with `pdo_sqlite` (Debian/Kali: `sudo apt install php8.4-sqlite3`), Composer.

```bash
pnpm install

cd apps/api
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
```

Set your account in `apps/api/.env` (git-ignored — never commit it). Quote the password if it contains `#`:

```dotenv
FOCUS_OWNER_NAME="Your Name"
FOCUS_OWNER_EMAIL=you@example.com
FOCUS_OWNER_PASSWORD="your-password"
FOCUS_SEED_DEMO=true          # optional sample goals and tasks (local only)
```

```bash
php artisan migrate --seed        # tables, quotes, your account (+ demo data)
php artisan focus:doctor          # checks PHP extensions, database and setup
cd ../..

pnpm dev                          # focus:doctor, then Laravel :8000, Vite :5173, live logs, scheduler, queue worker
```

### Scheduler

Daily reports are queued by `php artisan focus:daily-report` at 23:55 (`APP_TIMEZONE`) and stored by the queue worker (`GenerateDailyReport`, retried up to 3 times). At 00:05, `focus:daily-report --missing=7` finalises yesterday's report (so work finished after 23:55 counts) and fills in any day that has none. Every figure is rebuilt from timestamps as the day stood at its end, so a report written later still describes that day; only starred tasks use their current state. `pnpm dev` runs `schedule:work` and `queue:listen`; in production add a cron entry: `* * * * * cd /path/to/apps/api && php artisan schedule:run` and keep `php artisan queue:work` running. The installed app does both as the `focus-scheduler` and `focus-queue` services. "Generate today’s report" on the Reports page stays immediate. Backfill a day with `php artisan focus:daily-report --date=YYYY-MM-DD`.

### Seeders

| Seeder | What it does |
|---|---|
| `QuoteSeeder` | The curated quote collection. Idempotent. |
| `OwnerSeeder` | Creates the `FOCUS_OWNER_*` account. Never modifies an existing account; refuses a password that fails the policy in production. |
| `DemoSeeder` | Local/testing only, and only for an account with no goals or tasks: 4 goals and ~19 tasks dated around today, including completed development with pending integration. |

`php artisan migrate:fresh --seed` rebuilds everything from scratch (deletes all data). Without `FOCUS_OWNER_*`, use `php artisan focus:create-user`.

Open **http://localhost:5173**. Vite proxies `/api` and `/sanctum` to Laravel, so the browser stays same-origin and cookie auth behaves exactly as in production. Set `APP_TIMEZONE` in `apps/api/.env` (e.g. `Africa/Nairobi`) so “today” matches your day.

### Production-style run

```bash
pnpm build                        # typecheck + build into apps/api/public/app
php apps/api/artisan serve        # http://localhost:8000 serves the SPA and API
```

## Install on your computer

Run Focus as an app on your computer — it runs only when you start it, no terminal needed:

```bash
pnpm app:install
```

This builds the app, adds **Focus** to your app menu, and registers three user services: `focus-app` (http://localhost:8765), `focus-scheduler` (the 23:55 daily report) and `focus-queue` (background jobs). Nothing starts at login: opening **Focus** from the app menu starts all three and then opens the window; `pnpm app:stop` stops them. Because the 23:55 report only runs while Focus is open, starting it saves any reports missing from the last 7 days. `pnpm app:autostart on` opens (and so starts) Focus at login if you want that back.

Sign in once with **Keep me signed in** (on by default) and Focus stays signed in on that device until you sign out, or until it goes unused for `FOCUS_REMEMBER_DAYS` (30 by default — every visit renews it).

| Command | |
|---|---|
| `pnpm app:open` | Start Focus if needed and open its window (what the menu entry does) |
| `pnpm app:start` / `pnpm app:stop` | Start or stop the services without opening a window |
| `pnpm app:autostart on\|off` | Start and open Focus at login, or not (off by default) |
| `pnpm app:update` | Rebuild after code changes; restarts Focus only if it was running |
| `pnpm app:status` / `pnpm app:logs` | Service state / live logs |
| `pnpm app:uninstall` | Remove the services and launcher (data is kept) |

The installed copy runs in production mode (debug off, strict security headers) on 127.0.0.1 only, sharing the same database as `pnpm dev`. Once started, Focus keeps running until you stop it or log out. Set `FOCUS_PORT` before installing to use another port. The service worker never caches `/api` responses, so your data stays out of the browser cache.

## Quality checks

```bash
pnpm typecheck     # app, tests and config files
pnpm test          # Vitest: web, ui, api-client, tokens (contrast + drift checks)
pnpm lint
pnpm build
pnpm test:api      # php artisan test (includes the shared-contract check)
cd apps/api && vendor/bin/pint
```

When you change a design token: `pnpm tokens`. When you change a Laravel enum or limit: `pnpm types`.

## API

Versioned under `/api/v1`, session-authenticated. See [docs/api.md](docs/api.md).

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/auth/login` · `/auth/logout` | Sign in (throttled; `remember` keeps the device signed in) · sign out |
| `GET` | `/auth/me` | Current user + this device's session (`meta.session`) |
| `GET` | `/dashboard` | Dashboard summary |
| `GET` `POST` | `/tasks` | List and filter · create |
| `GET` `PATCH` `DELETE` | `/tasks/{task}` | Retrieve · update · delete |
| `GET` `POST` | `/goals` | List · create |
| `GET` `PATCH` `DELETE` | `/goals/{goal}` | Retrieve · update · delete (tasks are kept) |
| `GET` | `/quotes/random` | Random quote (`?exclude=id`) |

## Security

Summary — details in [docs/security.md](docs/security.md):

- Sanctum cookie sessions; CSRF on every write; session regenerated on login, invalidated on logout; nothing sensitive in `localStorage`.
- Login throttling; per-user API rate limit.
- Every query scoped to the signed-in user; policies return 404 for other users’ records; client-supplied `user_id` ignored; `goal_id` ownership validated.
- Server-side validation with Form Requests; Eloquent / parameterised queries only.
- Descriptions rendered as text; `v-html` is a lint error.
- Security headers on every response, strict CSP and HSTS in production, encrypted sessions, `noindex`.

## Definition of done

Real backend data · server-side validation and authorization · loading, empty and error states · desktop and mobile layouts · keyboard access · automated tests · no secrets exposed · docs match the implementation.
