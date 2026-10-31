# Phase 5 — templates list, template editor, connections

Boards 07 A, 08 A and 09 A, plus the two phase-4 review carry-overs. Every geometry
number below is measured: the board column comes from an ImageMagick pixel scan of the
1x JPEG, the render column from DOM rects in a 1280×900 headless Chromium render in the
cua Space `local:devtest`. Renders are in `reports/phase-5/`.

## What changed

Backend (no migrations, no route or permission changes):

- `app/Enums/MailerConnectionDisplayStatus.php` (new) — the derived pill status.
- `app/Models/MailerConnection.php` — `displayStatus()`, `needsAttention()`,
  `optionPayload()`, `optionsForTeam()`.
- `app/Http/Controllers/Campaigns/CampaignController.php` — the private
  `mailerConnectionOptions()` is gone; both call sites read
  `MailerConnection::optionsForTeam()` (P2-8).
- `app/Http/Controllers/DashboardController.php` — the copied mapping and the inline
  needs-attention expression read `optionPayload()` / `needsAttention()`.
- `app/Http/Controllers/MailerConnections/MailerConnectionController.php` — one
  read-only `sent_today` aggregate and five additive payload fields
  (`sent_today`, `display_status`, `display_status_label`, `needs_attention`,
  `last_error_message`).

Frontend:

- `resources/js/pages/templates/index.tsx` — rebuilt to 07 A.
- `resources/js/pages/templates/show.tsx` — rebuilt to 08 A (controlled form, Discard,
  dirty guard, placeholders rail).
- `resources/js/pages/mailer-connections/index.tsx` — rebuilt to 09 A.
- `resources/js/components/templates/placeholder-manager.tsx` — chips, fallback column,
  `+ Add placeholder`, hover delete.
- `resources/js/components/templates/rich-text-editor.tsx` — optional `insertRef` so the
  rail can insert at the cursor (no toolbar change; the step editor is untouched).
- `resources/js/components/mailer-connections/cap-meter.tsx` (new).
- `resources/js/components/mailer-connections/status-badge.tsx` — reads the derived
  display status.
- `resources/js/components/mailer-connections/signature-select.tsx` — text-styled select.
- `resources/js/components/page-head.tsx` — `back.onClick` so the editor can guard the
  back link. Existing callers are unaffected.
- `resources/js/pages/contacts/index.tsx` — R2-1.
- `resources/js/components/contacts/add-to-campaign-modal.tsx` — R2-2.
- `resources/js/types/mailer-connections.ts` — the new fields and display status type.

## The new aggregate

`MailerConnectionController::sentTodayByConnection()` runs once per index visit:

```sql
select mailer_connection_id, count(*) as sent_today
from campaign_emails
where mailer_connection_id in (?, ?, ?)
  and dispatched_at >= ?
group by mailer_connection_id
```

The day boundary is **00:00 UTC** because that is where `SendEmail` resets the same
counter (`app/Jobs/Campaigns/SendEmail.php:366`,
`CarbonImmutable::today('UTC')->addDay()`). `sent_count` is a lazily reset per-UTC-day
counter, not a send log, so the column counts dispatched rows instead of trusting it.

**Cost.** `campaign_emails` has indexes on `campaign_id`, `campaign_step_id`,
`recipient_id`, `status` and `scheduled_at` — none on `mailer_connection_id` or
`dispatched_at`. MySQL 8.0.46, 50,000 rows, 7,811 dispatched inside the window:

```
EXPLAIN ANALYZE  (no useful index)
-> Table scan on <temporary>  (actual time=9.45..9.45 rows=3 loops=1)
    -> Aggregate using temporary table  (actual time=9.45..9.45 rows=3 loops=1)
        -> Filter: ((mailer_connection_id in (1,2,3)) and (dispatched_at >= TIMESTAMP'2026-10-31 00:00:00'))
             (cost=5063 rows=4991) (actual time=0.0201..8.81 rows=7811 loops=1)
            -> Table scan on campaign_emails  (cost=5063 rows=49912) (actual time=0.0161..5.2 rows=50000 loops=1)
```

With the index a migration would add
(`(mailer_connection_id, dispatched_at)`), the same statement is a covering range scan and
no temporary table:

