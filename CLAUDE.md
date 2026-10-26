# Focus — agent notes

pnpm monorepo (Node 26.12+, pnpm 12.15+): `apps/web` (Vue 3 + TS SPA), `apps/api` (Laravel 13, `/api/v1`), `packages/{tokens,ui,types,api-client,motion}`. See README.md and docs/.

- Design: components consume tokens, never raw values. Add a token in `packages/tokens/src`, run `pnpm tokens`. The drift test rejects hex, arbitrary values, off-scale spacing and removed Tailwind defaults.
- Reuse `@focus/ui` primitives/components/patterns before adding UI; product components live in `apps/web/src/features`.
- Shared enums/limits come from `apps/api/app/Support/Contract.php`; run `pnpm types` after changing them.
- HTTP only through `api.*` from `@focus/api-client` (axios, cookie sessions, CSRF). Never tokens in localStorage or `v-html`.
- Scope task/goal queries with `whereBelongsTo($request->user())`; authorize single records via the policies.
- Checks: `pnpm typecheck`, `pnpm test`, `pnpm lint`, `pnpm test:api`, `apps/api/vendor/bin/pint`.
