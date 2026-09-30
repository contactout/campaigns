---
title: Connections
---

# Connections

Campaigns sends from inboxes you connect: SMTP (optional IMAP), Gmail, or Outlook. Gmail and Outlook use bring-your-own OAuth apps.

System mail (verification, password reset, invitations) is separate. It uses `MAIL_*` in `.env` — see [Quick start](./quick-start#system-email-registration).

## Gmail / Outlook OAuth (optional)

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

## If the buttons are missing

Gmail/Outlook buttons stay hidden when credentials are not set, or when containers were not recreated after editing `.env`.