```
-> Group aggregate: count(0)  (cost=2365 rows=2) (actual time=0.532..1.53 rows=3 loops=1)
    -> Filter: (...) (actual time=0.0208..1.34 rows=7811 loops=1)
        -> Covering index range scan on campaign_emails using probe_connection_dispatched
             (actual time=0.0188..0.749 rows=7811 loops=1)
```

So: **8.81 ms → 1.53 ms** at 50k rows, scaling with the total row count until the index
lands. The render fixture is SQLite and plans the same shape:

```
EXPLAIN QUERY PLAN ...
SCAN campaign_emails
USE TEMP B-TREE FOR GROUP BY
(rows=530 in the fixture)
```

The query is skipped entirely when a team has no connections, so the empty state costs
nothing.

## Status mapping, unified

`MailerConnectionDisplayStatus::derive($status, $hasException)`:

| Stored | Exception | Pill |
| --- | --- | --- |
| Active | no | `Verified` (success) |
| any | yes | `Failed` (danger) |
| Pending | no | `Pending` (warning) |
| Deactivated | no | `Deactivated` (neutral) |
| Disconnected | no | `Disconnected` (neutral) |

`MailerConnection::needsAttention()` is `displayStatus() !== Verified`, which is exactly
the reviewed rule `status !== Active || exception_type !== null`. The dashboard's count,
the connections table and the campaign start-blocked panel now all read the same methods,
so a row that shows `Verified` cannot be counted as needing attention, and an Active
connection with an exception is `Failed` rather than green. The phase-1 P2-8 copy is gone:
`MailerConnection::optionPayload()` is the only connection-option mapper, called by the
campaigns index, the campaign detail page and the dashboard.

## Placeholders: the phase-3 note, corrected

The phase-3 report's gap 3 ("placeholder fallbacks do not exist in the schema") is true
for **campaign steps**, which own no placeholder rows. It is not true for the template
editor: `placeholders.fallback` is a nullable column
(`2026_09_24_044656_create_placeholders_table.php:23`) and `TemplateController::show()`
already sends it. So the rail renders the stored value and nothing is invented:

- `{{first_name}}` → `there`, `{{company}}` → `your team`, `{{sender_name}}` → `Sam`.
- `{{title}}` has no fallback and renders `—`. The brief says "otherwise render the chip
  alone"; 08 A puts an em dash in that slot, and an em dash is not a value, so the board's
  treatment ships. The column stays aligned and the gap is visible rather than ambiguous.

Chips insert `{{name}}` at the cursor (verified: clicking `{{first_name}}` added it to the
body). `+ Add placeholder` opens the existing inline form (name / fallback / type) and the
existing delete route is a hover/focus control on the row, so add and delete still work.

## Board vs render

**07 A — templates list.** Board content column x 240–1255 (`+`1px border); render
x 240–1256.

| Element | Board 07 A | Render |
| --- | --- | --- |
| H1 cap top | 22 | 21 (box y 16, 20/700) |
| `New folder` | x 1011–1119, 109×32 | x 1002–1116, 114×32 |
| `New template` | x 1128–1256, 129×32 | x 1124–1256, 132×32 |
| Search | x 240–500, y 81–113, 260×32 | x 240–500, y 84–116, 260×32 |
| Count text | x 1123–1254, 12px | x 1130–1256, 12px |
| Group 1 | y 125, h 159 | y 128, h 158 |
| Group header row | 38–39px, tint `#fafafa` | 39px, `bg-muted/50` = `#fafafa` |
| Header folder icon x | 257–271 | 257–272 |
| Header name x | 283 | 281 |
| Child file icon x | 259–270 | 259–270 |
| Child name x | 285 | 281 |
| Row pitch | 39 (38 + rule) | 39 |
| `Updated …` | x 1120–1210 | x 1118–1203 |
| `Draft` pill | x 1034–1078, 45×21 | x 1030–1076, 46×22 |
| Kebab icon | 1223–1239 | 1223–1239 (button 1217–1245) |
| Group gap | 12 | 12 |

**08 A — template editor.**

