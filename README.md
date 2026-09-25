# MMOS

Open-source mail merge / email campaign tool (Laravel + Inertia React). Team-owned
campaigns, contacts spreadsheet, SMTP / Gmail / Outlook sending, open/click tracking,
and IMAP / provider reply-bounce detection.

## Requirements

- Docker Engine 24+ and Docker Compose v2
- A DNS **A** (and optional **AAAA**) record for your domain pointing at the server
- Ports **80** and **443** open to the internet (Let's Encrypt HTTP-01)

## Quick start (production)

```bash
git clone <your-fork-or-repo-url> mmos
cd mmos

cp .env.docker.example .env
```

Edit `.env`:

1. Set `DOMAIN` and `ACME_EMAIL` (used by Caddy for HTTPS)
2. Set `APP_URL=https://YOUR_DOMAIN`
3. Set a stable `APP_KEY` (required — shared by app, queue, and scheduler):

   ```bash
   echo "base64:$(openssl rand -base64 32)"
   ```

   Paste the output into `APP_KEY=...` in `.env`.

4. Set strong `DB_PASSWORD` and `DB_ROOT_PASSWORD`

Then:

```bash
docker compose up -d --build
```

Open `https://YOUR_DOMAIN`, register the first user, and use the dashboard checklist:

1. Connect an inbox (SMTP / Gmail / Outlook)
2. Add contacts
3. Create a campaign

### Optional OAuth

To show **Connect Gmail** / **Connect Outlook**, set `GOOGLE_*` / `MICROSOFT_*` in
`.env` and register these redirect URIs in your provider consoles:

- `https://YOUR_DOMAIN/oauth/google/callback`
- `https://YOUR_DOMAIN/oauth/microsoft/callback`

Recreate the app containers after changing env: `docker compose up -d`.

## What Compose runs

| Service     | Role                                                          |
|------------|----------------------------------------------------------------|
| `caddy`    | HTTPS (Let's Encrypt) → reverse proxy to the app               |
| `app`      | Nginx + PHP-FPM (Laravel)                                      |
| `queue`    | `queue:work` — sends campaign emails                           |
| `scheduler`| `schedule:work` — dispatch due emails + mailbox checks         |
| `mysql`    | MySQL 8.4                                                      |

Campaign sending uses the **database** queue (no Redis required).

## Local smoke test (self-signed TLS)

```bash
cp .env.docker.example .env
# Set DOMAIN=localhost, APP_URL=https://localhost, DB passwords, APP_KEY

docker compose -f docker-compose.yml -f docker-compose.local.yml up -d --build
```

Open https://localhost and accept the certificate warning.

## Ops

```bash
# Logs
docker compose logs -f app queue scheduler

# Artisan on the app container
docker compose exec app php artisan about

# Create / promote users via register UI, or tinker:
docker compose exec app php artisan tinker
```

### Upgrades

```bash
git pull
docker compose up -d --build
```

The app entrypoint runs `php artisan migrate --force` on start.

### Backups

- **Database**: volume `mysql_data` — e.g. `docker compose exec mysql mysqldump -ummos -p... mmos > backup.sql`
- **Uploads**: volume `app_storage`

## Development (without Compose production stack)

Use the normal Laravel workflow (Sail or local PHP 8.4 + Node 22 + SQLite/MySQL):

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm ci && npm run build
php artisan serve
php artisan queue:work
php artisan schedule:work
```

## License

See the repository license file.
