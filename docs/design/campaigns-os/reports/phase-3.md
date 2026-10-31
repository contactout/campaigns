# Phase 3 report — campaign detail, recipients, step editor, blocked start

Branch `design/campaigns-os`: `2a78fa4` (feature), `845d69c` + `85f4d53` (report),
`88a4ca4` (fix round) on top of `15baf71`. Worktree
`/home/azri/projects/contactout/campaigns-design`. Paper MCP was not called; every number
below comes from a pixel scan of the boards in
`/home/azri/projects/contactout/.design-refs/campaigns-os/` or from a 1280×900 headless
Chromium render in the cua Space `local:devtest`.

## What changed

Backend:

- `app/Http/Controllers/Campaigns/CampaignController.php` — `show()` eager-loads the campaign's
  `mailerConnection`, accepts a `recipients_q` search on the recipients paginator, derives the
  recipients' board status, adds per-step email metrics, a do-not-contact recipient count and a
  `start` payload of derived blocking reasons. New protected helpers: `connectionSummary()`,
  `stepMetrics()`, `recipientEngagement()`, `doNotContactRecipients()`, `recipientRow()`,
  `startState()`, `startReason()`.

Frontend:

- `resources/js/pages/campaigns/show.tsx` — rebuilt: `PageHead` with the back chevron, status pill
  and action cluster; `Steps | Recipients` tab bar (roving `tabIndex`, `aria-selected`,
  Arrow/Home/End); the steps tab or the two-pane editor; the recipients tab; the existing
  edit/delete/add-recipients modals. The blocked UI is gated on `canStart && start.blocked`.
- `resources/js/components/campaigns/campaign-steps-tab.tsx` (new) — stat cards, "Sends from" meta
  line, day groups, step rows with the three fixed metric lanes, native HTML5 drag reorder and a
  `⋮` menu (Edit step / Move up / Move down / Delete step).
- `resources/js/components/campaigns/campaign-recipients-tab.tsx` (new) — bulk bar, server-side
  search, table with select-all, selected-row tint, row menu and the "Showing x–y of N" footer.
  Selection resets when the page or the search changes.
- `resources/js/components/campaigns/sequence-editor.tsx` (new) — the two-pane step editor, the
  `Esc` handler (guarded against open Radix layers), the destructive `Delete step` that closes the
  pane on success.
- `resources/js/components/campaigns/step-form.tsx` (new) — the form logic and fields extracted
  from the modal (state, template/signature/placeholder insertion, day/time, threading).
- `resources/js/components/campaigns/step-form-modal.tsx` — now a thin `Dialog` wrapper over
  `StepForm`; still usable by any screen that wants a dialog.
- `resources/js/components/campaigns/start-blocked-panel.tsx` (new) — board 11 A panel with inline
  `Edit step N` and `Go to Connections`.
- `resources/js/components/campaigns/recipient-status-badge.tsx` (new) — maps the derived status
  onto the spec's badge table.
- `resources/js/components/templates/rich-text-editor.tsx` — adds an opt-in `toolbar="board"`
  (`B I U • 1. Link` + an `actions` slot), an inline link input and a `minHeightClass` prop. The
  icon toolbar stays the default and its markup is unchanged, so the template screens take the
  same code path; the template editor was not re-rendered in this phase.
- `resources/js/components/page-head.tsx` — optional `back` (chevron) and `titleAddon` (status
  pill). Additive; every existing caller is unchanged.
- `resources/js/lib/timezone.ts` (new) — `(UTC+08:00) Kuala Lumpur` formatting via `Intl`.
- `resources/js/types/campaigns.ts` — `CampaignStepMetrics`, `CampaignConnection`,
  `CampaignStartReason`, `CampaignStartState`, `RecipientDisplayStatus`, and the new step/recipient
  fields.

Tests — nine new tests in `tests/Feature/Campaigns/CampaignControllerTest.php`: per-step metrics,
the six derived recipient statuses, the empty-subject + failed-connection + do-not-contact blockers
(including a do-not-contact contact on another campaign that must not count), the unblocked case,
no-steps/no-connection, an empty recipient list not blocking, a campaign that cannot be started
reporting nothing, `recipients_q`, and the connection payload with its credentials missing.