| Element | Board 08 A | Render |
| --- | --- | --- |
| H1 cap top / x | 22 / 268 | 21 / 270 |
| Back chevron box | ≈234–258 | 234–258 |
| `Discard` | x 1068–1137, 69×32 | x 1060–1134, 74×32 |
| `Save template` | x 1144–1256, 112×32 | x 1142–1256, 114×32 |
| `Name` input | x 240–703, 464×35 | x 240–705, 465×36 |
| `Folder` input | x 716–935, 220×36 | x 717–937, 220×36 |
| `Subject` | y 179–213, 696 wide | y 176–212, 697 wide |
| Body editor card | x 240–936, y 251–573 (696×322) | x 240–936, y 245–567 (696×322) |
| Body text area | 280 tall | 280 tall |
| Placeholders rail | x 956–1255, 299×301 | x 957–1256, 299×298 |
| Rail title x | 972 | 974 |
| Chip | x 973–1067, 94×21 | x 974–1072, 98×22 |
| Chip value right edge | 1237 | 1239 |
| `+ Add placeholder` | 972–1240, 268×28 | 974–1239, 265×28 |
| `Draft` pill (template 3) | y 29, 21px | y 29, 46×22 |

**09 A — connections.**

| Element | Board 09 A | Render |
| --- | --- | --- |
| Banner | x 240–1255, y 81–130, 50 tall | x 240–1255, y 84–134, 50 tall |
| `Retry` | x 1164–1240, 76×28 | x 1164–1239, 75×28 |
| Table | y 142–310 | y 147–312 |
| Header row | 33 | 33 |
| Row pitch | 44 | 44 |
| Name text x | 257 | 257 |
| Type pill x | 437 | 436 |
| Server text x | 527 | 527 |
| From text x | 677 | 677 |
| Signature x | 847 | 847 |
| Status pill x | 957 | 956 |
| Sent today text x | 1057 | 1056 |
| Meter | x 1057–1168, 112×4 | x 1056–1168, 112×4 |
| Meter fill, 142/400 | ≈40px violet | 39.8px violet |
| Meter fill, 388/400 | ≈109px red | 109px red |
| Kebab icon centre | 1207 | 1209 |
| Footer note | 12px at y 327–338 | 12px at y 327–343 (box) |

The `+3px` on the first toolbar row of 07 A is the shared page-head block, not this phase:
the existing campaigns list renders its search at the same y 84 in the same render.

**Dark mode.** `reports/phase-5/07-templates-1280-dark.png`,
`08-template-editor-1280-dark.png`, `09-connections-1280-dark.png`. No new colour classes
and no new tokens were needed: the phase-2 dark pairs already cover every surface these
screens use. Folder icons are `text-primary` (near-white in dark), the Draft pill is
`--badge-neutral-foreground` on `--muted`, the failure banner is `--badge-danger` /
`--badge-danger-foreground`, and the meters read `--primary`, `--destructive` and `--muted`.

## Phase-4 carry-overs

**R2-1 — filtered bulk-delete failure.** `restoreAfterDelete(deleted, force, onDone)` now
takes `force` and a completion callback. On the delete failure path the page calls
`restoreAfterDelete(done, true, () => setSelected(ids.slice(done)))` instead of a bare
`router.reload`, so the visit restores `q`, `list` and the page number the loop started on
and the re-selection happens after that page lands. The success path is unchanged
(`restoreAfterDelete(done)`).

**R2-2 — Add to campaign.** The dialog issues `router.reload({ only: ['campaigns'] })` when
it opens, disables its submit while that reload is in flight, and refuses a submit whose
campaign is no longer in the refreshed (Draft/Stopped-only) list, clearing the choice and
showing "That campaign can no longer receive contacts." Verified over CDP: opening the
dialog produced exactly one request with `X-Inertia-Partial-Data: campaigns`, and the
submit stayed disabled until a campaign was chosen. The residual race — another member
starts the campaign between the reload and the POST — cannot be closed here because
`CampaignRecipientController::store()` intentionally accepts running campaigns; a server
guard needs an `idle_only` flag on the request, which the review called optional.

## Verification

