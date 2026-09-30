---
title: Operations
---

# Operations

```bash
# Logs
docker compose logs -f app queue scheduler caddy

# Artisan
docker compose exec app php artisan about
docker compose exec app php artisan tinker
```

## Upgrades

Once releases exist, deploy a release tag rather than tracking `main`:

```bash
git fetch --tags
git checkout vX.Y.Z
docker compose up -d --build
```

Read [CHANGELOG.md](https://github.com/contactout/campaigns/blob/main/CHANGELOG.md) before upgrading. If you track `main` instead, use `git pull`
followed by the same `docker compose up -d --build`.

The app entrypoint runs `php artisan migrate --force` on start.

The image and nginx site are named `campaigns` (previously `mmos`). Existing installs keep
working — Compose picks up the new names on rebuild, and the database is untouched: keep
whatever `DB_DATABASE` / `DB_USERNAME` your `.env` already sets. To pick up the new
branding on an existing install, set `APP_NAME=Campaigns` in your `.env`.

## Backups

| Data      | Volume / command         |
| --------- | ------------------------ |
| Database  | `mysql_data` — see below |
| Uploads   | `app_storage`            |
| TLS certs | `caddy_data`             |

The database credentials are read from the `mysql` container's own environment, so this
works whether your `.env` uses the original `mmos` names or the current `campaigns` ones:

```bash
docker compose exec -T mysql sh -c \
  'mysqldump --single-transaction --no-tablespaces --skip-lock-tables \
     -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' > backup.sql
```

`--no-tablespaces` avoids needing the `PROCESS` privilege the app user does not have, and
`--single-transaction` keeps the dump consistent without locking the tables of a live app.

## Common issues

- **`APP_KEY is empty`** — set `APP_KEY` in `.env` before `compose up`; all containers must share the same key.
- **HTTP works but redirects/links are `http://`** — set `APP_URL=https://...` and recreate; the app trusts the Caddy proxy.
- **Gmail/Outlook buttons missing** — credentials not set or containers not recreated after editing `.env`. See [Connections](./connections).
- **Campaigns not sending** — check `queue` and `scheduler` are up: `docker compose ps` and `docker compose logs queue scheduler`.