No migration, no new table, no route or middleware change, no `.dark` change, no
`resources/js/components/ui/*` change.

## Data added — every query and what it costs

All plans are SQLite `EXPLAIN QUERY PLAN` from the Space (the suite and the render fixture ran on
SQLite). MySQL's optimizer may pick differently.

1. **Campaign connection** — `mailerConnection` is now eager-loaded:
   `select * from mailer_connections where id = ? limit 1` →
   `SEARCH mailer_connections USING INTEGER PRIMARY KEY (rowid=?)`. One row by primary key. Not
   issued when the campaign has no connection.

2. **Per-step metrics** (new, one query per page):

   ```sql
   select campaign_step_id,
          count(case when dispatched_at is not null then 1 end) as sent_count,
          count(case when opened_at is not null then 1 end) as opened_count,
          count(case when replied_at is not null then 1 end) as replied_count
   from campaign_emails
   where campaign_id = ?
   group by campaign_step_id
   ```

   Plan: `SEARCH campaign_emails USING INDEX campaign_emails_campaign_id_index (campaign_id=?)`
   then `USE TEMP B-TREE FOR GROUP BY`. The index narrows to the campaign's emails, but there is no
   `(campaign_id, campaign_step_id)` index, so the grouping is a temp B-tree over every email the
   campaign ever produced (47 rows in the fixture). Percentages are computed in PHP from these
   counts, so no second query.

3. **Recipient engagement for the page** (new, one query per page):

   ```sql
   select recipient_id,
          count(case when dispatched_at is not null then 1 end) as sent_count,
          count(case when opened_at is not null then 1 end) as opened_count,
          count(case when replied_at is not null then 1 end) as replied_count,
          count(case when status = 'Bounced' then 1 end) as bounced_count
   from campaign_emails
   where campaign_id = ? and recipient_id in (… up to 25 ids …)
   group by recipient_id
   ```

   Plan: `SEARCH campaign_emails USING INDEX campaign_emails_campaign_id_index (campaign_id=?)`,
   `LIST SUBQUERY 1` over `recipients_campaign_id_contact_id_unique (campaign_id=?)` (covering),
   then `USE TEMP B-TREE FOR GROUP BY`. Skipped entirely when the page has no recipients. This is
   what lets the table use the board's vocabulary instead of `RecipientStatus`.

4. **Do-not-contact recipients** (new, one query per page):

   ```sql
   select count(*) from recipients
   inner join contacts on contacts.id = recipients.contact_id
   where recipients.campaign_id = ? and contacts.status = 'DoNotContact'
   ```

   Plan: `SEARCH recipients USING COVERING INDEX recipients_campaign_id_contact_id_unique
   (campaign_id=?)` + `SEARCH contacts USING INTEGER PRIMARY KEY (rowid=?)`. Cost scales with the
   campaign's recipient count; the contact lookup is a primary-key hit.

5. **Recipients search** (new, only when `recipients_q` is set):

   ```sql
   select * from recipients
   where campaign_id = ?
     and exists (select * from contacts
                 where recipients.contact_id = contacts.id
                   and (name like '%q%'
                        or exists (select * from contact_identities
                                   where contacts.id = contact_identities.contact_id
                                     and normalized_value like '%q%')))
   order by created_at desc limit 25 offset 0
   ```

   Plan: `SEARCH recipients USING INDEX recipients_campaign_id_contact_id_unique (campaign_id=?)`,
   `SEARCH contacts USING INTEGER PRIMARY KEY`, `SEARCH contact_identities USING INDEX
   contact_identities_contact_id_index (contact_id=?)`, `USE TEMP B-TREE FOR ORDER BY`. The
   `LIKE '%q%'` cannot use an index, so it is a per-recipient filter; the campaign index bounds the
   scan. The existing `latest()` sort already used a temp B-tree on this query.

6. **Recipients count** — pre-existing `select count(*) from recipients where campaign_id = ?`,
   which also feeds the "no recipients" number in `stats`. Plan: covering
   `recipients_campaign_id_contact_id_unique (campaign_id=?)`.

Measured with `DB::enableQueryLog()`: **39 queries** for a campaign with a connection and one page
of three recipients, **34** for a campaign with neither a connection nor recipients. The four
queries above are the only additions to `show()`; the rest is the pre-existing payload plus the
per-page recipient hydration.

