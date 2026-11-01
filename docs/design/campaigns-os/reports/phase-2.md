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
  `--banner` + `border-accent` + `--primary` text, and widened with a second variant
  (`message` + `actionSlot`) so the dashboard banner reuses this component instead of a
  parallel one. The original `{invitation, action}` auth variant keeps its `InfoIcon` and
  copy, so `login.tsx` and `register.tsx` are untouched.
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

Every plan below comes from SQLite `EXPLAIN QUERY PLAN` (the suite and the Space render used
SQLite), not MySQL. The schema and indexes are the same, but MySQL's optimizer may pick a
different index.

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

   Plan: `SEARCH campaign_emails USING INDEX campaign_emails_campaign_id_index
   (campaign_id=?)` plus a covering `campaigns_team_id_index` subquery. The `campaign_id`
   index narrows the scan to the team's sent emails, but the migration indexes only
   `campaign_id`, `campaign_step_id`, `recipient_id`, `status` and `scheduled_at` — **there
   is no index on `dispatched_at`**, so the date filter is applied per row, not as a range
   seek. Cost scales with the team's total sent emails rather than the 30-day slice; this is
   the most expensive query on the page. Computing both windows in one statement means the
   three delta chips add **zero** extra queries.

2. `select count(*) from campaigns where team_id = ? and status = 'Active'` — plan:
   `SEARCH campaigns USING INDEX campaigns_status_index (status=?)`. The status index is
   single-column, so the scan covers every team's Active campaigns and filters `team_id`
   afterwards. Feeds the "Active campaigns" card and the Campaigns qualifier.
3. `select count(*) from campaigns where team_id = ? and status = 'Draft'` — same plan and
   cost.
4. `select count(*) from contacts where team_id = ?` — covering
   `contacts_team_id_status_index (team_id=?)`, an index-only count over the team's contacts.
5. `select count(*) from contact_lists where team_id = ?` — covering
   `contact_lists_team_id_is_default_index (team_id=?)`, index-only.
6. `select count(*) from email_templates where team_id = ?` — covering
   `email_templates_team_id_index (team_id=?)`, index-only.
7. `select count(*) from template_folders where team_id = ?` — covering
   `template_folders_team_id_index (team_id=?)`, index-only.
8. `select * from mailer_connections where team_id = ? order by name` — `SEARCH
   mailer_connections USING INDEX mailer_connections_team_id_index (team_id=?)` plus
   `USE TEMP B-TREE FOR ORDER BY`; the sort is over the team's handful of connections. One
   query supplies the row count, the "needs attention" count
   (`exception_type is not null`) and the create modal's options.

The page now issues 8 extra queries on top of the existing invitation query and the three
`exists()` setup queries. Nothing is cached; there is no history table, so "Active
campaigns" has no delta (see gaps).

## Board 01 A requirements → what shipped

| Requirement | Where |
| --- | --- |
| Greeting H1 from time of day + first name, subtitle team · date | `dashboard.tsx` via `PageHead`; `auth.user.name`, `currentTeam.name`, `Intl.DateTimeFormat` |
| Breadcrumb bar hidden | `Dashboard.layout` sets `showHeader: false` |
| `New campaign` primary top-right | `CreateCampaignModal` with `mailerConnections`; rendered only when `can.create` |
| Full-width invitation banner, `--banner`, `Review` | `TeamInvitationAlert` message variant, opens `PendingInvitationsModal` |
| Four stat cards, 12px muted label, 20/700 value, green delta | `StatCard` local component; deltas only where computed |
| `Finish setting up`, `2 of 3 done`, progress bar, done/undone icons, strikethrough done rows | setup card; `--primary` bar, `Check` in a 20px filled circle, empty 20px ring |
| Secondary CTA on the pending row | `Go to Connections` / `Go to Contacts` / `Go to Campaigns` per undone row |
| `Jump back in`, 32px tinted tile, title, qualifier, chevron, ~60px rows | jump card; `bg-accent` tiles, real counts in the qualifier |
| 1280 layout: 4 stat cards at 12px gap, 560/440 panel split, 24px page padding | `xl:grid-cols-4 gap-3`, `lg:grid-cols-[minmax(0,14fr)_minmax(0,11fr)]`, `px-4 py-6 lg:px-6` |
| No hex outside `app.css`, no second type scale | only semantic tokens and `text-[20px]/[16px]/[13px]/[12px]` one-offs |

