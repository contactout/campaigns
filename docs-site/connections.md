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

Signatures are plain text. Line breaks are preserved, and characters such as `&` and
`<ada@example.com>` appear literally in the Signatures preview, the step editor's named-signature
insertion, and sent emails. HTML tags and entities (including those in existing signatures) are
shown as text, not formatting. Edit older HTML signatures to use text and line breaks instead.
Content already copied into a saved step is unchanged; remove and reinsert it to update.

The tag is filled only in the email body; in a subject it renders empty.

## When sending fails

A send failure only deactivates a connection when its credentials or configuration are rejected (a failed SMTP login, an expired or revoked OAuth grant, HTTP 401/403). Emails due on an inactive connection stay scheduled and are re-checked hourly; once you **Verify** the connection (or reconnect Gmail/Outlook) they resume within the campaign's sending window.

Other failures leave the connection active:

- A rejected address (for example SMTP 550 "user unknown") bounces that recipient only.
- Temporary problems (timeouts, SMTP 4xx, provider throttling or outages) are retried with increasing delays, up to five attempts, before the email is marked failed.

## Sending limits

A connection's daily sending limit counts sends since midnight UTC. Once it is reached, that
connection's due emails wait until the limit refreshes at the next UTC midnight and then go out
within each campaign's sending window. Other connections keep sending in the meantime. The limit
holds even with several queue workers sending at once.

## Switching a campaign's connection

Picking a different connection for a campaign moves its unsent emails, and all later steps, to the
new connection. Emails that were waiting on an inactive connection go out at the next moment the
sending window allows. Emails that were already sent stay recorded on the connection that sent them,
so replies and bounces to them still match.

A threaded follow-up sent from the new connection still replies in the same conversation over
SMTP and Gmail. On Outlook it starts a new conversation, because the new mailbox can't reply to a
message it never sent.

Clearing a campaign's connection leaves its unsent emails on the connection they already have.

## Replies and bounces

Mailboxes are checked every ten minutes. Each check reads Gmail and Outlook mail oldest first, at
most 100 messages per connection, and a backlog is worked through over the following checks. A
newly added connection only reads mail received after it was added. If the provider can't be
reached, nothing is skipped: the next check reads the same messages again. Mail you send from the
connected inbox yourself never counts as a reply.

## If the buttons are missing

Gmail/Outlook buttons stay hidden when credentials are not set, or when containers were not recreated after editing `.env`.
