# Users

The account everything else belongs to: the `User` model, profile, password and avatar.

- `PATCH /api/v1/profile`, `PUT profile/password`, `GET|POST|DELETE profile/avatar`.
- `php artisan focus:create-user`, and `OwnerSeeder` (the account from `FOCUS_OWNER_*`).
- Sets the app's password rules (`Password::defaults()`).
- Owns the `users`, `password_reset_tokens` and `sessions` tables.

Depends on no other module. Modules add the user's relation to their own records (`tasks`, `goals`, `wallpapers`…) from their providers with `User::resolveRelationUsing()`.
