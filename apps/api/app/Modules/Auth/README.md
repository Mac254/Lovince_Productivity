# Auth

Signing in and out of the first-party web app with Sanctum cookie sessions (CSRF-protected), and "keep me signed in".

- `POST /api/v1/auth/login` (throttled by the `login` limiter), `POST auth/logout`, `GET auth/me`.
- `AuthSession` describes the current sign-in to the client; `RenewRememberedLogin` (in the `api` middleware group, see `bootstrap/app.php`) slides the remember cookie.
- `LogAuthEvents` logs logins, failures, lockouts and logouts, never passwords.
- Owns the `personal_access_tokens` migration (Sanctum).

Depends on Users (`UserResource`) and Settings (`/auth/me` carries the resolved settings).
