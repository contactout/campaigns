# Phase 4 report — contacts grid

Branch `design/campaigns-os`: feature commit `57a15f1`, query fix `045f845`, review fixes
`9855961`, on top of `2c94273`. Worktree
`/home/azri/projects/contactout/campaigns-design`. Paper MCP was not called. Every board
number below is a pixel scan of `06-contacts-grid-a-1280.jpg`; every implementation number
is a DOM rect from a 1280×900 headless Chromium render in the cua Space `local:devtest`.

## What changed

Frontend:

- `resources/js/lib/utils.ts` — `cn()` now extends tailwind-merge's `theme.text` with
  `h1, h3, body, label, stat, brand, caption`. Without that, tailwind-merge treated
  `text-body`/`text-label` as colours and dropped them whenever a colour followed, which
  made this page's primary/secondary buttons 16px and every pill 13px. This is the P1-1
  fix; it also corrects the signed-off campaign screens (see the review round).
- `resources/js/pages/contacts/index.tsx` — rebuilt to board 06 A: `PageHead` with
  `Import CSV` (secondary) and `Add contact` (primary), the breadcrumb bar opted out
  (`showHeader: false`), the full-height `h-svh` workspace, the toolbar (Filters,
  selection count, bulk actions, search, columns menu), the grid card with the list-tab
  strip pinned at its bottom, and the empty/out-of-range states on `EmptyState`. The bulk
  runner counts successes and handles validation, HTTP, network, cancel and finish
  outcomes; it shows one summary toast and restores the page after a delete.
- `resources/js/components/contacts/contact-grid.tsx` (new) — the shared-`Table` grid:
  select-all with an indeterminate state, row checkboxes, the four core cells, the
  Company/Title/extra columns resolved from the team's contact fields, 12px muted headers,
  truncating cells with `title` tooltips, the keyboard cell navigation, `font-medium`
  names and the contact link. The link appears on hover/focus on a mouse device and is
  always visible when `any-pointer: coarse` matches.
- `resources/js/components/contacts/add-to-campaign-modal.tsx` (new) — campaign picker
  with **no preselection**; the submit stays disabled until a campaign is chosen. It only
  receives Draft/Stopped campaigns and shows each campaign's status.
- `resources/js/components/contacts/list-tabs.tsx` — board strip: 35px tall, 30px pills,
  active tab on `--accent` with a 2px `--primary` underline, counts, `+ Add list`, list
  rename/delete, and the pagination slot at the right. It keeps `q` when a list is chosen
  and can block list navigation while a bulk action runs.
- `resources/js/components/contacts/inline-cell.tsx`,
  `inline-property-cell.tsx`, `inline-status-cell.tsx` — 32px editors instead of 44px, so
  a row is 33px; value tooltips; 2px `--ring` cell focus; the status trigger is `size="sm"`
  and hides its chevron (the board has no dropdown glyph), which is what let `Do not
  contact`/`Unsubscribed` fit the 140px column.
- `resources/js/components/contacts/contact-status-badge.tsx` — the board's copy map
  (`NotContacted` → `New`, `DoNotContact` → `Do not contact`). The enum values are
  untouched; the contact detail page and the create/edit modals use the same map.
- `resources/js/components/contacts/create-contact-modal.tsx`,
  `edit-contact-modal.tsx` — status options use `contactStatusLabel`, so they read `New` /
  `Do not contact` instead of `NotContacted` / `DoNotContact`.
- `resources/js/components/ui/checkbox.tsx` — restyled in place for the indeterminate
  state (a `Minus` instead of a check when `data-state="indeterminate"`). Public API
  unchanged.
- `resources/js/hooks/use-flash-toast.ts` — exposes `setFlashToastSuppressed()` so a bulk
  action shows one summary toast instead of one per request, and dedupes flashes by
  message id.
- `resources/js/types/contacts.ts` — `ContactCampaignOption` gains `status_label`.
- `resources/js/pages/campaigns/show.tsx` — carry-over R2-1 (below).

Backend:

