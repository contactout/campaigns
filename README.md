# MMOS

**Mail Merge Open Source** — a self-hosted email outreach / campaign tool.

Team-owned campaigns, a contacts spreadsheet (lists included), SMTP / Gmail / Outlook
sending, open & click tracking, unsubscribe, and reply/bounce detection.

Built with Laravel 13, Inertia React, and Tailwind. Designed to run on your own server
via Docker Compose. Maintained by [ContactOut](https://github.com/contactout).

## Features

- **Campaigns** — multi-step email sequences, recipients from contacts, start/pause lifecycle
- **Contacts** — spreadsheet-style editor, lists as tabs, custom columns
- **Connections** — SMTP (+ optional IMAP), Gmail, or Outlook (BYO OAuth apps)
- **Sending** — database queue, scheduled dispatch, placeholder merge fields
- **Engagement** — open pixel, tracked links, signed unsubscribe
- **Replies & bounces** — IMAP or Gmail/Outlook API polling
- **Teams** — multi-user teams (Laravel starter kit Fortify + teams)

## Requirements (production)

- Docker Engine 24+ and Docker Compose v2
- A DNS **A** (and optional **AAAA**) record for your domain
- Ports **80** and **443** open (Let's Encrypt HTTP-01 via Caddy)

## Quick start

```bash
git clone https://github.com/contactout/campaigns.git
cd campaigns
cp .env.docker.example .env
```

Edit `.env`:

| Variable | Notes |
|----------|--------|
| `DOMAIN` | Hostname Caddy will serve (e.g. `mail.example.com`) |
| `ACME_EMAIL` | Email for Let's Encrypt registration |
| `APP_URL` | Must be `https://YOUR_DOMAIN` (include `:HTTPS_PORT` if not 443) |
| `APP_KEY` | Required — see below |
| `HTTP_PORT` / `HTTPS_PORT` | Host ports mapped to Caddy (default `80` / `443`) |
| `DB_PASSWORD` / `DB_ROOT_PASSWORD` | Strong unique passwords |

Generate `APP_KEY` (shared by app, queue, and scheduler):

```bash
echo "base64:$(openssl rand -base64 32)"
```

Paste into `APP_KEY=...`, then:

```bash
docker compose up -d --build
```

Open `https://YOUR_DOMAIN` and register the first user. The dashboard checklist walks you through:

1. Connect an inbox (SMTP / Gmail / Outlook)
2. Add contacts
3. Create a campaign

### Gmail / Outlook OAuth (optional)

Connect buttons appear only when credentials are set. Add to `.env`:

```env
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI="${APP_URL}/oauth/google/callback"

MICROSOFT_CLIENT_ID=...
MICROSOFT_CLIENT_SECRET=...
MICROSOFT_REDIRECT_URI="${APP_URL}/oauth/microsoft/callback"
MICROSOFT_TENANT=common
```

Register these redirect URIs in Google Cloud / Azure app settings:

- `https://YOUR_DOMAIN/oauth/google/callback`
- `https://YOUR_DOMAIN/oauth/microsoft/callback`

Then recreate containers: `docker compose up -d`.

## Architecture

```
Internet → Caddy (HTTPS) → app (Nginx + PHP-FPM)
                              ├─ queue (campaign sends)
                              └─ scheduler (dispatch + mailbox checks)
                         → MySQL 8.4
```

| Service | Role |
|---------|------|
| `caddy` | TLS termination (Let's Encrypt) → reverse proxy to the app |
| `app` | Nginx + PHP-FPM (Laravel) |
| `queue` | `php artisan queue:work` — sends campaign emails |
| `scheduler` | `php artisan schedule:work` — due emails + reply/bounce polling |
| `mysql` | MySQL 8.4 |

Queue, cache, and sessions use the **database** driver (no Redis required).

## Local smoke test (self-signed TLS)

Useful on a laptop without public DNS:

```bash
cp .env.docker.example .env
# DOMAIN=localhost
# HTTP_PORT=8088
# HTTPS_PORT=8443
# APP_URL=https://localhost:8443
# APP_KEY=...  DB_PASSWORD=...  DB_ROOT_PASSWORD=...

docker compose -f docker-compose.yml -f docker-compose.local.yml up -d --build
```

Open https://localhost:8443 (or your `HTTPS_PORT`) and accept the certificate warning.

## Operations

```bash
# Logs
docker compose logs -f app queue scheduler caddy

# Artisan
docker compose exec app php artisan about
docker compose exec app php artisan tinker
```

### Upgrades

```bash
git pull
docker compose up -d --build
```

The app entrypoint runs `php artisan migrate --force` on start.

### Backups

| Data | Volume / command |
|------|------------------|
| Database | `mysql_data` — `docker compose exec mysql mysqldump -ummos -p"$DB_PASSWORD" mmos > backup.sql` |
| Uploads | `app_storage` |
| TLS certs | `caddy_data` |

### Common issues

- **`APP_KEY is empty`** — set `APP_KEY` in `.env` before `compose up`; all containers must share the same key.
- **HTTP works but redirects/links are `http://`** — set `APP_URL=https://...` and recreate; the app trusts the Caddy proxy.
- **Gmail/Outlook buttons missing** — credentials not set or containers not recreated after editing `.env`.
- **Campaigns not sending** — check `queue` and `scheduler` are up: `docker compose ps` and `docker compose logs queue scheduler`.

## Development (without the production stack)

PHP **8.4**, Node **22**, Composer, and SQLite (default) or MySQL:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm ci
npm run build          # or: npm run dev
php artisan serve
php artisan queue:work
php artisan schedule:work
```

Optional local mail catcher (e.g. Mailpit) for SMTP connection tests.

```bash
# Quality checks
php artisan test --compact
vendor/bin/phpstan analyse --no-progress
vendor/bin/pint --dirty
npm run types:check
npm run check
```

More product/architecture notes: [`docs/migration-plan.md`](docs/migration-plan.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Please report security issues privately via
[SECURITY.md](SECURITY.md).

## License

[MIT](LICENSE)
