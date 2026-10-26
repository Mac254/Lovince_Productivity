# Security

## Authentication and sessions

- Laravel Sanctum stateful (cookie) authentication. The SPA is first-party: requests are stateful when their `Origin`/`Referer` matches the host serving the app (`Sanctum::currentRequestHost()`), or a host in `SANCTUM_STATEFUL_DOMAINS`.
- Login requests without a first-party origin get `403` — there is no token login.
- Passwords hashed with bcrypt. Accounts are created with `php artisan focus:create-user` (12+ chars, mixed case, number); there is no public registration.
- Login is throttled per email+IP (5 failures/min) and per IP (10/min). Sessions are regenerated on login and invalidated with a fresh CSRF token on logout.
- Session cookies are HTTP-only, `SameSite=Lax`, encrypted at rest, and `Secure` when `SESSION_SECURE_COOKIE=true`.
- “Keep me signed in” uses Laravel’s encrypted, HTTP-only remember cookie for `FOCUS_REMEMBER_DAYS` (default 30), renewed whenever it restores a session; logout clears it and rotates the user’s remember token.

## Authorization and data isolation

- Lists are built from `whereBelongsTo($user)`; single records go through `TaskPolicy` / `GoalPolicy`, which respond **404** for records you don’t own.
- Update requests authorize before validating, so other users’ records can’t be probed through validation messages.
- `user_id` is never fillable; `goal_id` must reference one of your goals.
- Feature tests cover cross-user reads, updates and deletes.

## Input and output

- Every request validated with Form Requests: enums, `Y-m-d` dates, lengths, date ordering.
- Eloquent / bound parameters only; raw SQL fragments are built from enum constants, never input.
- The frontend renders user text with interpolation; `v-html` fails lint.

## Browser

- CSRF protection on all writes (`X-XSRF-TOKEN`).
- Headers on every response: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`.
- Production adds a strict `Content-Security-Policy` (self-only scripts, fonts, connections; no framing) and HSTS over HTTPS.
- Fonts and icons are bundled — no third-party requests. `noindex` and `robots.txt` keep the app out of search engines.
- No secrets in frontend environment variables; the SPA has none.

## Operations checklist

- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `SESSION_SECURE_COOKIE=true`.
- Web root `apps/api/public`; run `pnpm build`, then `php artisan config:cache route:cache`.
- `.env` is never committed; `.env.example` holds placeholders only.
- Back up the database (for SQLite, `apps/api/database/database.sqlite`) and test a restore.
- Keep dependencies updated (`composer audit`, `pnpm audit`). `.npmrc` disables install scripts.
