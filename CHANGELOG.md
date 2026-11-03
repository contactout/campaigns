# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project aims to follow
[Semantic Versioning](https://semver.org/spec/v2.0.0.html) once releases are tagged.

## [Unreleased]

### Added

- Documentation site (VitePress), published to GitHub Pages.
- Campaigns with multi-step sequences, recipients from contacts, and a start/pause/archive lifecycle.
- Contacts spreadsheet with inline editing, lists as tabs, and custom columns.
- Templates, template folders, signatures, and placeholder merge fields in the step editor.
- SMTP, Gmail, and Outlook sending connections (Gmail and Outlook via bring-your-own OAuth apps).
- Sending engine: scheduled dispatch, queue jobs, and per-connection rate-limit handling.
- Open, click, and unsubscribe tracking.
- Reply and bounce detection via IMAP or the Gmail/Outlook APIs.
- Dashboard setup checklist and shared empty states.
- Docker Compose production stack (Caddy with automatic TLS, MySQL 8.4) and a local self-signed override.
- One-click unsubscribe: `List-Unsubscribe` headers on every campaign email.
- Follow-up steps marked as threaded reply in the same conversation: SMTP sets `In-Reply-To`/
  `References`, Gmail uses the thread id, and Outlook creates a reply on the previous message.
- Signature per connection: a `{{signature}}` tag in a step body is filled at send time with the
  sending connection's signature, falling back to the team default.
- Per-campaign sending window (allowed weekdays and an hour range) and switches for open and link
  tracking, both edited from the campaign dialog.
- Bulk CSV import of contacts: map columns to name, email, and contact fields (optionally creating
  fields), skip or update existing contacts by email, skip unsubscribed addresses, attach to a list
  (including existing contacts that were skipped, but not deleted ones), and see created/updated/skipped/invalid counts once the queued import finishes.
- Per-connection daily sending limit and minimum seconds between sends, set from the SMTP
  connection dialog; blank means no cap.
- `REGISTRATION_ENABLED` setting to control open sign-up; the first user can always register.
- `campaigns:verify-user` command to verify a user when system mail is not configured.
- Architecture overview in `docs/architecture.md`, issue and pull request templates, Code of Conduct.
- Team-wide suppression across campaigns: hard bounces join the team's unsubscribe list, `@domain`
  entries suppress a whole domain, and contacts that are do-not-contact, bounced, unsubscribed, or
  listed are skipped when added to a campaign and never emailed.
- Suppression follow-ups: delay reports never suppress, bare spam/blocklist/SPF/DMARC refusals
  don't count as permanent, a real 5.1.1 code wins over a relay refusal, send time checks every
  address of the contact, and legacy mixed-case unsubscribe entries are lowercased by a migration.

### Changed

- Rebranded from MMOS to Campaigns. The Docker image and nginx site are renamed; existing
  installs keep working and the database is untouched.
- Contacts spreadsheet keyboard navigation and accessibility polish.
- "Sending" copy renamed to "Connections".
- Docker Compose host ports are configurable, and PHP 8.4 IMAP is installed via PECL.
- README backup instructions use `mysqldump` with credentials read from the container.
- Restarting a stopped campaign keeps its original start time, so later steps stay on the days
  they were planned for instead of shifting by however long the campaign was stopped.
- Switching a campaign to another connection moves its unsent emails to the new connection and
  releases any that were waiting on the old one. Later steps are scheduled on the campaign's current
  connection. Clearing the connection leaves unsent emails where they are. A threaded follow-up
  sent from a different mailbox than the previous step keeps only the Message-ID: SMTP and Gmail
  still thread it through headers, while Outlook starts a new conversation.
- A step can no longer be deleted once any of its emails has gone out. Deleting a step moves
  recipients waiting on it to the following step, or completes them if it was the last step.
- Reordering steps must list every step of the campaign exactly once.
- A connection that reaches its daily sending limit holds its due emails until the limit refreshes
  at midnight UTC, instead of retrying every 15 minutes. `campaigns:dispatch-due` leaves capped
  connections out of the batch, so their backlog no longer delays other connections. Every email is
  checked against its sending window again just before it is sent.
- Mailbox polling for Gmail and Outlook reads at most 100 messages per connection per run, oldest
  first, and resumes where it stopped. A connection that was never polled starts from when it was
  created rather than reading the whole inbox.
- Faster sending and enrollment on large campaigns: first-step seeding reads only recipients that
  are still missing their first email, `campaign_emails` has indexes for dispatch and reply/bounce
  lookups, send jobs are queued without their relations, the contacts added to a campaign are
  validated in one query, and a send reads the team's contact fields once instead of once per
  render.

### Fixed

- The Status select in New contact and Edit contact and the Role select in Invite member now use
  their visible labels as accessible names for assistive technology.
- Campaign emails could be queued and sent more than once when the queue fell behind; sends are
  now unique per email and re-check that the email is still scheduled and due.
- Every step of a recipient added after the campaign started fired within minutes, because step
  days were counted from the campaign start rather than from when the recipient joined.
- Recipients added to a running campaign received nothing until the campaign was stopped and
  restarted.
- Open tracking counted mailbox proxy fetches and pixels for emails that were never sent, failed,
  or bounced. A click now also records an open, since a blocked pixel leaves no other trace.
- User agents longer than 255 characters broke open and click tracking inserts.
- A connection's sending limit never reset, so a connection that reached `sending_limit` was
  throttled indefinitely. It now counts sends since midnight UTC and resets when the UTC day rolls
  over.
- Campaign emails carried `List-Unsubscribe` headers but no unsubscribe link in the body, which the
  deliverability docs already promised.
- A recipient's own `timezone` was stored but never used when scheduling.
- `campaign_emails` had no uniqueness on (recipient_id, campaign_step_id), so two seed jobs running
  at once could insert the same step twice. Existing duplicates are merged into the earliest row,
  keeping their opens, clicks and tracked links.
- Any send error deactivated the connection and failed the email, so one bad address, a timeout,
  or provider throttling stopped the whole connection. Errors are now classified: a rejected
  recipient bounces only that recipient, temporary errors retry with backoff (up to five attempts),
  and only authentication or configuration errors deactivate the connection.
- Emails due while their connection was inactive were failed for good. They now stay scheduled and
  resume when the connection is verified or reconnected.
- Rate-limit retries could be scheduled outside the campaign's sending window.
- The campaign "Sent" stat dropped emails once they were opened, replied to, or bounced; it now
  counts every dispatched email.
- Bounce reports carrying `In-Reply-To`/`References` were recorded as replies, and a bounce could be
  matched to an arbitrary email of the contact. Bounces are now checked first and matched to the
  latest sent email by Message-ID, then by address.
- Docker build for PHP 8.4 IMAP support.
- Gmail polls picked up the connection's own sent follow-ups as replies, so every Gmail sequence
  with a threaded step stopped after that step. Mail sent from the connection's own address is no
  longer treated as a reply or a bounce.
- The mailbox cursor moved past replies and bounces that were never read: provider errors returned
  an empty mailbox, each poll read at most 50 messages, and the cursor was set after the fetch.
  A failed read now leaves the cursor in place, and the cursor only moves past mail that was
  processed.
- Replies and bounces did not match emails seeded before the campaign had a connection. A sent
  email now records the connection it actually went out through.
- Adding a list of several thousand contacts to a campaign failed with too many query
  placeholders. Recipients are now checked and inserted in chunks of 500.
- Concurrent send workers could exceed a connection's daily limit, and the daily reset could wipe
  out sends that other workers had just counted. Each send now reserves its slot atomically before
  it goes out and gives it back if the send fails.
- Reordering only some of a campaign's steps could give two steps the same position, so one of them
  was never sent.
- Multipart Gmail bounce reports were read from the snippet, so 5.1.1 bounces did not suppress the
  address. The full MIME tree is now read, including delivery-status parts that Gmail stores as
  attachments.
- Replies read over IMAP did not match, because IMAP strips the angle brackets from Message-IDs.
- Tracked links kept HTML entities such as `&amp;` in the redirect URL.
- Send jobs for emails deleted while queued (a removed recipient or a deleted campaign) failed
  instead of being dropped.
- CSV import ignored `@domain` suppression entries, so it created contacts on suppressed domains.

### Security

- Open-tracking pixel URLs use a random per-email tracker instead of the sequential email ID,
  so opens can no longer be forged by enumerating IDs. A migration backfills trackers for
  existing emails; pixels in emails sent before upgrading stop recording opens.
- The unsubscribe endpoint accepts RFC 8058 one-click POSTs (still signature-checked).
- Open sign-up is now opt-in via `REGISTRATION_ENABLED`; other users join through team invitations.
- Team invitations can no longer grant the owner role; only roles assignable to members are accepted.
  Pending invitations created earlier with the owner role are rejected when accepted.
- Pending owner-role invitations no longer allow sign-up when open registration is off, are
  hidden from the dashboard and the login/register invitation banner, and no longer block
  re-inviting the same email. Because they are hidden, they cannot be declined from the app;
  they lapse when they expire.
- Only proxies on loopback/private networks (configurable via `TRUSTED_PROXIES`) may set
  `X-Forwarded-*` headers, and requests for hosts other than the `APP_URL` host (plus
  `TRUSTED_HOSTS`) are rejected outside local/testing. Production URLs are generated from `APP_URL`.
- The Docker example enables secure session cookies and a one-year HSTS header in Caddy.
- The CSRF exemption now covers only the one-click unsubscribe POST, not every `unsubscribe/*` route.
- Contact values filled into placeholders are HTML-escaped in email bodies, so imported data can
  no longer inject markup or links. Subjects are filled as plain text. The `{{signature}}` tag still
  inserts the signature's HTML.

### Upgrade notes

- `REGISTRATION_ENABLED` defaults to `false`. Existing installs that relied on open sign-up
  must set `REGISTRATION_ENABLED=true` in `.env` and recreate the containers.
- PHP 8.4 is now the minimum supported version.
- If the app sits behind a proxy outside loopback/private ranges, set `TRUSTED_PROXIES`. If it is
  reached on hostnames other than the `APP_URL` host, list them in `TRUSTED_HOSTS`. Existing Docker
  installs: confirm HTTPS works before pulling the HSTS change into `docker/Caddyfile`. The header
  uses `includeSubDomains`, so drop that directive if any subdomain of `DOMAIN` is HTTP-only.
  `TRUSTED_HOSTS` only widens accepted hosts; links and cookies still use `APP_URL`.
- A migration adds three indexes to `campaign_emails`. Migrations run when the app container
  starts, so on a large table the first start after upgrading can take longer.
- Contact fields whose values contain HTML now appear as literal text in email bodies.
