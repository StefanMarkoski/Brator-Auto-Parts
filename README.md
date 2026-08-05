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

## Database

36 tables across five bounded contexts. The authoritative design, with the reasoning for
every index and denormalisation, is the schema plan in the academy brain.

```bash
docker compose exec app php artisan migrate:fresh --seed   # ~21 seconds
```

Seeds 5,000 products, 2,000 vehicle variants, **148,908 fitment rows**, 500 receipts. The
volume is the point: a bad index on the hot path is invisible at a thousand rows.

Three things worth knowing before you touch a migration:

1. **Use `$table->ulidPrimary()` / `$table->ulidColumn()`, never `ulid()` / `foreignUlid()`.**
   Laravel's versions create utf8mb4 `char(26)` — 104 bytes inside every index that includes
   the column. Ours are `ascii`, so 26. A test fails if a plain `foreignUlid()` slips in.
2. **`product_vehicle_fitments` has no `id`.** Its primary key is
   `(vehicle_variant_id, product_id)`, vehicle first, so "parts for my car" is a range scan
   over the clustered index. Treat it as a link table: `insert()`/`upsert()`, never `save()`.
3. **Listing queries select `Product::LISTING_COLUMNS`, never `*`.** Pulling the description
   columns into a 24-card page is the easiest way to make the catalogue feel slow.

`QueryPlanTest` runs `EXPLAIN` and asserts which index MySQL picks, so a change that turns a
range scan into a table scan fails the suite instead of surfacing in production.

## Admin panel

TailAdmin (Tailwind v4 + Alpine), at **http://localhost:8090/admin** — sign in with the
seeded staff account (`stefan.m@xgate.io` / `password`).

Assets are built separately from the storefront and must be rebuilt after changing any
admin view's classes:

```bash
docker compose --profile build run --rm node
```

**The isolation rule, and why it is not negotiable:** Tailwind ships a global CSS reset
that would flatten the Brator theme anywhere the two met. So:

- `admin/layouts/admin.blade.php` is the **only** view that references `@vite`
- `vite.config.js` builds admin assets and nothing else
- Tailwind's `@source` scans `resources/views/admin` only
- the two sides share no layout, no partial, not even a `<head>`

`AdminAssetIsolationTest` asserts every one of those, in both directions. Don't work
around it — if an admin screen needs storefront data, pass it through a controller.

## Status

**Phases 1–6 complete.** Docker, Laravel 13 skeleton, theme cut into Blade and verified
against the original, full schema, dynamic homepage and listings, working filters
including shop-by-vehicle, basket with a fake checkout that emails a real receipt, and
the TailAdmin staff panel. 77 tests passing.

Not built yet: the import *runner* (the override rule it must respect is done and
enforced), review submission, blog pages, and the compare page.
