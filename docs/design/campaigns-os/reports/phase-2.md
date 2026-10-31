# Phase 2 report — dashboard

Base commit `618556f` on `design/campaigns-os`. Worktree
`/home/azri/projects/contactout/campaigns-design`. Paper MCP was not called.

## What changed

- `app/Http/Controllers/DashboardController.php` — adds an `overview()` aggregate for the
  four cards and the four "Jump back in" rows, plus `mailerConnections` and `can.create`
  so the page head's `New campaign` can open the existing create modal. Existing
  `pendingInvitations` and `setup` payloads are unchanged.
- `resources/js/pages/dashboard.tsx` — rebuilt to board 01 A: greeting head with team and
  date, invitation banner, four stat cards, "Finish setting up" card with progress bar and
  per-row CTA, "Jump back in" card. The old "Get started"/"Workspace" hero, the three
  destination tiles and the connections tip are gone; the board has none of them.
- `resources/js/types/teams.ts` — `DashboardMetrics` and `DashboardJumpBackIn` next to
  `DashboardSetup`.
- `resources/js/components/team-invitation-alert.tsx` — restyled from the blue alert onto
  `--banner` + `border-accent` + `--primary` text (the auth-page info strip; see deviations).
- `resources/js/pages/campaigns/index.tsx` — search input now re-syncs from `filters.q`
  when the server sends a new one (review follow-up 2).
- `tests/Feature/DashboardTest.php` — three tests: 30-day metrics and previous-window
  deltas, the jump-back-in counts, and cross-team isolation with null rates on empty data.

No migration, no new table, no change to routes, middleware, Inertia conventions, `.dark`,
or `resources/js/components/ui/*` public APIs.

## Data added — every query and what it costs

Rates are cohort rates over the same 30-day window the board labels: emails whose
`dispatched_at` falls in the window, opened/replied when `opened_at`/`replied_at` is set.
Deltas compare that window with the 30 days before it.

1. **Email metrics and deltas — one query, both windows.**

   ```sql
   select count(case when dispatched_at >= :windowStart then 1 end) as current_sent,
          count(case when dispatched_at >= :windowStart and opened_at is not null then 1 end) as current_opened,
          count(case when dispatched_at >= :windowStart and replied_at is not null then 1 end) as current_replied,
          count(case when dispatched_at <  :windowStart then 1 end) as previous_sent,
          count(case when dispatched_at <  :windowStart and opened_at is not null then 1 end) as previous_opened,
          count(case when dispatched_at <  :windowStart and replied_at is not null then 1 end) as previous_replied
   from campaign_emails
   where campaign_id in (select id from campaigns where team_id = ?)
     and dispatched_at >= :previousStart
   ```

   `campaign_emails` has no `team_id`, so the `campaign_id` index (plus the FK index on
   `campaigns.team_id`) narrows the scan to the team's sent emails. The table has indexes on
   `campaign_id`, `campaign_step_id`, `recipient_id`, `status` and `scheduled_at` but **none
   on `dispatched_at`**, so the date filter is a scan of the team's sent rows, not a range
   seek. This is the most expensive query on the page; an index on
   `campaign_emails(campaign_id, dispatched_at)` would fix it, and is out of scope here.
   Computing both windows in one query means the three delta chips cost **zero extra
   queries** rather than one per metric.

2. `select count(*) from campaigns where team_id = ? and status = 'Active'` — indexed FK,
   small row set. Feeds the "Active campaigns" card and the Campaigns qualifier.
3. `select count(*) from campaigns where team_id = ? and status = 'Draft'` — same.
4. `select count(*) from contacts where team_id = ?` — FK index, index scan over the team's
   contacts.
5. `select count(*) from contact_lists where team_id = ?` — cheap.
6. `select count(*) from email_templates where team_id = ?` — cheap.
7. `select count(*) from template_folders where team_id = ?` — cheap.
8. `select * from mailer_connections where team_id = ? order by name` — a handful of rows;
   the collection supplies the row count, the "needs attention" count
   (`exception_type is not null`) and the create modal's connection options, so one query
   serves three uses.

The page now issues 8 extra queries on top of the existing invitation and three `exists()`
setup queries. Nothing is cached; there is no history table, so "Active campaigns" has no
delta (see gaps).

