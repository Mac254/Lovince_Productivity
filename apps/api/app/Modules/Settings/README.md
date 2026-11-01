# Settings

Per-account settings saved on the user (`users.settings`), and the registry that joins every module's settings.

- `GET|PATCH|DELETE /api/v1/settings`.
- `SettingsRegistry` finds every `SettingsModule` in a module's `Settings` folder (via `ModuleRegistry`), validates changes and stores only what differs from the defaults.
- Owns the General and Appearance settings, and the `add_settings_to_users` migration.

To add settings to another module, put a `SettingsModule` subclass in that module's `Settings` folder (built from `App\Shared\Support\Settings`). `pnpm types` then adds it to `packages/types`.