## Deviations

1. **The banner reuses `team-invitation-alert.tsx`, which had no dashboard use before.**
   The component now has two variants: its original `{invitation, action}` form for
   login/register (unchanged, still with the `InfoIcon`) and a `{message, actionSlot}` form
   the dashboard renders with the "You have N pending team invitation(s) from …" copy and
   the `Review` button that opens the existing modal. The board banner has no icon, and the
   message variant renders none. The modal no longer auto-opens on page load; the board
   shows the banner, and an auto-open modal would cover it.
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
`composer install` and `npm ci` ran in the Space. The final run below is on the clean tree —
the temporary seeder, preview-login route and SQLite database were deleted before it, and
only an untracked `.env` remained. Nothing was installed, built or served on the host.

```
$ npm run build
BUILD_EXIT=0
✓ built in 2.21s

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

`php artisan test` is a full-suite pass, not a subset: the suite runs on SQLite `:memory:`
from `phpunit.xml`, so it needed no MySQL provisioning. The three new dashboard tests assert
the computed rates/deltas (`4 sent / 50% open / 25% reply`, previous window `2 sent / 50%
open / 0% reply`), the jump-back-in counts, and that another team's rows do not leak into
either payload (rates and deltas are `null`, not `0`).

Query-plan evidence (SQLite, empty migrated schema, `EXPLAIN QUERY PLAN`; not MySQL):

```
email metrics (30d + previous window)
  SEARCH campaign_emails USING INDEX campaign_emails_campaign_id_index (campaign_id=?)
  LIST SUBQUERY 1
  SEARCH campaigns USING COVERING INDEX campaigns_team_id_index (team_id=?)
active campaigns count
  SEARCH campaigns USING INDEX campaigns_status_index (status=?)
draft campaigns count
  SEARCH campaigns USING INDEX campaigns_status_index (status=?)
contacts count
  SEARCH contacts USING COVERING INDEX contacts_team_id_status_index (team_id=?)
contact lists count
  SEARCH contact_lists USING COVERING INDEX contact_lists_team_id_is_default_index (team_id=?)
email templates count
  SEARCH email_templates USING COVERING INDEX email_templates_team_id_index (team_id=?)
template folders count
  SEARCH template_folders USING COVERING INDEX template_folders_team_id_index (team_id=?)
mailer connections
  SEARCH mailer_connections USING INDEX mailer_connections_team_id_index (team_id=?)
  USE TEMP B-TREE FOR ORDER BY
