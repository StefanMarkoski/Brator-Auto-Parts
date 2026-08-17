# Hosting Brator Auto Parts

For an internal demo on a free Oracle Cloud VM. Roughly 40 minutes, most of it waiting for
`docker compose build`.

The stack deploys as-is — no database port, no object storage, no code changes. That is the
whole reason this target was chosen over Render or Vercel, neither of which can run a
Laravel app that needs MySQL and a disk it can write images to.

---

## What you need first

- An Oracle Cloud account (Always Free tier). It asks for a card to verify identity and
  does not charge it.
- A free subdomain, if you want HTTPS. DuckDNS takes about a minute.
- The repo is private, so the server needs a way to clone it — a GitHub personal access
  token with `repo` scope is the simplest.

---

## 1. The machine

Create an **Ampere A1 (ARM)** instance, Ubuntu 24.04, 4 OCPU / 24 GB. It is free
permanently at that size.

Two things bite people here:

**ARM capacity.** Oracle frequently reports "out of capacity" for free ARM instances in
busy regions. If that happens, try a different availability domain, or a different region
when you create the account. It is not a fault on your side and retrying later often works.

**Oracle's firewall is two firewalls.** Opening a port in the cloud console's *security
list* is only half of it — the Ubuntu image also ships restrictive local iptables rules
that block everything except SSH. Miss the second half and the site is silently
unreachable while everything looks correct.

```bash
# In the OCI console: VCN → Security List → add ingress rules for TCP 80 and 443
# Then, on the instance itself:
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save
```

## 2. Docker

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker "$USER"
newgrp docker          # or log out and back in
```

## 3. The code

```bash
git clone https://github.com/StefanMarkoski/Brator-Auto-Parts.git brator
cd brator
```

## 4. The domain

Point your DuckDNS subdomain at the instance's **public** IP. Confirm it resolves before
going further — Caddy asks Let's Encrypt for a certificate on first boot, and if the name
does not resolve to this machine, issuance fails and the site never comes up.

```bash
dig +short your-name.duckdns.org      # must print the instance's public IP
```

## 5. Configuration

```bash
cp .env.production.example .env
```

Edit `.env` and change every value marked `CHANGE-ME`:

| Key | What it is |
|---|---|
| `APP_URL` | `https://` plus your domain. Laravel builds absolute URLs from it |
| `SITE_DOMAIN` | The same domain, without the scheme — Caddy reads this one |
| `DB_PASSWORD` | Anything but `secret`, which is also the MySQL root password |
| `ADMIN_LOGIN_EMAIL`, `ADMIN_PASSWORD` | **The account that is the entire security boundary.** There is no customer login on this shop, so this one login is all of it. The development default is `password` |

Then generate a key. Never reuse the development one:

```bash
docker compose -f compose.yaml -f compose.prod.yaml run --rm app php artisan key:generate
```

## 6. Build and start

```bash
# Admin panel assets. One-shot container, exits when done.
docker compose -f compose.yaml -f compose.prod.yaml --profile build run --rm node

docker compose -f compose.yaml -f compose.prod.yaml up -d --build
```

The first build takes a while — it compiles PHP extensions from source on ARM.

## 7. Database and storage

```bash
C="docker compose -f compose.yaml -f compose.prod.yaml"

$C exec -T app php artisan migrate --force
$C exec -T app php artisan db:seed --force      # 5,000 products, ~2 minutes
$C exec -T app php artisan storage:link

# Cache config and routes. Do this AFTER .env is final — the cache freezes the values,
# and a later .env edit will appear to be ignored until you clear it.
$C exec -T app php artisan config:cache
$C exec -T app php artisan route:cache
$C exec -T app php artisan view:cache
```

## 8. Check it

```bash
curl -sI https://your-name.duckdns.org | head -1        # HTTP/2 200
curl -s https://your-name.duckdns.org/up                # health check
```

Then in a browser:

- the shop loads over **https** with no certificate warning and no mixed-content errors
- `/admin/login` accepts the credentials you set in step 5
- place a test order and confirm the receipt page renders
- the receipt email is in Mailpit (see below)

