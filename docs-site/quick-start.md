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

> **Confirm HTTPS first.** `docker/Caddyfile` sends a one-year `Strict-Transport-Security`
> (HSTS) header (`max-age=31536000; includeSubDomains`) and `.env.docker.example` sets
> `SESSION_SECURE_COOKIE=true`. Once a browser sees HSTS it refuses plain HTTP for the domain
> and its subdomains, so make sure `DOMAIN` resolves to this host and Caddy obtains a
> certificate before real users visit. If unsure, comment out the HSTS line for the first
> boot, confirm `https://YOUR_DOMAIN` loads with a valid certificate, then re-enable it and
> run `docker compose restart caddy`. Adding `; preload` (HSTS preload list) is a separate,
> deliberate opt-in.
>
> **`includeSubDomains` covers every subdomain of `DOMAIN`.** If `DOMAIN` is e.g. `example.com`,
> browsers will also refuse plain HTTP for `www.example.com`, `intranet.example.com`, and so on.
> If any subdomain is still HTTP-only, remove `; includeSubDomains` from `docker/Caddyfile`
> before deploying.

Open `https://YOUR_DOMAIN` and register the first user. The dashboard checklist walks you through:

1. Connect an inbox (SMTP / Gmail / Outlook)
2. Add contacts
3. Create a campaign

## Stopping and archiving campaigns

Use **Stop** to pause an active campaign if you may want to restart it later.
**Archive** asks for confirmation and is permanent: sending stops, and the campaign's settings,
recipients and steps become read-only. Archived campaigns cannot be restored or restarted.
Recipient history remains available; you can still duplicate or delete the campaign.
## Personalizing templates and steps

Both editors list the same available contact fields: `{{name}}`, `{{email}}`, `{{phone}}`,
and your team's custom fields (for example `{{company}}` or `{{title}}` if those columns exist).
Tags ignore case and surrounding whitespace. Template and step saves reject unknown tags in
subjects and bodies, naming the tags to correct; `{{first_name}}` needs a matching custom
contact field or should be replaced with `{{name}}`. Template placeholder definitions alone
do not create contact fields. Known fields with no contact value still render as empty text.
Use `{{signature}}` only in the body to insert the sending connection's signature or team default.
Existing saved content is not rewritten; correct unknown tags when editing it.

## Campaign step order

Arrange steps before starting a campaign. After its first start, dragging steps and the
**Move up / Move down** actions are disabled, even while the campaign is stopped. This keeps
queued emails and recipients' sent history in the same sequence so follow-ups are not skipped.
Stopping a campaign does not unlock its step order.

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