## Board vs render measurements

Board coordinates come from ImageMagick pixel scans of the JPGs; render coordinates are glyph scans
of the PNG screenshots at 1280×900 with the 216px sidebar (content 240–1256) or DOM rects where a
box is the honest measure. "Glyph" means the dark-pixel band, not the line box.

### 03 A — Steps

| Element | Board | Render |
| --- | --- | --- |
| Tab bar: active underline | y 98–100 | y 98–100 (tablist 64–100, 36px) |
| Tab bar: label glyphs | 75–89 | 78–92 (3px lower in the same bar) |
| Stat cards | x 240–484, 497–741; y 117–201 | x 240–485, 497–742, 754–999, 1011–1256; y 116–200 |
| Stat-card height / gap | 84px / 12px | 84px / 12px |
| Stat value | 24px (spec amendment) | 24px, line height 28px (computed) |
| Step row 1 | y 298–358 (60px) | y 302–362 (60px) |
| Step row pitch (group to group) | 106px (298→404) | 106px (302→408) |
| Day-group header glyphs (Day 3) | 381–393 | 384–395 |
| `Sent` / `Opened` / `Replied` lane x | 940 / 1036 / 1132 | 939 / 1035 / 1131 |
| `Add step` button | x 1157–1255 (98px), y 219–251 | x 1154–1256 (102px), y 220–252 |

The Steps block sits 4px lower than the board (302 vs 298): the meta-line button is 32px tall and
the board's gap above it is 18px against the render's 20px. Row pitch and lane x are exact.

### 04 A — Recipients

| Element | Board | Render |
| --- | --- | --- |
| Table card | x 240–1256 | x 241–1255 (1px border inside the card) |
| Header height | 34px (157–191) | 34px (161–195) |
| Row pitch | 44px (dividers 191.5/235.5/279.5/323.5) | 44px |
| Footer | 52px (499.5–552) | 52px |
| Column glyph x: Name / Email / Status | 290 / 510 / 1060 | 292 / 512 / 1061 |
| Row `⋮` glyph x | 1206–1208 | 1224–1225 (18px further right) |
| Bulk bar: search field | y 113–144, x 897–1117 | y 116–148, x 871–1091 |
| Bulk bar: `Add recipients` fill | x 1123–1256 | x 1099–1256 |
| Selected row tint | lavender | `rgb(240, 238, 255)` = `--accent` |

The `Add recipients` button on this tab is the soft `secondary` fill on the board too
(`#F1EEFF`), which is what the render uses. It is 24px wider than the board's, which pushes the
search field 26px left; both right edges align. The `⋮` sits 18px further right because the render
right-aligns it inside a 64px cell with 16px padding; the board places it ~48px from the card edge.

### 05 A — Step editor

| Element | Board | Render |
| --- | --- | --- |
| Sequence list | x 240–540 (300px) | x 240–540 (300px) |
| Gap between panes | 24px | 24px |
| Editor card | x 564–1263 (700px), y 117–878 | x 564–1256 (692px), y 116–883 |
| Active sequence item | y 203–256 (54px) | y 202–254 (52px) |
| Editor body | y 416–637 (221px) | y 416–636 (220px) |
| Editor footer | y 822–878 (56px) | y 825–882 (57px) |
| Toolbar | `B I U • 1. Link` + `Placeholder` + `Signature` | same eight controls |

Board 05 A's card ends at x 1263 while boards 03 A and 04 A end at 1256 — an 8px inconsistency in
the boards themselves. The implementation follows the shared 24px page padding, so the editor pane
is 692px against the board's 700px.

### 11 A — Blocked start

| Element | Board | Render |
| --- | --- | --- |
| Panel | x 240–1256, y 117–298 (181px) | x 240–1256, y 116–306 (190px) |
| Reasons | 3 bullets | 3 bullets, same copy, count from data |
| Inline actions | `Edit step 2`, `Go to Connections` | same |
| `Start` | disabled | disabled, `aria-describedby="start-blocked"` |
| Offending step | red outline on step 2 | `rgb(220, 38, 38)` on row 2 only |

An Active campaign wired to the same failed connection was rendered as well: no panel, no Start
button, and no outlined row (`panel: false, startButton: false, outlined: ["rgb(228, 228, 231)"]`).