- `app/Http/Controllers/Contacts/ContactController.php` — adds the read-only `campaigns`
  prop through `campaignOptions()` for `Add to campaign`: the team's **Draft and Stopped**
  campaigns only, with `status_label`. No query was added for a column.
- `tests/Feature/Contacts/ContactControllerTest.php` — the campaigns test now creates a
  Draft, an Active and an Archived campaign (plus another team's) and asserts only the
  Draft is listed, with `status_label = Draft`.

Docs: `docs/design/campaigns-os/spec.md` gains the accepted phase-4 amendment for 33px
rows / `px-3` cells.

No migration, no route, no policy change, no `.dark` change, no change to a
`resources/js/components/ui/*` API.

## Column-source decision

| Column | Source | Notes |
| --- | --- | --- |
| Name, Email, Phone | `contactSummary()` — `name`, `emailIdentity`, `phoneIdentity` | Already in the payload. Phone is the stored normalized value, so it renders `6581234471`, not the board's `+65 8123 4471`. |
| Status | `contact.status` + `status_label`, `ContactStatus` variants | Pill labels use the board copy above. |
| Company, Title | A team `ContactField` matched by name (values in `contact.properties`) | `contacts` has no company/title column and neither is one `with`/join away, so the custom-field rows are the only source. |
| Any other team field | The same `fields`/`properties` payload | Rendered after Title, so custom-field inline editing is preserved. The grid scrolls horizontally inside its container when they overflow. |

Matched names are normalized (lowercase, non-alphanumerics collapsed):
**company** ← `company`, `companies`, `organization`, `organisation`, `employer`;
**title** ← `title`, `job title`, `role`, `position`. A team without a matching field gets
no Company/Title column — four columns and no blank cells — rather than a column with no
source. This is recorded as a gap below.

The dropped columns are the old spreadsheet row-number `#` column and the `Lists` count;
neither is on board 06 A. Losing the row number changes no action. The `Open` contact page
stays reachable through a 28px arrow in the last data cell: hover/focus on a pointer
device, always visible when `any-pointer: coarse` matches (see P2-5).

## Data added — the query and its plan

The only new query is the campaigns prop:

```sql
select id, name, status from campaigns
where team_id = ? and status in ('Draft', 'Stopped')
order by name asc
```

SQLite `EXPLAIN QUERY PLAN` on the render fixture:
`SEARCH campaigns USING INDEX campaigns_team_id_index (team_id=?)` then
`USE TEMP B-TREE FOR ORDER BY`. That is one query and no per-campaign work. The first cut
filtered the collection through `$user->can('update', $campaign)`, which lazy-loaded
`team` per campaign; `045f845` removed the filter because the policy is just team
membership, already enforced by the route middleware, and the action still posts to
`campaigns.recipients.store`, which owns the update gate. The contacts index query itself
is unchanged: `SEARCH contacts USING INDEX contacts_team_id_status_index (team_id=?)` plus
a temp B-tree for the existing `latest()` sort.

## Bulk actions

No contact bulk endpoints exist. Each action reuses the existing per-contact endpoint and
its policy, one request per selected row (at most the 25 rows on the page):

- **Add to campaign** — `POST campaigns/{campaign}/recipients` with
  `contacts: [ids]`; the endpoint skips duplicates and suppressed contacts and gates on
  `CampaignPolicy::update`. The picker lists only the team's Draft/Stopped campaigns and
  has no default selection, so a single click cannot enrol contacts into a running
  sequence.
- **Do not contact** — `PATCH contacts/{contact}/cell` with
  `{field: "status", value: "DoNotContact"}`. Contacts already at that status are skipped.
- **Delete** — a `ConfirmDialog` first (with the count captured when it opens), then
  `DELETE contacts/{contact}` per row.

The loop counts successes (`done`) and handles every outcome through `onSuccess`,
`onError`, `onHttpException`, `onNetworkError`, `onCancel` and the `onFinish` fallback. A
failure stops the loop, resets `busy`, keeps the unprocessed ids selected, shows an inline
`role="alert"` and one error toast (`Marked 3 of 5 contacts… The rest were not changed.`),
and reloads `contacts`/`lists` so the partial change is visible. Intermediate requests use
`only: []`; the last one merges the full props. While the loop runs, the search, Filters,
gear, list tabs, pagination and the grid are disabled, and the visits use `async: true`, so
a stray navigation cannot cancel them. Flash toasts are suppressed during the loop and one
summary toast is shown at the end.

Delete restores the page after the redirect to the bare index: it computes
`min(current_page, ceil((total - deleted) / per_page))`, so page 3 stays page 3 and deleting
the last rows of the last page steps back to the new last page. Selection is pruned to ids
on the current page and reset when the page, search or list changes. The header checkbox is
`indeterminate` when some rows are selected.

## Carry-over from the phase-3 review (R2-1)

`resources/js/pages/campaigns/show.tsx`: the one-line summary is now
`This campaign can't start until you resolve N blocking issue(s).` (a sentence with a
verb) and gets `sr-only` whenever `tab === 'steps' && editor === null` — the exact state
that renders the full blocked panel. It keeps `id="start-blocked"` so `aria-describedby`
never dangles.

Verified on the fixture's Draft campaign (no steps, no connection → 2 blocking reasons):

| State | Summary | Panel |
| --- | --- | --- |
| Steps tab, editor closed | `sr-only`, 26×18 hit box; text "This campaign can't start until you resolve 2 blocking issues." | present |
| Recipients tab | visible, 1016×38 | absent |
| Steps tab, editor open (`New step`) | visible, 1016×38 | absent |

`Start` stays focusable (`disabled: false`, `tabIndex: 0`, `aria-disabled="true"`,
`aria-describedby="start-blocked"`).

## Board vs implementation measurements

Board is 1280×820; the render is 1280×900, so the strip's absolute y differs by the
viewport height while its height and bottom anchoring do not. The implementation numbers
below are after the `cn()` fix and the re-shoot.

| Element | Board 06 A | Implementation |
| --- | --- | --- |
| H1 | glyph band y 22–36 | line box y 16–44, cap top ≈21 |
| Subtitle | glyph band y 51–62 | line box y 44–64, glyphs ≈47–58 |
| Toolbar controls | y 88–119, 32px tall | y 88–120, 32px tall |
| `Filters` | x 242–318, 76px | x 240–324, 84px |
| `2 selected` | x ≈330–390 | x 332–397 (13px, glyph 53) |
| `Add to campaign` | x 404–547, 143px | x 405–557, 152px (13px) |
| `Do not contact` | x 561–669, 108px | x 565–682, 117px (13px) |
| `Delete` | x 682–758, 76px; glyph 39 | x 690–776, 86px; glyph 40 (13px) |
| Search field | x 971–1215, 244px | x 970–1216, 246px |
| Gear button | x 1228–1253 | x 1224–1256, 32px |
| Grid frame | full-bleed rules x 215–1279, no card | card x 241–1255 (1px border) |
| Table header | 129–164, 35px | 129–164, 35px |
| Row 1 / dividers | 164–197, 33px pitch | 164–197, 33px pitch |
| Cell height | 32px + 1px rule | 33px border-box |
| Column starts | 279 / 469 / 689 / 849 / 989 / 1139 | 279 / 469 / 689 / 849 / 989 / 1139 |
| Column widths | 64 (checkbox) / 190 / 220 / 160 / 140 / 150 / 117 (clipped) | 38 (checkbox) / 190 / 220 / 160 / 140 / 150 / 116 |
| Status pill | ≈74×20, text glyph 58, x 861, y 171–190 | 78×22, glyph 60, x 861, y 169–191 (12px) |
| List-tab strip | y 783–818 at a 820 viewport, 35px | y 864–899 at a 900 viewport, 35px |
| `All contacts` tab | glyph 71 | box 97×30, glyph 73 (13px) |
| `Add contact` | x 1137–1256, 119px | x 1134–1256, 122px (13px, glyph 76) |
| `Import CSV` | x 1017–1130, 113px | x 1009–1126, 117px (13px, glyph 71) |
| Page width | 1280 | 1280, `documentHeight` 900 (no page scroll) |

**Accepted amendment — 33px rows / `px-3` cells.** The brief says "44px rows (the board's
divider pitch)". Board 06 A does not measure 44: its rules sit at y
164/197/230/263/296/328/361/394/427/460/492/525/558, a 32.8px mean pitch, and the spec's
density line says "grid cells 32px". 44px is the 02 A campaigns-table amendment. The
implementation follows board 06 and the spec (32px cells + the 1px rule = 33px). Cell
padding is 12px (`px-3`), not the brief's `px-4` (the 02 A measurement): Name text starts
at 291 against the 279 rule on both board and render. This is recorded as an Amendment in
`spec.md` so phase 6 does not restore 44px.

