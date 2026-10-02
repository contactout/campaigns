<!-- Included by the VitePress page `docs-site/architecture.md` (region `body`). Keep new sections inside that region. -->

# Architecture

A tour of how Campaigns is put together. For deployment, see the [README](../README.md).

<!-- #region body -->

## Stack

Laravel 13 (PHP 8.4), Inertia v3 with React and Tailwind 4 for the UI, Laravel Fortify for
authentication, and Laravel Wayfinder for typed route helpers on the frontend. MySQL 8.4 in
production (SQLite locally). Queue, cache, and sessions all use the **database** driver, so no
Redis is required.

In Docker Compose, Caddy terminates TLS and proxies to `app` (Nginx + PHP-FPM). Two more
containers run the same image: `queue` (`queue:work`) and `scheduler` (`schedule:work`).

## Domain model

Everything is owned by a **team**. Routes live under `/{current_team}/...`, and the
`EnsureTeamMembership` middleware plus per-model policies keep users inside their team.

| Concept            | Models                                                          | Notes                                                                                                                                   |
| ------------------ | --------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| Teams              | `Team`, `Membership`, `TeamInvitation`                          | Users belong to many teams with a role and permissions (`TeamRole`, `TeamPermission`). Expired invitations are pruned daily.            |
| Contacts           | `Contact`, `ContactIdentity`, `ContactField`, `ContactProperty` | A contact has identities (email, phone) and custom properties defined by team-level fields (the spreadsheet's custom columns).          |
| Lists              | `ContactList` (pivot `contact_list`)                            | Lists group contacts; the UI shows them as tabs in the contacts spreadsheet.                                                            |
| Mailer connections | `MailerConnection`                                              | A sending inbox of type SMTP (optional IMAP), Gmail, or Outlook (`MailerType`). Stores status, rate-limit state, and `last_checked_at`. |
| Campaigns          | `Campaign`, `CampaignStep`                                      | A campaign belongs to a team, uses a mailer connection, and has ordered steps. Lifecycle in `CampaignStatus`.                           |
| Recipients         | `Recipient`                                                     | Links a contact to a campaign and tracks progress (`RecipientStatus`), including replied, bounced, and unsubscribed.                    |
| Campaign emails    | `CampaignEmail`                                                 | One row per recipient per step: scheduled time, message and thread IDs, status (`EmailStatus`), and open/reply timestamps.              |
| Templates          | `EmailTemplate`, `TemplateFolder`, `Signature`, `Placeholder`   | Reusable content for the step editor; placeholders are merge fields rendered per contact.                                               |
| Tracking           | `EmailOpen`, `TrackedLink`, `LinkClick`                         | Open events, rewritten links, and click events.                                                                                         |
| Unsubscribe        | `Unsubscribe`                                                   | Per-team suppression list keyed by email address.                                                                                       |

## Sending pipeline

```
Start campaign
   -> SeedCampaignEmails job: CampaignStepScheduler creates first-step CampaignEmail rows
scheduler (every minute)
   -> campaigns:dispatch-due: due, scheduled emails of active campaigns (batches of 200)
   -> SendEmail job per email (queue worker)
        - skip unless the email is still scheduled and due (one queued job per email)
        - skip if the campaign is not active
        - fail if the address is missing or suppressed, or the connection is inactive
        - reset the connection's daily send counter when the UTC day rolls over
        - defer 15 minutes if the connection is rate limited
        - render placeholders (PlaceholderRenderer)
        - rewrite links, add the unsubscribe link and the open pixel (CampaignBodyBuilder)
        - send via the CampaignMailer for the connection type
        - schedule the recipient's next step (CampaignStepScheduler)
```

`CampaignStepScheduler` counts a step's `day` from the later of the campaign start and the moment
the recipient joined, then moves the result onto the campaign's sending window (`SendingWindow`):
the allowed weekdays and hour range, evaluated in the recipient's timezone when it has one.

`CampaignMailerResolver` picks the implementation of the `CampaignMailer` contract:
`SmtpCampaignMailer`, `GmailCampaignMailer` (Gmail API), or `OutlookCampaignMailer`.
OAuth tokens are refreshed by `OAuthTokenManager`. Send jobs make a single attempt; failures are
recorded on the email and the connection instead of being retried blindly.

## Replies and bounces

The scheduler runs `campaigns:check-mailboxes` every ten minutes, which queues a
`CheckConnectionMailbox` job per connection. `MailboxReaderResolver` selects a reader
(`ImapMailboxReader`, `GmailMailboxReader`, or `OutlookMailboxReader`) that produces
`InboundMessage` objects. The `ProcessInboundMessage` action matches each message to a sent
email via the `In-Reply-To`/`References` headers (a reply) or recognizes a bounce report naming
a failed recipient, then updates the email, recipient, and contact status. Unmatched messages
are ignored.

## Tracking and unsubscribe

Public routes in `routes/tracking.php` sit outside the authenticated team group:

- `GET /t/o/{campaignEmail}` serves the 1x1 open pixel and records an `EmailOpen`.
- `GET /t/c/{hash}` records a `LinkClick` for a `TrackedLink` and redirects to the original URL.
- `GET|POST /unsubscribe/{recipient}` is protected by Laravel's `signed` middleware; confirming
  writes an `Unsubscribe` row and stops further sends to that address for the team.

Opens and clicks are approximate: mail clients may prefetch or block images. Known proxy user
agents are ignored, and a click records an open as well, because a blocked pixel leaves no other
trace that the message was read. Link rewriting and the pixel can each be turned off per campaign;
the unsubscribe link is always added.

## Directory conventions

| Path                                      | Convention                                                                                                                   |
| ----------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| `app/Actions/<Domain>/`                   | Single-purpose classes holding write logic (`CreateCampaign`, `StartCampaign`, ...). Controllers stay thin and call actions. |
| `app/Http/Controllers/<Domain>/`          | Inertia controllers and redirect endpoints.                                                                                  |
| `app/Http/Requests/<Domain>/`             | Form Requests for validation and authorization.                                                                              |
| `app/Policies/`                           | One policy per team-owned model.                                                                                             |
| `app/Services/Mail`, `app/Services/OAuth` | Mailers, mailbox readers, rendering, scheduling, and OAuth clients, behind contracts in `app/Contracts/Mail`.                |
| `app/Jobs/Campaigns/`                     | Queue jobs.                                                                                                                  |
| `app/Console/Commands/`                   | Scheduled commands. Schedules are defined in `routes/console.php`.                                                           |
| `app/Enums/`                              | Status and type enums.                                                                                                       |
| `routes/*.php`                            | Split per domain and required from `routes/web.php`.                                                                         |
| `resources/js/pages/`                     | Inertia pages, one directory per domain. Layouts are mapped in `resources/js/app.tsx`.                                       |
| `resources/js/wayfinder/`                 | Generated (gitignored) by Wayfinder from routes and controllers; use its typed URL helpers instead of hardcoding paths.      |
| `tests/`                                  | Pest feature and unit tests.                                                                                                 |

<!-- #endregion body -->