### Dark mode

One dark screenshot of the detail page was taken with `localStorage.appearance = 'dark'`: body
`oklch(0.145 0 0)`, cards on the same surface with `--border` dividers, the primary button
`oklch(0.985 0 0)` with near-black text, the `Active` pill on `--badge-success` / `#86efac`, the tab
underline `--primary`, and the step icon tiles legible on `--accent`. Nothing in this phase adds a
light-only token.

## Vocabulary decision — `RecipientStatus` vs the board's labels

`RecipientStatus` cannot express the board's `Contacted / Opened / Not contacted` split: it is
`Active, Pending, Bounced, Completed, Error, Paused, Replied, Unsubscribed, Archived`, and
`AddCampaignRecipients` writes `Active` for every new recipient. The board's labels are
**engagement**, and engagement lives on `campaign_emails` (`dispatched_at`, `opened_at`,
`replied_at`, `status`). The terminal states are already campaign-scoped on the recipient row:
`ProcessInboundMessage` writes `Replied`/`Bounced`, `UnsubscribeController` writes `Unsubscribed`.

So the label is derived per recipient from one grouped email query plus the recipient's own status,
first match wins:

| Order | `display_status` | Label | Source |
| --- | --- | --- | --- |
| 1 | `replied` | `Replied` | an email with `replied_at`, or `RecipientStatus::Replied` |
| 2 | `bounced` | `Bounced` | an email with `status = Bounced`, or `RecipientStatus::Bounced` |
| 3 | `unsubscribed` | `Unsubscribed` | `RecipientStatus::Unsubscribed` |
| 4 | `opened` | `Opened` | an email with `opened_at` |
| 5 | `contacted` | `Contacted` | an email with `dispatched_at` |
| 6 | `error` / `paused` / `archived` / `completed` | their own label | `RecipientStatus` |
| 7 | `not_contacted` | `Not contacted` | no dispatched email |

`status` and `status_label` are **kept unchanged** in the payload (`RecipientStatus`), so
`CampaignRecipientControllerTest`'s `status_label === 'Active'` assertion still holds and nothing
else that reads them breaks. `display_status` / `display_status_label` are additive. Badge variants
follow the spec table: Replied → success, Bounced → danger, Opened → warning, Contacted → info,
Unsubscribed / Not contacted / Completed / Paused / Archived → neutral, Error → danger.

On the board the sequence reads `Contacted`, `Replied`, `Opened`, `Contacted`, `Bounced`,
`Not contacted`, `Unsubscribed`; the render reproduces exactly that.

## Start blockers

`start.reasons` is computed in `show()` and only for a campaign that can actually be started:
`startState()` returns `blocked: false, reasons: []` unless the status is Draft or Stopped, and the
page additionally requires `can.start`. Nothing is hardcoded:

| Type | Blocking | Condition | Copy |
| --- | --- | --- | --- |
| `steps` | yes | no steps | `This campaign has no steps yet.` |
| `step` | yes | `trim(subject) === ''` | `Step 2 (Day 3) has an empty subject` + `step_id`/`step_sequence` |
| `connection` | yes | `mailer_connection_id` is null | `This campaign has no sending connection.` |
| `connection` | yes | `exception_type !== null` | `The sending connection "Mailgun relay" failed verification` |
| `connection` | yes | `status !== Active` | `The sending connection "X" is pending` (status label lowercased) |
| `do_not_contact` | no | DNC recipient count > 0 | `4 recipients are marked Do not contact and will be skipped` |

Why these are real:

- `StartCampaign::handle` enforces `canBeStarted()` (Draft/Stopped **and** at least one step), so
  no-steps is enforced today and the UI now says so instead of letting the request bounce.
- `SendEmail` marks an email `Failed` when the campaign has no connection, and defers every email
  while the connection's status is not `Active` — nothing sends.
- `SendEmail::isSuppressed` → `SuppressionList` treats `DoNotContact` contacts as suppressed, so
  the "will be skipped" line is true. They are marked `Failed` rather than silently skipped; the
  copy follows the board.
- The empty-subject check is not enforced by the API (`subject` is `required` on create/update), so
  it only appears for legacy or duplicated rows — still derived, never rendered blind.

