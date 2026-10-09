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

## Signatures

Each connection can use one of the team's signatures, chosen in the **Signature** column on the Connections page. Put `{{signature}}` in a step body (the step editor's **Insert signature → Sender's signature** adds it) and every email gets the signature of the connection that sends it.

A connection left on **Team default** uses the team's default signature. With no default either, the tag renders empty. Deleting a signature moves the connections using it back to the team default.

The tag is filled only in the email body; in a subject it renders empty.

## If the buttons are missing

Gmail/Outlook buttons stay hidden when credentials are not set, or when containers were not recreated after editing `.env`.
