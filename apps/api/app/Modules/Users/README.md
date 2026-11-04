# Users

The account everything else belongs to: the `User` model, profile, password and avatar.

- `PATCH /api/v1/profile`, `PUT profile/password`, `GET|POST|DELETE profile/avatar`.
- `php artisan focus:create-user`, and `OwnerSeeder` (the account from `FOCUS_OWNER_*`).
- Sets the app's password rules (`Password::defaults()`).
- Each account's timezone (`users.timezone`, `null` follows `APP_TIMEZONE`): `AccountTimezone` applies it for the whole request once the user is known, and around work done for an account outside a request (`AccountTimezone::run()`). `PATCH profile` with `timezone` (an IANA name) moves every stored time of the account to the new zone, found from the schema (tables with `user_id`, and their cascading children), so the moments they record stay the same.
- Owns the `users`, `password_reset_tokens` and `sessions` tables.

Depends on no other module. Modules add the user's relation to their own records (`tasks`, `goals`, `wallpapers`…) from their providers with `User::resolveRelationUsing()`.