An empty recipient list is **not** a blocker: `StartCampaign` allows it and
`CampaignStepScheduler::baselineFor` explicitly supports recipients who join a running campaign, so
refusing to start would change behaviour the prompt says to keep. The do-not-contact line is a
warning (`blocking: false`) for the same reason — those recipients are suppressed at send time, not
a reason to refuse the send.

## Deviations

1. **Stat cards stay on the blocked state.** 11 A omits the four cards and the meta line; keeping
   them makes the two states one page and the cards read `Sent 0 / Opened —` truthfully.
2. **`Add recipients` stays in the head on the blocked state**, as an outline button, because
   `Start` takes the primary slot (one primary per screen). 11 A omits it entirely.
3. **`Delete campaign` moved into a `⋮` overflow** in the action cluster. The boards have no Delete,
   but `can.delete` must stay reachable; it is still gated and still opens the existing modal.
4. **Every recipient status is a pill.** The board shows `Contacted` and `Not contacted` as bare
   text; the spec calls that a defect and asks for a pill per status.
5. **A select-all checkbox in the recipients header.** The board's header is empty in that column;
   the phase prompt requires select-all. It selects the current page only.
6. **The board toolbar is opt-in** (`toolbar="board"`). `RichTextEditor` is shared with the template
   editor, which is phase 5 scope, so the default stays the icon toolbar.
7. **No undo/redo buttons on the board toolbar** (05 A shows `B I U • 1. Link` only). The keyboard
   shortcuts still work; the buttons remain in the icon toolbar used by templates.
8. **The `Time` field is the existing native `<input type="time">`**, so it renders `09:00 AM`
   instead of the board's `09:00 · Kuala Lumpur`. The campaign timezone is on the meta line.
9. **`Apply template` cannot show an applied template name.** Steps store no template reference
   (`campaign_steps.setting` is unrelated), so the select is a placeholder-only action; 05 A's
   `Follow-up · soft bump` value has no data source.
10. **Placeholder chips carry no fallbacks.** Fallbacks live on `placeholders` rows owned by an email
    template (`owner_type`/`owner_id`); a campaign step owns none. Chips render `{{name}}` as on
    05 A; the 390 board's fallback values are a gap for phase 6.
11. **Page padding is 24px**, so the editor card is 692px wide against 05 A's 700px (see the
    measurement table).
12. **Start gating is client-side.** `StartCampaign` still only checks status + one step; the server
    would accept a Start with an empty subject or a non-Active connection if the form were posted
    directly. Changing the action was out of scope.
13. **An `Active` connection with `exception_type` set blocks.** This mirrors phase 2's
    needs-attention rule; `SendEmail` itself only checks the status, so the panel is stricter than
    the job.
14. **The do-not-contact line only renders inside the blocked panel**, exactly as on 11 A. A
    campaign whose only issue is do-not-contact recipients shows no panel and starts normally.
15. **`display_status` is additive** next to `status`/`status_label` (see the vocabulary section).
16. **Tab state is local** (`useState`). Search, pagination and bulk remove pass `preserveState`, so
    the tab survives those visits; a full page reload returns to Steps. The recipient selection is
    reset whenever the page or the search changes, so the bulk Remove can never act on rows that are
    no longer on screen.
17. **Native HTML5 drag** reorders step rows (no DnD library); the keyboard path is
    `Move up`/`Move down` in the row's `⋮` menu, which replaces the old arrow buttons.
18. **The recipient search matches `%q%` literally**, so a typed `%` or `_` acts as a wildcard.
19. **`Esc` in the editor ignores the key while a Radix menu, listbox or dialog is open**, so
    dismissing the Placeholder menu or the template select does not close the editor and lose the
    draft; `Esc` on its own still closes it.

## Verification

Space provenance: the tree was staged as a `tar` of the working tree into
`/home/cua/work/campaigns-phase3` with `vendor/`, `node_modules/` and `.env` copied from the
phase-2 tree, so nothing was installed, built or served on the host. After the fix round, the 60
files under `app/Http/Controllers/Campaigns`,
`resources/js/{components/campaigns,components/templates,pages/campaigns,lib,types}` and
`tests/Feature/Campaigns` were md5-compared between the Space tree and commit `88a4ca4`: identical.