```

Render checks at 1280 (headless Chromium against `php artisan serve`, seeded with 12
contacts / 3 lists / 2 active + 1 draft campaign / 14 templates / 4 folders / 3 connections
of which 1 threw / 40 sent, 18 opened, 3 replied in-window plus 30/10/1 in the prior window /
one Harbor Labs invitation):

- Dashboard, 3/3 setup: cards, deltas `+33.3% / +11.7pt / +4.2pt`, the component banner,
  and the jump rows match the board's structure; cards top-aligned.
- Dashboard, 2/3 setup (campaigns removed): progress at 66%, strikethrough done rows,
  `Go to Campaigns` on the pending row only, empty rates as `—`.
- `Review` click (CDP, after the banner moved into the alert component):
  `modal open, rows=1`; the existing invitation modal renders over the dashboard.
- `/login?invitation=<code>`: the auth variant of the restyled alert renders on the banner
  token with its icon and `Log in to join the "Harbor Labs" team.` copy, no overlap.
- `/settings/profile`, `/` (welcome, `size="lg"` primary) and the campaigns list empty
  state: no regression from the phase 1 primitives.

The seeded numbers are a render fixture, not committed; the committed tests assert the
arithmetic independently.

## Gaps and open questions

1. **No index on `campaign_emails.dispatched_at`.** The 30-day aggregate scans the team's
   sent emails (see the plan above). A migration adding `(campaign_id, dispatched_at)` is
   the fix; migrations are out of scope for this phase.
2. **Campaign status counts use the single-column `campaigns_status_index`**, so they scan
   every team's Active/Draft rows. A `(team_id, status)` index would be the fix; also a
   migration.
3. **Active-campaign delta** needs a history table or event log; the card has no chip.
4. **Definition of "emails sent"** counts every row with `dispatched_at` set, so bounced
   emails count as sent and there is no delivered-only filter. Open/reply are per-email
   timestamps, so they count emails, not raw open events.
5. **Template/contact qualifiers count everything** — draft templates and do-not-contact
   contacts included. The board gives no scoping hint.
6. **Board copy vs data**: the board's `2 of 3 done` + populated campaigns cannot coexist
   (deviation 3); its `Team · 4 members` still cannot be rendered without a member count.
7. **The campaigns search sync** is type-checked, not click-tested; a browser interaction
   with the Radix status select was not scripted.
8. **Dark mode** remains untouched; the new banner/card surfaces only define light values.
9. **No JS test runner** exists in this worktree, so no snapshots were added or updated.
10. **`local:devtest` cleanup**: the staged tree `/home/cua/work/campaigns-phase2` is kept for
    the next phase. `/tmp/campaigns-phase2-src.tar.gz` was deleted on the host and in the
    Space, and the temporary seeder, preview route and SQLite database were removed.

## Fix round — independent review of phases 1–2

The review (`review/phases-1-2.md`) returned BLOCK on three P1s and 22 P2s. This round fixed
P1-1..3, P2-1..7, P2-9..19 and P2-21..22; P2-8 (the duplicated connection-options builder) is
left for phase 5, where the connections payload is touched anyway.

### Corrections to earlier claims

The phase 1 and phase 2 reports said the table rows and stat gap "match the board". They did
not, and those claims were wrong:

- **Rows:** board 02 A dividers are at y 211/255/299/343/387/431, a **44px** pitch. Phase 1
  shipped ~47px and called it 46px. It is now 44px.
- **Stat gap:** board 01 A cards sit at x 240–484 and 497–741, a **12px** gap. Phase 2 shipped
  8px and claimed 8px was the board. It is now 12px.
- **Jump rows:** board 01 A rows are ~60px, not the 64px the report claimed; the implementation
  was 61px and stays 61px.
- **Page head:** the board's H1 cap top is at y 23; the implementation was ~31. It is now 22–23.

From phase 3 on, a report must quote measured board coordinates and the matching render
coordinates, not "matches".

### P1

1. **P1-1** — destructive `Button` is back to `text-white`. Dark `--destructive` is
   `oklch(0.396 0.141 25.7)` ≈ `#80181a`; white on it is **10.18:1**, where
   `--primary-foreground` (near-black) was **1.95:1**.
2. **P1-2** — `.dark` gained additive values for the new tokens, and three paired foreground
   tokens were added because the existing ones could not express the semantic (banner text was
   near-white in dark, danger/neutral pills read colours that fail on dark fills). No existing
   `.dark` value changed. Measured: banner `#c9c2ff` on `#221d3a` **9.69:1**; success pill
   **9.59:1**; warning **9.43:1**; danger **8.47:1**; neutral **10.24:1**; `--primary-border`
   `#8b7dff` **6.10:1** on the dark page. Values and reasoning are in the spec's Amendments
   section. The alert's old `dark:` one-offs are gone.
3. **P1-3** — `--ring` is `#6552ff` (**4.99:1** on white; the board's `#b8afff` was **1.98:1**
   and the halo **1.16:1**). Buttons, inputs, selects, checkboxes and the jump rows now use a
   **2px** ring (`--primary-border`, `#8b7dff` in dark, **6.10:1**), because `.dark --ring` is
   frozen at **2.53:1**. Recorded as an accessibility waiver in the spec.

### P2

- **P2-1** `isFirstRun = campaigns.total === 0 && !hasFilters`; an out-of-range page no longer
  renders "No campaigns yet" or hides the toolbar.
- **P2-2** the status filter now navigates with `q: search`, so the typed search is applied
  instead of silently kept in local state.
- **P2-3** `isCurrentOrParentUrl` matches whole path segments and treats `/` as an exact path,
  so the six no-team fallbacks no longer light up every nav item.
- **P2-4** inputs are `text-base md:text-body`, restoring 16px on mobile to stop iOS zoom.
- **P2-5** the sidebar is `collapsible="none"` (no collapse on the boards) and the primitive
  still renders the mobile sheet: the static branch now comes after the mobile branch and is
  `hidden md:flex`.