Provenance: the dirty host worktree (17 changed/new files, `git rev-parse --short HEAD`
= `18a2af0`) was tarred to `/home/cua/work/campaigns-phase5` over the phase-4 tree's
`vendor/` and `node_modules/`; nothing was installed, built, served or tested on the host.
Every changed file was md5-compared between the Space tree and the host worktree after the
final checks and matched. Fixture (temporary, removed from the space tree before the final
run): Northwind Growth / `preview@northwind.io`, connections `Sam Patel · Gmail`
(Active/Verified, cap 400), `Outbound team` (Active/Verified, cap 400), `Mailgun relay`
(Deactivated + `TransportException` "535 Authentication failed. Check the username and
password.", cap 1,000) and, for one extra shot, `Zz backup relay` (Pending, no cap);
folders `Cold outreach` (3), `Follow-ups` (2), `Events` (1), `Q1 experiments` (0) and one
unfiled template; four placeholders (`there`, `your team`, null, `Sam`); 530
`campaign_emails` dispatched today (142 / 388 / 0).

Final checks on the tree that matches the commit (no preview route, no seeder, no render
scripts):

```
$ npm run build            BUILD_EXIT=0    ✓ built in 3.3s
$ npm run types:check      TYPES_EXIT=0    tsc --noEmit
$ npm run check            CHECK_EXIT=0    All 157 files are correctly formatted
                                           Found no warnings or lint errors in 140 files
$ php artisan test         TEST_EXIT=0     Tests: 571 passed (2485 assertions), 9.07s
$ php vendor/bin/pint --test  PINT_EXIT=0  PASS 366 files
$ php vendor/bin/phpstan analyse … PHPSTAN_EXIT=0  [OK] No errors
```

Interactions driven over CDP on that build:

- Search `series` → count `1 template · 1 folder`, one group with one row; clearing
  restores all groups.
- Folder kebab → `Rename` / `Delete`; `Rename` opens "Rename folder" with the current
  name. Template kebab → `Open` / `Delete`; the delete dialog is the existing modal.
- Editor: changing `Name` makes `Discard` act as a guard — "Discard your changes?" with
  `Discard changes` / `Cancel`; confirming restored `Quick idea for {{company}}`.
- Clicking a rail chip inserted `{{first_name}}` into the body.
- `+ Add placeholder` created `{{company_size}}` with fallback `50-200`; the rail shows it.
- `Save template` → toast `Template updated.`, subtitle `Cold outreach · Saved just now`,
  and a reload showed the new name (`Quick idea v3`) — this caught a real bug: the first
  build called `form.put()` against a PATCH route and got a 405; the shipped call is
  `form.patch()`.
- Connections: row kebab → `Verify` / `Edit` / `Delete`; the primary button opens
  `Connect Gmail` / `Connect Outlook` (fresh OAuth routes unchanged). `Retry` is the same
  verify `Form`.
- No-cap connection (`sending_limit` null) renders `—` in `Sent today`
  (`09-connections-nocap-1280.png`).

## Deviations

1. **Page-head offset (+3px).** Board toolbar y 81, render y 84 on all three screens; the
   campaigns list renders at the same y, so this is the shared page-head block from phase 1
   (H1 28px line box + 20px gap), not a phase-5 change. Not adjusted, to keep the shell
   consistent.
2. **"Child rows indented one step" (brief) vs the board.** 07 A measures the folder icon
   at x 257 and the child file icon at x 259, names 283 vs 285 — a 2px SVG-artwork offset,
   not an indent. The render reproduces it exactly (257 / 259, names 281), so child rows
   align with their group header rather than stepping in. Matching the indent the brief
   describes would move the board's child rows.
3. **Folder group order** is the controller's `orderBy('name')` (Cold outreach, Events,
   Follow-ups, Q1 experiments, No folder); the board lists Cold outreach, Follow-ups,
   Events. Left data-driven.
4. **Empty folders** render a muted "No templates in this folder yet." row under the
   header, and **unfiled templates** get a trailing "No folder" group. The board has
   neither (its "14 templates · 3 folders" does not reconcile with the six rows it shows);
   both cases exist in the data and the phase brief requires a 0-template folder to render.
5. **Draft pill:** spec neutral (`--muted` fill, 4px radius) rather than the board's white
   fill with a 1px `#e4e4e7` border. The spec's status table wins; 46×22 against 45×21.