**Grid frame deviation.** The board's grid is full-bleed: its horizontal rules run from the
sidebar border at x 215 to the artboard edge at 1279, with no card, no left/right border
and no 24px page inset. The implementation keeps a bordered card at x 241–1255, inside the
24px page padding, because it bounds the scroll container and the Title truncation. The
Name divider still lands on 279 and every other column start is exact; only the checkbox
column is narrower (38px against the board's 64px) and the card's outer frame is inset.
This is a recorded deviation, not a board measurement.

**Internal scrolling.** The table container is 735px tall for an 860px table
(`overflow-y: auto`). With `scrollTop = 125`, the sticky header stays at y 129 while row 1
moves from y 164 to y 39. The list-tab strip is not inside the scroller.

**Truncation.** The first page contains Charlotte Whitmore-Sanders with
`Northstar Capital Partners International Holdings` and
`Senior Vice President, Corporate Development and Strategic Finance`. In a 116px Title
cell and a 150px Company cell both buttons clip (`clientWidth` 115/149,
`scrollWidth` 448/322) and carry the full value in `title`. The board's defect — the Title
column running past x 1256 — does not exist: the last header ends at 1255.

## Dark mode

`06-contacts-1280-dark.png` (localStorage `appearance = 'dark'`, taken before any mutation).
Body and header `oklch(0.145 0 0)`, selected rows `--accent` `oklch(0.269 0 0)`, the
`Contacted` info pill at 12px on the dark accent with its `--badge-info-border` `#8b7dff`,
and all six status pills legible. The toolbar `Delete` is an outline button with
`--destructive-foreground` (`oklch(0.637 0.237 25.331)`, **5.2:1** on the dark page) and
`hover:bg-badge-danger` / `hover:text-badge-danger-foreground` (8.47:1), so it inherits the
phase-3 P2-9 fix instead of the filled destructive variant.

## Verification

Space provenance: the working tree was tarred to `/home/cua/work/campaigns-phase4` over the
phase-3 tree's `vendor/` and `node_modules/`; nothing was installed, built, served or
tested on the host. Fixture (temporary, removed from the host and the Space):
Northwind Growth / `preview@northwind.io`, `Company` and `Title` contact fields, the three
board lists, 1,284 contacts (the board's eight rows first, all six statuses, a long
company/title row, email + phone on the rows on page 1) and five campaigns (two Draft, one
Stopped, one Active, one Archived).

Final checks on the tree that matches `9855961` (no preview route, no fixture seeder, no
render artifacts; the changed PHP/TS files were md5-compared with the host worktree and
matched):

```
$ npm run build            BUILD_EXIT=0    ✓ built in 2.62s
$ npm run types:check      TYPES_EXIT=0    tsc --noEmit
$ npm run check            CHECK_EXIT=0    All 156 files are correctly formatted
                                           Found no warnings or lint errors in 139 files
$ php artisan test         TEST_EXIT=0     Tests: 571 passed (2485 assertions)
$ php vendor/bin/pint --test               PINT_EXIT=0     PASS 365 files
$ php vendor/bin/phpstan analyse …         PHPSTAN_EXIT=0  [OK] No errors
```

`php artisan test` is the full suite on SQLite `:memory:` from `phpunit.xml`, run without
a `DB_DATABASE` export. Interactions driven through CDP on the final build:

- Two row checkboxes → header checkbox `data-state="indeterminate"`, toolbar count
  `2 selected`; select-all → `25 selected`, `data-state="checked"`; deselect → 0 and the
  count/bulk actions disappear.
- `ArrowRight` on the Name cell moves focus to `Email: …` (`data-grid-column="1"`). The
  status Select keeps its arrow keys.
- Inline name edit: typing and Enter persisted `Priya Natarajan-Rao` and re-rendered the
  cell.
- Filters → `APAC founders` gave `?list=2&q=` and one row; the list tab reports
  `aria-current="page"`.
- `Do not contact` on two New rows changed both pills and showed exactly one toast:
  `Marked 2 contacts as do not contact.` (the already-DNC contact in the selection was
  skipped).
- `Add to campaign` opened with `Choose a campaign` and a disabled submit; the options were
  `Paused outreach Stopped`, `Q4 Fintech CFO outreach Draft`, `Series A founders — APAC
  Draft` (no Live/Archived). Selecting Q4 enabled the submit and added 1 recipient
  (`1 recipient added.`).
- The create and edit modals both offered `New … Do not contact`.
- With `*/contacts/*/cell` blocked at the network layer, the bulk DNC stopped with
  `Marked 0 of 2 contacts as do not contact. The rest were not changed.`, one error toast,
  and the toolbar/search enabled again.
- Deleting two rows on `?page=3` restored `?list=&page=3&q=` with 25 rows and one
  `Deleted 2 contacts.` toast. Deleting the 7 rows on page 52 stepped back to
  `?list=&page=51&q=` with one `Deleted 7 contacts.` toast.
- The contact link is `opacity: 0` / `pointer-events: none` at rest on a pointer device,
  `opacity: 1` / `auto` on hover, and `opacity: 1` / `auto` with touch emulation
  (`any-pointer: coarse` = true).

Screenshots (committed next to this report):

- `phase-4/06-contacts-1280.png` — light, 1280×900, two selected rows, every status,
  post-`cn()` type scale.
- `phase-4/06-contacts-truncation-1280.png` — the same table with the long company/title
  row and the hovered link.
- `phase-4/06-contacts-1280-dark.png` — dark mode.
- `phase-4/02-campaigns-1280-post-cn.png` — the signed-off campaigns list after the shared
  `cn()` fix (13px controls, 12px pills).
- `phase-4/03-campaign-detail-1280-post-cn.png` — the signed-off campaign detail after the
  fix (13px tab, 12px status pill; the R2-1 summary stays `sr-only` with the panel shown).

The Space tree (`/home/cua/work/campaigns-phase4`) matches `9855961` and is kept with
`vendor/` and `node_modules/` for phase 5. The host tar and temporary scripts were deleted.

## Review round — phase 4 (BLOCK → fixes)

The independent review returned BLOCK on P1-1 and six P2s. Every item is addressed in
`9855961`; the spec amendment and this report follow.

| Item | What changed | Evidence |
| --- | --- | --- |
| **P1-1** | `cn()` registers the custom `text-*` sizes with `extendTailwindMerge`. | `Delete` 13px, glyph run **40** (board 39); `Add contact` 13px/122px (board 119); `Import CSV` 13px/117px (board 113); `Contacted` pill 12px/78×22, glyph 60 (board 12px, 74×20, glyph 58); `All contacts` 13px/97×30, glyph 73 (board 71). Re-shot `06-contacts-1280.png`; 02 A `New campaign` 13px and `Draft` pill 12px; 03 A tab 13px and `Draft` pill 12px. |
| **P2-1** | Every outcome is handled (validation/http/network/cancel/finish); successes are counted, `busy` resets, the unprocessed ids stay selected, an inline alert and one error toast report `done of total`, and partial changes reload. | Blocked `*/contacts/*/cell`: alert "Marked 0 of 2 contacts as do not contact. The rest were not changed."; `dncDisabled: false`, `searchDisabled: false` after it. |
| **P2-2** | One summary toast per bulk action; flash toasts suppressed during the loop and deduped by id; intermediate visits use `only: []`; delete restores the current page or the new last page. | DNC toasts `["Marked 2 contacts as do not contact."]`; page 3 restore `?list=&page=3&q=`; page 52 delete of 7 → `?list=&page=51&q=`; one toast each. |
| **P2-3** | The picker starts empty (`Choose a campaign`, submit disabled), only Draft/Stopped campaigns are returned, and each option carries its status label. | Trigger "Choose a campaign", submit disabled; options Paused/Stopped, Q4/Draft, Series A/Draft; no Live/Archived; select enables submit; `1 recipient added.` |
| **P2-4** | Create and edit modals use `contactStatusLabel`. | Both option lists: `New, Contacted, Replied, Bounced, Unsubscribed, Do not contact`. |
| **P2-5** | The open link is always visible under `any-pointer: coarse`, plus hover and `focus-visible`. | At rest 0/none; hover 1/auto; touch emulation (`coarse: true`) 1/auto. |
| **P2-6** | Report corrected (below): no board card, the rules run x 215–1279, the picker statement, the pill cause and the duplicate gap number. The 33px/`px-3` decisions are recorded as a spec Amendment. | This report and `spec.md`. |

The shared `cn()` fix changes the signed-off campaign pages from accidentally 16px
controls/13px pills to the spec's 13px/12px. Those pages were rendered again; the campaign
list `New campaign` button is 139×32 at 13px, the `Draft` pill 46×22 at 12px, the detail
tab bar is 13px in a 36px bar, and the detail `Add recipients` button is 136×32 at 13px.
The 03 A tab bar stays a 36px bar with its 2px underline; the R2-1 summary is still
`sr-only` with the panel shown. The change is a correction toward the spec, not a new
regression.

## Deviations

1. **33px rows / 32px cells, 12px cell padding** — accepted Amendment, see above. The
   brief's 44px/`px-4` figures are 02 A's campaigns table, not board 06.
2. **Company and Title are team contact fields**, resolved by name; a team without those
   fields gets no such column. Other custom fields follow Title and can scroll the grid
   horizontally inside its container.
3. **No row-number or Lists-count column.** The row number is replaced by the checkbox
   column; list membership is the bottom tabs.
4. **Open is not a column.** A small arrow in the last data cell is hover/focus-only on a
   pointer device and always visible for `any-pointer: coarse`; the board has no
   affordance. This keeps touch reachable without a seventh column.
5. **Status copy is mapped in the UI**, not in `ContactStatus::label()`; the enum values and
   the existing `status_label` payload stay unchanged. `contacts/show.tsx` and the
   create/edit modals read `New` / `Do not contact` through the same map.
6. **`Add column` moved into the settings/columns menu**, and the create CTA reads
   `Add contact` (the board's copy) instead of `New contact`.
7. **Search says "name or email"**, because `ContactController::index` matches the name and
   email identities only. The board's "company" promise has no query behind it.
8. **Filters filters lists** using the existing `list` parameter. There is no status/column
   filter in the controller or payload; the menu is the compact list switcher.
9. **Pagination sits in the bottom strip** (`Showing x–y of N`, Previous/Next) instead of
   the numbered `Pagination` below the card. The board has no pagination; the strip keeps
   the workspace full-height.
10. **List tabs show counts**, which the board omits; the data exists in the payload.
11. **Bulk actions are N sequential requests** to the existing per-contact endpoints (max
    25 rows). No bulk route was added; intermediate answers use `only: []`, one summary
    toast is shown, and delete restores the page.
12. **Bulk actions are not hidden from members** whose role might not delete: the contact
    policy is team membership for view/update/delete today, so every viewer of this page can
    perform them; the server still gates each request. Field add/edit/delete stays behind
    the existing `can.create` flag.
13. **The subtitle sits ~3px higher** than the board (line box 44–64 against board glyphs
    51–62); `PageHead` is shared with the signed-off campaign pages and was not moved.
14. **The status pill is 78×22 with 12px text**, against the board's ≈74×20 and 12px text
    (glyph 60 vs 58). The remaining 4px width is the shared `Badge`'s 8px horizontal
    padding (the board has ≈7.5px); the 22px height is the spec's badge height (the board
    scans 20px). This is no longer a type-scale issue.
15. **The buttons are 4–10px wider** than the board while their glyph runs are within
    1–2px (`Delete` 86 vs 76, glyph 40 vs 39; `Filters` 84 vs 76; `Add to campaign` 152 vs
    143; `Do not contact` 117 vs 108; `Add contact` 122 vs 119; `Import CSV` 117 vs 113).
    The shared `Button` spacing/icon gap accounts for it; y and height match.
16. **Phone values are normalized digits** (`ContactIdentityType::Phone::normalize()` strips
    punctuation on write); the board's formatted numbers are not stored anywhere.
17. **The list strip's active tab is `All contacts`** in the render because that is the
    fixture's initial URL; the board's designer had `Fintech CFOs` selected. The active
    pill/underline state is the same and was exercised through Filters.
18. **The board grid has no card.** Its rules are full-bleed x 215–1279; the implementation
    card is x 241–1255 inside the 24px page padding (see the grid-frame note above).
19. **The campaigns payload is a non-column addition.** The brief allows a read-only payload
    addition only when a column is one `with`/join away; `campaigns` is there for the
    `Add to campaign` picker. It is limited to Draft/Stopped and the recipients endpoint
    re-gates every insert.
20. **The status trigger hides its chevron** (`[&>svg]:hidden`) to fit `Do not contact` /
    `Unsubscribed` in the board's 140px column. The pill is still a select (click, arrows,
    Enter) but no longer advertises that visually.

