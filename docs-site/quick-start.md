---
title: Quick start
---

# Quick start

Production installs use Docker Compose. Gmail and Outlook OAuth setup is on [Connections](./connections).

## Requirements (production)

- Docker Engine 24+ and Docker Compose v2
- A DNS **A** (and optional **AAAA**) record for your domain
- Ports **80** and **443** open (Let's Encrypt HTTP-01 via Caddy)

## Install

```bash
git clone https://github.com/contactout/campaigns.git
cd campaigns
cp .env.docker.example .env
```

Edit `.env`:

| Variable                           | Notes                                                            |
| ---------------------------------- | ---------------------------------------------------------------- |
| `DOMAIN`                           | Hostname Caddy will serve (e.g. `mail.example.com`)              |
| `ACME_EMAIL`                       | Email for Let's Encrypt registration                             |
| `APP_URL`                          | Must be `https://YOUR_DOMAIN` (include `:HTTPS_PORT` if not 443) |
| `APP_KEY`                          | Required — see below                                             |
| `HTTP_PORT` / `HTTPS_PORT`         | Host ports mapped to Caddy (default `80` / `443`)                |
| `DB_PASSWORD` / `DB_ROOT_PASSWORD` | Strong unique passwords                                          |

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

## System email & registration

System mail (email verification, password reset, team invitations) needs SMTP `MAIL_*`
settings in `.env` (`MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`,
`MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`). This is separate from the inboxes you connect for
campaign sending.

- The first registered user can always register.
- Set `REGISTRATION_ENABLED=true` to allow open sign-up. Otherwise, others join through team invitations.
- If mail isn't configured, verify a user from the command line:

```bash
docker compose exec app php artisan campaigns:verify-user you@example.com
```

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

Open `https://localhost:8443` (or your `HTTPS_PORT`) and accept the certificate warning.

Day-to-day development without this stack is covered in [Contributing](./contributing).
