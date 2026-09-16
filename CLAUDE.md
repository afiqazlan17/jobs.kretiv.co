# Kretivco Jobs — CLAUDE.md

Job/CRM/finance dashboard for Kretivco Mediaworks. Laravel 13 + Breeze
(Blade + Alpine.js) + Tailwind + Vite, MySQL in production. Self-hosted on
Exabytes shared cPanel at `jobs.kretiv.co`. Replaces the Next.js/Supabase
app at `kretivco-jobs-dashboard` — full visual/functional parity is the
standing goal; the old app's Vercel/Supabase deployments are paused.

## Hosting reality — read before touching deployment

Full details in `DEPLOYMENT.md`. The short version:

- **No SSH/Terminal.** Every `composer`/`artisan` CLI command runs via a
  one-off Cron Job (schedule a few minutes out, check the log, then
  **delete the cron job** — never leave it recurring).
- **Composer cannot run on the server at all** (cron's outbound network is
  blocked; `composer install` just hangs). When `composer.json` changes,
  build `vendor/` **locally** (`composer install --no-dev
  --optimize-autoloader`, then strip `.git/` and non-runtime `tests/`/
  `docs/` dirs from vendor packages — they bloat the zip far past what's
  needed), zip it, and upload/extract over the old `vendor/` via cPanel
  File Manager.
- **CSS/JS the same way**: `npm run build` locally, upload `public/build/`.
- **Deployment mechanism**: cPanel **Git Version Control** → **Update from
  Remote** (git pull) — NOT the cPanel "Deploy" feature. Document Root
  points directly at the repo's `public/` folder, so a plain pull updates
  the live site immediately; no server-side build step is needed or
  possible.
- **`config:cache`**: never run it. PHP-FPM here appears to serve stale
  cached bytecode of `bootstrap/cache/config.php` even after the file
  changes. Leave config uncached.
- **DNS** for `kretiv.co` is on Cloudflare, not cPanel Zone Editor.
- PHP binary for cron is `/usr/local/bin/ea-php84`, not plain `php`.

## Known gotcha: tests run SQLite, production runs MySQL

CI and local `php artisan test` use SQLite (see `phpunit.xml` /
`tests.yml`). Production is MySQL. This has already caused one real bug:
`orderByRaw("FIELD(role, ...)")` is MySQL-only and silently breaks under
SQLite. Prefer portable Eloquent, e.g. sort by rank in PHP:
`$collection->sortBy(fn ($u) => $rank[$u->role] ?? 99)` instead of
`orderByRaw`. Watch for other MySQL-only SQL the same way.

## Alpine.js: cross-slot state needs a store, not local `x-data`

Blade's `<x-slot name="header">` and the main `{{ $slot }}` render as
**separate DOM subtrees** under the shared layout — they do not share a
local `x-data` scope. Any control that needs to affect something in the
other slot (e.g. a header dropdown toggling a panel in the body) must use
a shared `Alpine.store()` registered once in `resources/js/app.js`
(existing examples: `jobActions`, `settingsUi`). This has bitten this
project twice already.

## Local development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build   # or `npm run dev` for hot-reload
php artisan serve
```

A `.claude/settings.json` SessionStart hook bootstraps a fresh checkout
automatically (composer/npm install, `.env` + key + sqlite migrate if
missing) — the above is mostly redundant with it, but useful to know for
manual setup.

## Code style

`laravel/pint` is installed. A PostToolUse hook runs `vendor/bin/pint
--dirty` automatically after editing a `.php` file. CI also runs
`vendor/bin/pint --test` on every push/PR — keep it passing.

## Deploying

See `DEPLOYMENT.md` for the full runbook. `scripts/build-deploy-package.sh`
automates building the pruned `vendor.zip` and `build.zip` described above.

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
