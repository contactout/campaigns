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
- `REGISTRATION_ENABLED` setting to control open sign-up; the first user can always register.
- `campaigns:verify-user` command to verify a user when system mail is not configured.
- Architecture overview in `docs/architecture.md`, issue and pull request templates, Code of Conduct.

### Changed

- Rebranded from MMOS to Campaigns. The Docker image and nginx site are renamed; existing
  installs keep working and the database is untouched.
- Contacts spreadsheet keyboard navigation and accessibility polish.
- "Sending" copy renamed to "Connections".
- Docker Compose host ports are configurable, and PHP 8.4 IMAP is installed via PECL.
- README backup instructions use `mysqldump` with credentials read from the container.

### Fixed

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

### Security

- Open-tracking pixel URLs use a random per-email tracker instead of the sequential email ID,
  so opens can no longer be forged by enumerating IDs. A migration backfills trackers for
  existing emails; pixels in emails sent before upgrading stop recording opens.
- The unsubscribe endpoint accepts RFC 8058 one-click POSTs (still signature-checked).
- Open sign-up is now opt-in via `REGISTRATION_ENABLED`; other users join through team invitations.

### Upgrade notes

- `REGISTRATION_ENABLED` defaults to `false`. Existing installs that relied on open sign-up
  must set `REGISTRATION_ENABLED=true` in `.env` and recreate the containers.
- PHP 8.4 is now the minimum supported version.