- **P2-6** contact pills map NotContacted→neutral, Contacted→info, Replied→success,
  Bounced→destructive, Unsubscribed→neutral, DoNotContact→warning; connection pills map
  Active→success, Pending→warning, Deactivated/Disconnected→neutral.
- **P2-7** the banner variant is `role="status"`, not `role="alert"`.
- **P2-9** needs-attention is `status !== Active || exception_type !== null`; "inboxes" counts
  every connection row. The mapping is in the controller docblock and one test row per enum
  case exists (Active clean, Active with exception, Pending, Deactivated, Disconnected → 5
  connections, 4 flagged).
- **P2-10** the cohort method is kept; the age bias (a fresh send has had less time to open
  than one from the previous window, so the delta leans negative) is recorded in the controller
  docblock and in gaps below.
- **P2-11** the metrics fixture now has 3 sent / 1 opened / 1 replied in-window (33.3%, the
  1-of-3 rounding case) against 2 / 1 / 1 in the previous window (50%), giving a negative
  `-16.7pt` delta on both rates; an email at 70 days proves the lower window bound; `can.create`
  and the four `mailerConnections.0` fields are asserted.
- **P2-12** deltas use `--badge-success-foreground` (**5.52:1**; `--success` was **3.30:1**) and
  `--badge-danger-foreground` for negative deltas.
- **P2-13** table header is `bg-muted/50` (**4.63:1**, was **4.40:1**); neutral pill foreground
  `#52525b` (**7.03:1**); danger pill foreground `#b91c1c` (**5.91:1**).
- **P2-14** each checklist row has `sr-only` "Done:"/"To do:" before the title and both icons are
  `aria-hidden`.
- **P2-15** nav pitch 36px (`SidebarMenu gap-0`), brand mark `size-6` with 6px radius, no border
  above Settings, full-bleed divider above the user block.
- **P2-16** `PageHead` is `items-center` with `-mt-2`, so the title block starts at 16px; rendered
  H1 cap top is 22px against the board's 23px.
- **P2-17** table cells `px-4`, header `h-[34px]` (35px row), name cell `py-[3.5px]` (44px rows), column widths 120/80/116/64, footer 52px.
- **P2-18** the true empty state is the shared `EmptyState` with a dashed border, 64px icon tile
  and a 12px gap to the head; the filtered-empty state reuses it inside the table card.
- **P2-19** stat gap 12px, stat value 24px (`--text-stat`), list dividers inset 20px via `mx-5`,
  jump card `pb-5`.
- **P2-21** `--text-h1/h3/body/label` (and `--text-stat`) are in `@theme`; every
  `text-[NNpx]` one-off is gone from TSX, including 14px/11px brand and sidebar text, which now
  use `text-body`/`text-label`.
- **P2-22** `StatCard` moved to `components/dashboard/stat-card.tsx`; `EmptyState` restyled to
  the 10 A pattern and used by `campaigns/index.tsx`.

### Verification (fix round)

Space `local:devtest`, staged from the working tree (24 dirty paths at staging), installs reused
from the phase run. Final tree: temporary seeder, preview route and SQLite database removed.

```
$ npm run build            BUILD_EXIT=0   ✓ built
$ npm run types:check      TYPES_EXIT=0
$ npm run check            CHECK_EXIT=0   All 146 files formatted, 129 lint-clean
$ php artisan test         TEST_EXIT=0    560 passed (2276 assertions)
$ pint --test ...          PINT_EXIT=0    PASS 2 files
$ phpstan analyse ...      PHPSTAN_EXIT=0 [OK] No errors
```

Rendered at 1280 in the Space and measured against the board with ImageMagick:

| Element | Board | Render |
| --- | --- | --- |
| Stat-card edges | 240/484, 497/741 (gap 12) | 240/484, 497/741 |
| Table row dividers | 211/255/299/343/387/431 (44px) | 171/215/259/303 (44px) |
| Table header | 35px | 34px tint + 1px border |
| Footer | 52px | 52px |
| Empty icon tile | 716–779 (64px) | 716–779 |
| Nav active pill | 119–152, next 155–188 (36px pitch) | 130–165 (36px) |
| H1 cap top | 23 | 22 |