6. **No `Signature` action in the editor toolbar.** The board shows `+ Placeholder` and
   `Signature`; the template show payload has no signatures, the template is signed by the
   sending connection, and adding the prop would change the payload contract. `+ Placeholder`
   is kept and inserts at the cursor.
7. **No "last saved 2 min ago".** The editor payload carries no `updated_at` (only the list
   does), so the subtitle shows the folder name and appends `· Saved just now` after a save
   in the session. Adding `updated_at` would be a payload-contract change for a subtitle.
8. **Footer note is UTC, not "each connection's timezone".** There is no per-connection
   timezone column (migrations are out of scope) and `SendEmail` resets at 00:00 UTC, so the
   board's copy would be false. The note reads "Daily sending caps reset at 00:00 UTC."
9. **One banner per failed connection**, with the message
   `{name} failed verification: {exception_data.message}`. The board shows one; the payload
   gained `last_error_message` because `last_error` is only the exception class.
10. **`Connect Gmail or Outlook` is a menu** when both OAuth providers are configured, a
    plain button when one is, absent when neither is; both provider routes are unchanged.
    The board's multicolour provider glyph is replaced by the `Mail` icon (no brand SVGs in
    the repo).
11. **Signature cell is a select styled as text.** The board shows static text; keeping the
    control keeps assignment reachable. Labels read `Sam — default`, `Team footer`,
    `Team default` — `Team default` rather than the board's `None` because an unassigned
    connection falls back to the team default signature at send time.
12. **Server cell truncates long values** (`smtp.mailgun.org…`) with a `title`; the column
    keeps the board's x positions (text x 527) and truncation follows the spec's
    "truncate, don't clip" rule. It shows `host:port`, or the provider label when an OAuth
    connection stores no host.
13. **Meter turns red at ≥90% of the cap.** The board has one red example (388/400) and no
    legend; 90% is the inferred threshold. Violet otherwise, `--destructive` above it.
    A connection with no cap renders `—` instead of a bar.
14. **Rows are ordered by name**, so the fixture renders Mailgun relay / Outbound team /
    Sam Patel · Gmail against the board's Sam / Outbound / Mailgun order. This is the
    controller's pre-existing `orderBy('name')`.
15. **Type pills are the neutral badge** (spec status table) rather than the board's
    white-with-border chip; the same applies to the editor's `Draft` label.
16. **Search is client-side** over name and subject. The old folder-filter sidebar is gone
    because the folder groups replace it; the payload already carries every template.
17. **The dirty guard covers the page's own paths** (back chevron, Discard, tab close via
    `beforeunload`). Sidebar navigation still discards without asking — the same gap phase 3
    recorded for the step editor.
18. **The payload additions are additive**: `sent_today`, `display_status`,
    `display_status_label`, `needs_attention`, `last_error_message`. No key changed type or
    disappeared; the connections index now runs three statements (connections, aggregate,
    signatures).

## Gaps and open questions

1. **No `(mailer_connection_id, dispatched_at)` index** on `campaign_emails`, so the new
   aggregate scans. 8.81 ms at 50k rows, 1.53 ms with the index (numbers above). A migration
   is the fix and was out of scope.
2. **`sent_count` and the aggregate can disagree**: the counter resets lazily on the next
   send from that connection, so a connection that has not sent since midnight yesterday
   still carries yesterday's count. The meter reads the aggregate; the counter is untouched.
3. **Daily caps are UTC**, while recipients have their own timezones; the board's per-connection
   claim cannot be honoured without a new column.
4. **No "last saved" timestamp** in the editor payload (see deviation 7).
5. **R2-1 was code-reviewed, not click-tested.** Triggering it needs a DELETE that fails
   after a success inside a filtered view; the changed path is the failure branch only.
6. **R2-2's last window** (start between the partial reload and the POST) stays open until
   the recipients endpoint accepts an idle-only flag.
7. **The Space keeps `campaigns-phase5`** with `vendor/`, `node_modules/`, the fixture
   database and `storage/render/`. The preview route, the seeder and the render scripts were
   removed from the tree after the final render and before the final checks; none of them
   were ever in the host worktree or the commits.