## Gaps and open questions

1. **No first-class Company/Title.** They exist only as arbitrary `ContactField`s, so a team
   that never created a matching field cannot show those columns, and an alias like
   `Organization` is treated as Company. A future schema change would be a migration.
2. **No bulk contact endpoints.** Marking 25 contacts do-not-contact is 25 PATCHes; deleting
   them is 25 DELETEs. `only: []` reduces the client merge, not the server work. A bulk
   route would need its own request/policy/test and was outside the "existing endpoints"
   instruction.
3. **Bulk delete's redirect is query-less.** `ContactController::destroy` still redirects to
   `contacts.index`; the page compensates with a restore `GET` to the computed page. A
   `back()` redirect would remove that round trip but changes the single-delete behaviour
   outside this phase.
4. **No search over company/title properties.** The board's placeholder implies it; the
   query does not. Adding a `whereHas('properties', …)` would need its own index discussion.
5. **`Filters` cannot filter status** for the same reason; it only mirrors the lists.
6. **No 390 layout.** The toolbar wraps and the grid scrolls, but the mobile tab bar and
   icon-only bulk bar are phase 6.
7. **The sidebar still says `Team · Owner`** instead of the board's `Team · 4 members`
   (phase 1's `UserTeam` has no member count).
8. **The fixture's `1,284 contacts`** is render data; no production count was implied.
9. **Bulk `Do not contact` writes `status` only**, not `do_not_contact_at`, because it
   reuses the cell endpoint. Phase-3 R2-7 counts either as blocked, but
   `ContactController::show()` reads the timestamp, so a contact marked in bulk still shows
   `Do not contact: No` on its detail page. The inline status dropdown had the same
   behaviour before this phase; fixing it needs an action/request change.
10. **The board's active-cell border** is reproduced as a 2px `--ring` focus ring, which
    only appears once a cell is clicked or tabbed to; the light screenshot has no focused
    cell, so the board's outlined Name cell has no direct visual counterpart in it.
11. **The campaigns list is unbounded and re-sent on every index visit.** Making it
    `Inertia::optional` and loading it when the picker opens would avoid that; not done in
    this round.
12. **The campaigns prop remains a non-column payload addition** (accepted with P2-3); a
    future bulk endpoint or picker endpoint could replace it.