```
$ npm run build            BUILD_EXIT=0    ✓ built in 2.35s
$ npm run check            CHECK_EXIT=0    All 153 files are correctly formatted
                                            Found no warnings or lint errors in 136 files
$ npm run types:check      TYPES_EXIT=0    tsc --noEmit
$ php artisan test         TEST_EXIT=0     Tests: 569 passed (2444 assertions)
$ php vendor/bin/pint --test …             PINT_EXIT=0     PASS 2 files
$ php vendor/bin/phpstan analyse …         PHPSTAN_EXIT=0  [OK] No errors
```

`php artisan test` is the full suite on SQLite `:memory:` from `phpunit.xml`, not a subset. The first
run of the suite failed only because `public/build/manifest.json` was missing (the tar excludes
`public/build`); it passed after the build above.

Render fixture (temporary, not committed): Northwind Growth team, an Active connection `Primary`
(`from_email` `sam@northwind.io`), an Active connection `Mailgun relay` with `exception_type`,
campaign `Q4 Fintech CFO outreach` (4 steps on days 1/3/7/12, 37 recipients, 47 emails),
campaign `Series A founders — APAC` (Draft, 3 steps of which step 2 has an empty subject, 8
recipients of which 4 are do-not-contact) and `Active with a broken relay` (Active, same failed
connection, empty subject). Screenshots: steps, recipients, step editor, blocked start, dark-mode
steps.

Interactions driven through CDP on the final build:

- `ArrowRight` on the tab bar selects `Recipients`, moves focus to it and the roving `tabIndex`
  becomes `[-1, 0]`; `Home` returns to `Steps`.
- `Esc` with the Placeholder menu open closes only the menu (`menu: false, editor: true`); `Esc`
  alone closes the editor.
- Selecting a recipient shows `1 selected`; clicking `Next` lands on `Showing 26–37 of 37` with no
  selected rows and the Recipients tab still active.
- Selecting two recipients and pressing `Remove` posts to `campaigns.recipients.bulk-destroy`: the
  footer moves from `Showing 1–25 of 37` to `Showing 1–25 of 35`, the selection clears and the
  `Recipients removed.` toast appears. (37 is the seeded total; the pre-remove footer text was not
  captured, only the post-remove one.)
- The header select-all shows `25 selected` — the current page only.
- `Delete step` in the editor closes the pane, drops the row count from 4 to 3 and flashes
  `Step deleted.`.
- The recipients search submits `?recipients_q=Priya`, returns one row and keeps the Recipients tab.

Not exercised in a browser: the `Link` toolbar flow (the inline URL row appears; the mark is not
asserted), the drag-and-drop reorder (only `Move up`/`Move down` share its POST path), and the
signature dropdown.

## Gaps and open questions

1. **No `(campaign_id, campaign_step_id)` or `(campaign_id, dispatched_at)` index** on
   `campaign_emails`, so both aggregates group through a temp B-tree over the campaign's emails. A
   migration is the fix; migrations were out of scope.
2. **`recipients_q` does not escape `%`/`_`** in the search term.
3. **Placeholder fallbacks and the step→template link do not exist in the schema**, so 05 A's
   applied-template value and the 390 board's fallbacks cannot be rendered without a migration.
4. **Server-side Start validation is unchanged** — the UI is stricter than `StartCampaign`.
5. **`Pending` recipients with no email read `Not contacted`**; the board has no Pending state, and
   the mapping is recorded in the table above rather than invented.
6. **Drag-and-drop is mouse-only**; the menu items are the accessible path.
7. **The blocked panel is 9px taller than the board** (190 vs 181); not chased further because the
   board's own type scale is a step off the shared 13px body.
8. **The recipients `⋮` is 18px right of the board's** and the `Add recipients` button 24px wider,
   which shifts the search field 26px left; the right edges align. Shifting the button without
   changing the table's column distribution needs a layout-neutral transform, which was not worth
   the extra mechanism for 18px.
9. **`Team · Owner` in the sidebar** is the phase 1 deviation (no member count in `UserTeam`).
10. **The Space keeps `/home/cua/work/campaigns-phase3`** with `vendor/`, `node_modules/` for
    phase 4. The temporary preview route, seeder, render scripts, SQLite database and screenshots
    were removed from the tree after the final checks; none of them were ever in the host worktree
    or the commits.