Dark-mode screenshots: dashboard, campaigns list, `/login?invitation=…`, `/settings/profile`,
welcome. The banner is a dark violet surface with light violet text, pills are dark fills with
light foregrounds, the primary button is white-on-near-black, and the destructive button is
white-on-`#80181a` (10.18:1). A keyboard Tab through the dashboard shows the 2px violet focus
ring on the Campaigns nav item; programmatic `.focus()` does not trigger `:focus-visible`, so
the check used CDP key events.

The campaigns search fix and the out-of-range-page empty state are type-checked, not
click-tested.

### Gaps after the fix round

- **Cohort age bias** is documented but not removed; same-age cohorts are the follow-up.
- **P2-8** (one connection-options builder) stays open for phase 5.
- **`--ring-halo`** is now unused by focus treatments; it remains a decoration token.
- **`local:devtest`** keeps `/home/cua/work/campaigns-phase2` for the next phase; the source
  tarball was deleted on the host and in the Space.

## Round 2 — review of the fix round

The second review blocked on R2-1 (the static sidebar scrolled with the page and lost its right
border) and the R2-0 residual (the destructive variant's red 20% ring won over the visible ring).
Both are fixed, along with R2-3..7. R2-2 was accepted as-is.

### Fixes

- **R2-1** — the `collapsible="none"` branch is `sticky top-0 h-svh border-r
  border-sidebar-border`. Measured on a 25-row list: document 1348px against an 813px viewport;
  after scrolling to the bottom (`scrollY 535`) the sidebar top is 0, its height is 813, the
  user block is inside the viewport, and the computed right border is `1px rgb(228, 228, 231)`.
- **R2-0** — `focus-visible:ring-destructive/20` is gone from the destructive Button. A
  keyboard Tab to `/settings/profile`'s `Delete account` now computes
  `rgb(101, 82, 255) 0 0 0 2px` (the 2px `--primary-border` ring, 4.99:1) with a `#6552ff`
  border, instead of the 1.37:1 red ring.
- **R2-3** — the info pill has `--badge-info-border` (`#b8afff`, dark `#8b7dff`); `--ring` no
  longer controls it. The dark border is 4.46:1 on the dark info fill and 6.10:1 on the page.
- **R2-4** — `--text-*` tokens now carry no line height; every call site sets `leading-*`
  explicitly. The team card measures **50px** (was 54px, board 50px) and the user block is back
  to its 31px content height.
- **R2-5** — the wordmark is `text-brand` (14px) with `font-bold`, measured 14px/700 in the
  render; the settings passkey chip is back to `text-caption` (11px), and avatar initials use
  the same 11px caption.
- **R2-6** — `text-stat leading-7` (24/28) makes the stat card **84px**, matching 01 A (was
  88px with the 32px line height).
- **R2-7** — an out-of-range page with no filters now says "This page has no campaigns" with a
  "Back to page 1" link; "No campaigns match your filters" and `Clear filters` only appear
  when a filter is active. Verified with 25 campaigns and `?page=2`.
- **R2-2 (accepted)** — `SidebarTrigger` stays for the mobile sheet; the shortcut and
  `sidebar_state` cookie are untouched. Noted in the spec Amendments as a known dead desktop
  affordance until phase 6.

### Verification (round 2)

Same Space (`local:devtest`), same checks. Final tree after removing the temporary seeder,
preview route and SQLite database:

```
$ npm run build            BUILD_EXIT=0
$ npm run types:check      TYPES_EXIT=0
$ npm run check            CHECK_EXIT=0   All 146 files formatted, 129 lint-clean
$ php artisan test         TEST_EXIT=0    560 passed (2276 assertions)
$ pint --test ...          PINT_EXIT=0    PASS 2 files
$ phpstan analyse ...      PHPSTAN_EXIT=0 [OK] No errors
```

Render measurements (headless Chromium, 1280×900, 25 seeded campaigns):

| Item | Board | Render |
| --- | --- | --- |
| Sidebar top after scrolling 535px | pinned | 0 |
| Sidebar height | viewport | 813 (viewport 813) |
| Sidebar right border | 1px `#EAEAEA` | 1px `rgb(228,228,231)` |
| Team card height | 50px | 50px |
| Stat card height | 84px | 84px |
| Wordmark | 14px/700 | 14px/700 |
| Destructive focus ring | 3:1 visible | 2px `#6552ff` (4.99:1) |

Screenshots: the 25-row list at the top and scrolled (Settings/user still pinned), the
out-of-range page, the dashboard stat cards, and the focused destructive button.
