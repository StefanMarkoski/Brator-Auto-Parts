# Brator Auto Parts

A Laravel 13 backend under a bought HTML storefront theme (Brator). The theme's look is
**fixed** — this project makes it work, it does not redesign it.

Plans live in the academy brain at `symbiosis-brain/projects/brator-auto-parts/plan/`:

- `2026-08-05-database-schema.md` — the authoritative schema
- `2026-08-05-foundation-and-schema-plan.md` — scope, pages, Docker, styling rules, phases

## Running it

The host needs **only Docker** — no PHP, no Composer. Every PHP command runs inside the
`app` container.

```bash
docker compose up -d --build
docker compose exec app php artisan migrate
```

| Service | URL |
|---|---|
| Storefront | http://localhost:8090 |
| Mailpit (receipt emails) | http://localhost:8030 |
| MySQL | `localhost:3310` — db `brator`, user `brator` |
| Redis | `localhost:6382` |

Ports were chosen to stay clear of the Inspectio Sail stack. Check `UID`/`GID` in `.env`
match your `id -u` / `id -g` before the first build, or files the container writes will be
owned by the wrong user.

```bash
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint
docker compose exec app composer require vendor/package
```

## ⚠️ Docker Desktop stale-mount gotcha

On this machine Docker Desktop runs in a VM, and its bind mount **serves stale content for
files edited in place**. Brand-new files appear fine; edited ones do not. If a change seems
to have no effect — a route that doesn't register, a Blade edit that doesn't render:

```bash
docker compose restart app
```

Takes under a second. This is an environment quirk, not a project bug, and it will waste an
hour of your life if you don't know about it.

## The one rule

**No changes to the theme's styling. None.**

- `public/assets/**` is the theme's CSS/JS/images, copied byte-for-byte. **Read-only.** All
  26 CSS/JS files are verified identical to the original.
- **No Vite, no Tailwind, no bundler for theme assets.** nginx serves them straight off
  disk. A build step is the likeliest way a style changes by accident.
- Blade partials are cut at existing HTML boundaries. We slice the markup; we never rewrite
  it. Classes are not renamed, reordered, or tidied.
- `resources/theme-reference/` holds the pristine original template files. They are the test
  baseline — do not edit them.

Enforced by `tests/Feature/ThemeFidelityTest.php`, which renders the homepage and compares
it against the original template. Adding a single CSS class fails it (verified).

The admin panel (phase 6) will use Tailwind, and gets its **own layout, own build, own route
group, sharing nothing** with the storefront — Tailwind's global reset would flatten the
theme anywhere the two met.

## Layout

```
app/Domain/{Catalog,Fitment,Ordering,CatalogImport,Content}/  bounded contexts — see app/Domain/README.md
resources/views/layouts/storefront.blade.php                 the theme shell
resources/views/partials/                                    topbar, header, footer
resources/views/home/sections/                               one partial per homepage strip
resources/theme-reference/                                   pristine original template
docker/                                                      php, nginx, mysql config
```

## Status

**Phase 1 complete.** Docker, Laravel 13 skeleton, DDD context roots, theme cut into Blade,
homepage verified against the original. Phase 2 is the schema.