---

## Mail

Mailpit stays in the hosted stack on purpose. It is the demo's mailbox: every receipt the
shop "sends" lands there, with no domain verification, no DKIM, no spam folder — and no way
for a demo shop to email a real person by accident.

To reach the inbox, set `MAIL_DOMAIN` to a second subdomain and give it a password:

```bash
docker run --rm caddy:2-alpine caddy hash-password --plaintext 'your-password'
# paste the output into MAIL_PASSWORD_HASH
```

Password-protect it rather than leaving it open: it holds every customer name, email
address and order placed on the demo. Leave `MAIL_DOMAIN` blank to not expose it at all —
you can still read mail with
`docker compose -f compose.yaml -f compose.prod.yaml logs mailpit`.

**If you ever need real email**, the app sends exactly one message, so it is four lines:
point `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` / `MAIL_PASSWORD` at Resend, Postmark or
Brevo. No code changes.

---

## Updating

```bash
cd brator && git pull
docker compose -f compose.yaml -f compose.prod.yaml up -d --build
docker compose -f compose.yaml -f compose.prod.yaml exec -T app php artisan migrate --force
docker compose -f compose.yaml -f compose.prod.yaml exec -T app php artisan config:cache
```

The rebuild matters. The production PHP config sets `opcache.validate_timestamps=0`, so the
running container will keep serving the old code until it restarts — a `git pull` alone
looks like it did nothing.

---

## Backups

One directory and one database:

```bash
C="docker compose -f compose.yaml -f compose.prod.yaml"

$C exec -T mysql mysqldump -ubrator -p"$DB_PASSWORD" brator > brator-$(date +%F).sql
tar czf storage-$(date +%F).tar.gz storage/app/public
```

`storage/app/public` is the one that cannot be regenerated: uploaded product photos, hero
banners, what's-hot images and department photos all live there on local disk. Everything
else comes back from the seeders.

---

## Hosting on a platform instead of a VM (Render, Laravel Cloud)

Everything above assumes a machine with a real disk. Managed platforms do not have one —
the filesystem is wiped on every deploy and restart — so uploaded images have to live in
object storage. That is the *only* app-level difference, and it is one setting:

```dotenv
UPLOADS_DISK=s3
AWS_ACCESS_KEY_ID=…
AWS_SECRET_ACCESS_KEY=…
AWS_BUCKET=brator
AWS_DEFAULT_REGION=auto
AWS_ENDPOINT=https://…            # R2, Supabase Storage, Backblaze — any S3-compatible host
AWS_USE_PATH_STYLE_ENDPOINT=true  # required by most non-AWS providers
AWS_URL=https://cdn.example.com   # the bucket's public base URL
```

`App\Support\ImageUrl` then builds every image URL from that disk. Theme assets under
`/assets` are deliberately unaffected — they ship with the purchased template and are not in
anybody's bucket. `ImageUrlTest` pins both halves of that rule.

Three things this does **not** do for you:

- **It does not move files that already exist.** Copy `storage/app/public` into the bucket
  first, keeping the paths (`products/…`, `hero/…`, `departments/…`, `whats-hot/…`), or
  re-seed after switching.
- **It does not make the bucket public.** The URLs are plain reads with no signing, so the
  bucket (or the `AWS_URL` domain in front of it) has to serve them publicly.
- **`storage:link` becomes pointless.** Harmless to run, does nothing useful.

### Laravel Cloud — the specifics

This is the intended target. It has managed **MySQL**, so there is no database port to do.
It deploys from Git rather than from compose, which means `compose.prod.yaml`, the Caddyfile
and everything in the VM section above are simply unused there — Cloud provides the web
server and TLS itself.

Four things are easy to get wrong, all of them ten-second fixes if you know them first:

1. **Name the bucket's disk `s3`.** Cloud asks you to name the disk when you create the
   bucket, and it injects `FILESYSTEM_DISK` set to that name. `config/filesystems.php`
   already has an `s3` block wired to the `AWS_*` variables Cloud injects, so calling it
   `s3` makes everything line up with no config change. Any other name means adding a
   matching disk block yourself.
2. **Create it as a Public bucket.** The shop builds plain image URLs, not signed temporary
   ones, so a private bucket returns nothing to visitors.
3. **`AWS_URL` is NOT injected.** Cloud injects the credentials, the endpoint, the region
   and the bucket, but the public base URL is only *shown* on the bucket settings page. Copy
   it into the environment's custom variables by hand. Without it, Laravel builds URLs from
   the S3 API endpoint instead of the public one and every image 404s while the upload
   itself reports success.
4. **Never set `visibility` on a write.** Cloud's buckets are Cloudflare R2, which manages
   visibility per bucket and rejects per-object ACL headers with `NotImplemented`. The code
   here deliberately passes none — worth knowing before you add an upload path.

`UPLOADS_DISK` does not need to be set at all: it defaults to `s3` as soon as `AWS_BUCKET`
is present, precisely so that attaching a bucket cannot leave uploads writing to a disk that
is wiped on the next deploy.

**Mail.** Mailpit does not exist on Cloud. Set `MAIL_MAILER=log` — receipts then land in the
log viewer, and the on-screen receipt page is the shopper's actual confirmation anyway. If
you want real email, the four `MAIL_*` values point at Resend, Postmark or Brevo and nothing
else changes; the app sends exactly one message.

**Seeding.** Run `php artisan db:seed --force` once from Cloud's command runner, with
`ADMIN_LOGIN_EMAIL` and `ADMIN_PASSWORD` already set in the environment — see the seeder note
above. `storage:link` is pointless once uploads are on the bucket.

**Images, and a correction to what this file used to claim.** The seeder writes NO image
files. Every path it sets points at something committed in the repo — `assets/images/shop/…`
for products, `assets/images/slider/…` for the hero, `assets/images/hot/…`, and three
category icons under `app/images/`. So a freshly seeded deployment has working images with
no bucket attached at all, and `UPLOADS_DISK` only starts to matter the moment somebody
uploads something through the admin panel.

That is worth knowing before you spend an afternoon on object storage you do not yet need.
The bucket is for what happens AFTER the seed: hero banners, "What's Hot" images, department
photos and product photos added through the admin. Without it, those land on the ephemeral
disk and disappear on the next deploy — the uploads appear to succeed and the images vanish
later, which is the confusing version of this failure.

### Render

No managed MySQL. Either bring your own MySQL or port to Postgres, and that port is **not** a
config flip: a MySQL-only `FULLTEXT` migration, `DATE_FORMAT` in the admin dashboard, two
seeders using `UPDATE … JOIN … SET`, and the quiet one — MySQL's `LIKE` is case-insensitive
and Postgres's is not, so search silently returns fewer results without erroring anywhere.

## What is deliberately not done

Recorded so nobody assumes it was overlooked. This is an internal demo, and each of these
is a real decision rather than an omission:

- **No queue worker.** Nothing in the app implements `ShouldQueue` — checked, zero. The
  one email sends synchronously and is wrapped so a failure cannot break an order.
- **No CDN, no object storage.** Images are served off the box's disk. Fine at demo
  traffic; it is also what lets the stack deploy unchanged.
- **No CSP header.** The storefront is a purchased theme built on inline styles and
  scripts, so a strict policy would break it. The cheap headers are set in the Caddyfile.
- **Mailpit instead of a mail provider.** See above — a feature for a demo, not a shortcut.
- **The open findings from the August audit are still open.** The ones that made the shop
  unsafe to expose are fixed (the pagination amplification, the shared rate-limit counter,
  the exposed database and session store, the baked-in debug output, the checkout mail
  swallow). The rest — the mini-cart totals not reconciling, the vehicle picker not
  clearing lower levels, the brand purge, the receipt URLs having no authorization — are
  live in this deployment. Do not demo the mini-cart totals.