## Board 01 A requirements → what shipped

| Requirement | Where |
| --- | --- |
| Greeting H1 from time of day + first name, subtitle team · date | `dashboard.tsx` via `PageHead`; `auth.user.name`, `currentTeam.name`, `Intl.DateTimeFormat` |
| Breadcrumb bar hidden | `Dashboard.layout` sets `showHeader: false` |
| `New campaign` primary top-right | `CreateCampaignModal` with `mailerConnections`; rendered only when `can.create` |
| Full-width invitation banner, `--banner`, `Review` | page markup, `border-accent bg-banner`, opens `PendingInvitationsModal` |
| Four stat cards, 12px muted label, 20/700 value, green delta | `StatCard` local component; deltas only where computed |
| `Finish setting up`, `2 of 3 done`, progress bar, done/undone icons, strikethrough done rows | setup card; `--primary` bar, `Check` in a 20px filled circle, empty 20px ring |
| Secondary CTA on the pending row | `Go to Connections` / `Go to Contacts` / `Go to Campaigns` per undone row |
| `Jump back in`, 32px tinted tile, title, qualifier, chevron, 64px rows | jump card; `bg-accent` tiles, real counts in the qualifier |
| 1280 layout: 4 stat cards at 8px gap, 560/440 panel split, 24px page padding | `xl:grid-cols-4 gap-2`, `lg:grid-cols-[minmax(0,14fr)_minmax(0,11fr)]`, `px-4 py-6 lg:px-6` |
| No hex outside `app.css`, no second type scale | only semantic tokens and `text-[20px]/[16px]/[13px]/[12px]` one-offs |

## Deviations

1. **The invitation banner is not `team-invitation-alert.tsx`.** That component is the
   auth-page strip (`Log in`/`Register` to join an invited team), not a dashboard banner.
   It was restyled onto `--banner` as asked, but the dashboard banner is page markup that
   reads `pendingInvitations` and opens the existing modal — no parallel component was
   created. The modal no longer auto-opens on page load; the board shows the banner, and an
   auto-open modal would hide it.
2. **Two new dashboard props.** The board's `New campaign` has no data source on the
   dashboard, so the controller now passes `mailerConnections` and `can.create`; the modal
   itself is unchanged.
3. **The board's checklist state is not reachable.** It shows `2 of 3 done` with a pending
   "Create a campaign" while "Jump back in" lists 2 active + 1 draft campaigns. Any campaign
   sets `has_campaign`, so the data can only produce 3/3 with campaigns or 2/3 without.
   Both states were rendered; the 2/3 render has `0 active · 0 draft`.
4. **Complete setup state is not on the board.** With 3/3 the card stays (all rows done, no
   CTA) instead of the old "Workspace" hero, which the board does not contain.
5. **No delta on "Active campaigns".** There is no stored history to compare against, and
   the board shows none. Listed under gaps rather than invented.
6. **Negative and zero deltas** render in `--destructive` and muted respectively; the board
   only covers positive green deltas. The colour is a state the board omits, not new data.
7. **Rates show `—` when the window has no dispatched email** rather than a fabricated
   `0.0%`; the delta chip is hidden in that case.
8. **`needs attention` = a connection with `exception_type` set.** When it is zero the second
   half of the Connections line is dropped (`3 inboxes`) instead of `0 need attention`.
9. **Contacts icon is `Users`**, not the board's person-with-arc glyph (no matching asset in
   the icon set); Campaigns/Templates/Connections use `Send`/`FileText`/`Plug` as on the board.
10. The sidebar still reads `Team · Owner` (phase 1 deviation 6, no member count in
    `UserTeam`); phase 1's six nav items are untouched.

## Review follow-ups from phase 1

1. **Button blast radius — rendered and checked at 1280.** `/login`: card, 40px inputs,
    32px `Log in`, passkey and submit both legible, no overlap; the autofocused input's halo
    reads as focus, not an error. `/settings/profile`: breadcrumb bar intact, 32px `Save`
    and destructive `Delete account` legible, inputs not cramped. `/` (welcome) is the only
    page using `size="lg"`: its 36px `Get started` still reads as the primary action next to
    the outline `I have an account`, so the shrink did not flatten the hierarchy.
