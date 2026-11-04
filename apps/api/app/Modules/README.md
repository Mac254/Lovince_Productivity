# Modules

The API is a modular monolith: each folder here owns one business area, with the parts it needs (`Http`, `Models`, `Policies`, `Database/Migrations`, `Routes/api.php`, `Tests`…). Each module's README says what it owns.

## Registration

`App\Providers\ModuleServiceProvider` registers every module once: a folder with `Providers/<Module>ServiceProvider.php` is an active module (`ModuleRegistry`). Module providers extend `App\Shared\Support\ModuleProvider`, which loads by convention:

- `Routes/api.php` under `/api/v1` with the `api` middleware group (routes add `auth:sanctum` and `throttle:api` themselves),
- `Database/Migrations` (filenames keep their original timestamps, so order and history are unchanged),
- `Config/*.php` (merged under the file name),
- the policies in `$policies`.

`Settings/*` subclasses of `SettingsModule` join the settings API, and `Permissions/permissions.php` declares the module's permissions as `module.action`; `PermissionRegistry` collects them (`php artisan focus:permissions`). Nothing assigns permissions yet.

## Dependencies

Modules depend on each other in one direction only:

```
Users ← Settings ← Auth
Users ← Goals ← Tasks ← Blockers ← Journal
Users ← Quotes
Tasks ← Clock
Tasks ← Planner
Tasks, Goals, Blockers, Journal, Clock ← Reviews
Tasks, Goals, Blockers, Journal ← Search
Tasks, Goals, Suggestions ← Achievements
Tasks, Goals, Blockers, Journal, Clock, Reviews, Planner, Suggestions, Achievements, Reports ← Backup
Tasks, Goals, Journal, Planner ← Suggestions
Tasks, Goals, Blockers, Journal, Quotes, Clock, Reviews ← Dashboard, Metrics, Reports (read models)
```

Where the arrow would point back, the lower module offers a contract or an event instead: Goals' `GoalProgress` contract is implemented by Tasks, Tasks' `WorkLogChanged` event is handled by Reports, and relations to a higher module's records (`User::tasks`, `Goal::tasks`, `Task::blockers`) are added by that module with `resolveRelationUsing()`.

Outside the modules: `app/Shared` holds what any module may use and depends on no module (the base controller, app-wide middleware, `ModuleProvider`, the settings base classes, `ActivityObserver` and `OwnerPolicy`, which lets only a record's owner view, change or delete it and answers 404 to everyone else); `app/Support/Contract.php` gathers the enums and limits shared with the TypeScript apps (`pnpm types`); `database/migrations` keeps the framework tables and one migration that spans several modules; `tests/Feature` keeps app-wide and cross-module tests.