2. **Campaigns search state — fixed** (4 lines): an effect re-syncs `search` from
    `filters.q`, so changing the status filter now reloads the applied query into the input.
    Type-checked and lint-clean; not exercised by a browser interaction.
3. **`relative-time.ts` — left alone.** Its only call site is the campaigns list; the
    dashboard formats its date and numbers with `Intl` directly and does not use it.
4. **Type scale** stays on the phase 1 convention; `PageHead` owns the page head.

## Verification

Space provenance: staged from a `tar` of the working tree at `618556f` with 6 dirty paths;
`composer install` and `npm ci` ran in the Space, and the final run below is on the clean
tree (the temporary seeder, preview-login route and SQLite database were deleted before the
last build/test pass; only an untracked `.env` remained). Nothing was installed, built or
served on the host.

```
$ npm run build
BUILD_EXIT=0
✓ built in 2.24s

$ npm run types:check
TYPES_EXIT=0
> tsc --noEmit

$ npm run check
CHECK_EXIT=0
pass: All 145 files are correctly formatted
pass: Found no warnings or lint errors in 128 files

$ php artisan test
TEST_EXIT=0
Tests:  559 passed (2252 assertions)

$ php vendor/bin/pint --test app/Http/Controllers/DashboardController.php tests/Feature/DashboardTest.php
PINT_EXIT=0
PASS ... 2 files

$ php vendor/bin/phpstan analyse app/Http/Controllers/DashboardController.php
PHPSTAN_EXIT=0
[OK] No errors
```

`php artisan test` is a full-suite pass here, not a subset: the suite runs on SQLite
`:memory:` from `phpunit.xml`, so it needed no MySQL provisioning. The three new dashboard
tests assert the computed rates/deltas (`4 sent / 50% open / 25% reply`, previous window
`2 sent / 50% open / 0% reply`), the jump-back-in counts, and that another team's rows do not
leak into either payload (rates and deltas are `null`, not `0`).

Render checks at 1280 (headless Chromium against `php artisan serve`, seeded with 12
contacts / 3 lists / 2 active + 1 draft campaign / 14 templates / 4 folders / 3 connections
of which 1 threw / 40 sent, 18 opened, 3 replied in-window plus 30/10/1 in the prior window /
one Harbor Labs invitation):

- Dashboard, 3/3 setup: cards, deltas `+33.3% / +11.7pt / +4.2pt`, banner, jump rows match
  the board's structure; cards top-aligned.
- Dashboard, 2/3 setup: progress at 66%, strikethrough done rows, `Go to Campaigns` on the
  pending row only, empty rates as `—`.
- `Review` click (CDP): `modal open, rows=1`; the existing invitation modal renders over the
  dashboard and offers Accept/Decline.
- `/login`, `/settings/profile`, `/` (welcome) and the campaigns list empty state: no
  regression from the phase 1 primitives.

The seeded numbers are a render fixture, not committed; the committed tests assert the
arithmetic independently.

## Gaps and open questions

1. **No index on `campaign_emails.dispatched_at`.** The 30-day aggregate scans the team's
   sent emails. A migration adding `(campaign_id, dispatched_at)` is the fix; migrations are
   out of scope for this phase.
2. **Active-campaign delta** needs a history table or event log; the card has no chip.
3. **Definition of "emails sent"** counts every row with `dispatched_at` set, so bounced
   emails count as sent and there is no delivered-only filter. Open/reply are per-email
   timestamps, so they count emails, not raw open events.
4. **Template/contact qualifiers count everything** — draft templates and do-not-contact
   contacts included. The board gives no scoping hint.
5. **Board copy vs data**: the board's `2 of 3 done` + populated campaigns cannot coexist
   (deviation 3); its `Team · 4 members` still cannot be rendered without a member count.
6. **The campaigns search sync** is type-checked, not click-tested; a browser interaction
   with the Radix status select was not scripted.
7. **Dark mode** remains untouched; the new banner/card surfaces only define light values.
8. **No JS test runner** exists in this worktree, so no snapshots were added or updated.
9. **`local:devtest` cleanup**: the staged tree `/home/cua/work/campaigns-phase2` is kept for
   the next phase; `/tmp/campaigns-phase2-src.tar.gz` was removed from the host and can be
   deleted in the Space.
