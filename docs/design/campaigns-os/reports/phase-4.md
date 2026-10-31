# Phase 4 report — contacts grid

Branch `design/campaigns-os`: feature commit `57a15f1` and follow-up `045f845`
on top of `2c94273`. Worktree
`/home/azri/projects/contactout/campaigns-design`. Paper MCP was not called. Every board
number below is a pixel scan of `06-contacts-grid-a-1280.jpg`; every implementation number
is a DOM rect from a 1280×900 headless Chromium render in the cua Space `local:devtest`.

## What changed

Frontend:

- `resources/js/pages/contacts/index.tsx` — rebuilt to board 06 A: `PageHead` with
  `Import CSV` (secondary) and `Add contact` (primary), the breadcrumb bar opted out
  (`showHeader: false`), the full-height `h-svh` workspace, the toolbar (Filters,
  selection count, bulk actions, search, columns menu), the grid card with the list-tab
  strip pinned at its bottom, and the empty/out-of-range states on `EmptyState`.
- `resources/js/components/contacts/contact-grid.tsx` (new) — the shared-`Table` grid:
  select-all with an indeterminate state, row checkboxes, the four core cells, the
  Company/Title/extra columns resolved from the team's contact fields, 12px muted headers,
  truncating cells with `title` tooltips, the keyboard cell navigation and the
  hover/focus-only contact link.
- `resources/js/components/contacts/add-to-campaign-modal.tsx` (new) — campaign picker
  for the selected contacts; posts to the existing campaign recipients endpoint.
- `resources/js/components/contacts/list-tabs.tsx` — board strip: 35px tall, 30px pills,
  active tab on `--accent` with a 2px `--primary` underline, counts, `+ Add list`, list
  rename/delete, and the pagination slot at the right. It now keeps `q` when a list is
  chosen instead of silently clearing the search.
- `resources/js/components/contacts/inline-cell.tsx`,
  `inline-property-cell.tsx`, `inline-status-cell.tsx` — 32px editors instead of 44px, so
  a row is 33px; value tooltips; 2px `--ring` cell focus; the status trigger is `size="sm"`
  and hides its chevron (the board has no dropdown glyph), which is what let `Do not
  contact`/`Unsubscribed` fit the 140px column.
- `resources/js/components/contacts/contact-status-badge.tsx` — the board's copy map
  (`NotContacted` → `New`, `DoNotContact` → `Do not contact`). The enum values are
  untouched; `contacts/show.tsx` picks the copy up through the same badge.
- `resources/js/components/ui/checkbox.tsx` — restyled in place for the indeterminate
  state (a `Minus` instead of a check when `data-state="indeterminate"`). Public API
  unchanged.
- `resources/js/types/contacts.ts` — `ContactCampaignOption`.
- `resources/js/pages/campaigns/show.tsx` — carry-over R2-1 (below).

Backend:

- `app/Http/Controllers/Contacts/ContactController.php` — adds the read-only `campaigns`
  prop through `campaignOptions()` for `Add to campaign`. No query was added for a column.
- `tests/Feature/Contacts/ContactControllerTest.php` — one test that the index lists only
  the member's campaigns (another team's campaign is excluded).

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
stays reachable through a 28px arrow that appears on hover/focus in the last data cell
(no seventh column, so the six column starts match the board).

## Data added — the query and its plan

The only new query is the campaigns prop:

```sql
select id, name from campaigns where team_id = ? order by name asc
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
  `CampaignPolicy::update`. The picker only lists campaigns the user can update.
- **Do not contact** — `PATCH contacts/{contact}/cell` with
  `{field: "status", value: "DoNotContact"}`. Contacts already at that status are skipped.
- **Delete** — a `ConfirmDialog` first, then `DELETE contacts/{contact}` per row. The
  endpoint redirects to the bare `contacts.index`, so a filtered deletion is followed by
  one `GET` that restores `q`/`list`.

Selection is pruned to ids on the current page and reset when the page, search or list
changes. The header checkbox is `indeterminate` when some rows are selected.

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
viewport height while its height and bottom anchoring do not.

| Element | Board 06 A | Implementation |
| --- | --- | --- |
| H1 | glyph band y 22–36 | line box y 16–44, cap top ≈21 |
| Subtitle | glyph band y 51–62 | line box y 44–64, glyphs ≈47–58 |
| Toolbar controls | y 88–119, 32px tall | y 88–120, 32px tall |
| `Filters` | x 242–318, 76px | x 240–324, 84px |
| `2 selected` | x ≈330–390 | x 332–397 (20px line) |
| `Add to campaign` | x 404–547, 143px | x 405–557, 152px |
| Search field | x 971–1215, 244px | x 970–1216, 246px |
| Gear button | x 1228–1253 | x 1224–1256, 32px |
| Card | x 240–1256, top 129 | x 241–1255 (1px border), top 129 |
| Table header | 129–164, 35px | 129–164, 35px |
| Row 1 / dividers | 164–197, 33px pitch | 164–197, 33px pitch |
| Cell height | 32px + 1px divider | 33px border-box |
| Column starts | 279 / 469 / 689 / 849 / 989 / 1139 | 279 / 469 / 689 / 849 / 989 / 1139 |
| Column widths | 39 / 190 / 220 / 160 / 140 / 150 / 117 (clipped at the artboard) | 38 / 190 / 220 / 160 / 140 / 150 / 116 (not clipped) |
| Status pill | ≈74×22, x 861, y 168–190 | 83×22, x 861, y 169–191 |
| List-tab strip | y 783–818 at a 820 viewport, 35px | y 864–899 at a 900 viewport, 35px (bottom-anchored) |
| Active tab | x 340–448, 30px pill + 2px underline | active `All contacts` x 245–359, 30px pill + 2px underline |
| Page width | 1280 | 1280, `documentHeight` 900 (no page scroll) |

**Row pitch conflict.** The brief says "44px rows (the board's divider pitch)". Board 06 A
does not measure 44: its dividers sit at y 164/197/230/263…, a **33px** pitch, and the
spec's density line says "grid cells 32px". 44px is the 02 A campaigns-table amendment.
The implementation follows board 06 and the spec (32px cells + the 1px cell border = 33px),
which is the only reading that matches both the board and the spec; using 44px would have
produced an 11px-per-row mismatch against the board. For the same reason cell padding is
12px (`px-3`), not the brief's `px-4`, which is the 02 A table measurement: Name text
starts at 291 against the 279 divider on both board and render.

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

`06-contacts-1280-dark.png` (localStorage `appearance = 'dark'`). Body and header
`oklch(0.145 0 0)`, selected rows `--accent` `oklch(0.269 0 0)`, `Contacted` info pill on
the dark accent with its `--badge-info-border` `#8b7dff`, all six status pills legible. The
toolbar `Delete` is an outline button with `--destructive-foreground`
(`oklch(0.637 0.237 25.331)`, **5.2:1** on the dark page) and
`hover:bg-badge-danger` / `hover:text-badge-danger-foreground` (8.47:1), so it inherits the
phase-3 P2-9 fix instead of the filled destructive variant.

## Verification

Space provenance: the working tree was tarred to `/home/cua/work/campaigns-phase4` over the
phase-3 tree's `vendor/` and `node_modules/`; nothing was installed, built, served or
tested on the host. Fixture (temporary, removed from the host and the Space):
Northwind Growth / `preview@northwind.io`, `Company` and `Title` contact fields, the three
board lists, 1,284 contacts (the board's eight rows first, all six statuses, a long
company/title row, email + phone on the rows on page 1) and two campaigns.

Final checks on the tree that matches `045f845` (no preview route, no fixture seeder,
no render artifacts):

```
$ npm run build            BUILD_EXIT=0    ✓ built in 2.52s
$ npm run types:check      TYPES_EXIT=0    tsc --noEmit
$ npm run check            CHECK_EXIT=0    All 156 files are correctly formatted
                                           Found no warnings or lint errors in 139 files
$ php artisan test         TEST_EXIT=0     Tests: 571 passed (2483 assertions)
$ php vendor/bin/pint --test               PINT_EXIT=0     PASS 365 files
$ php vendor/bin/phpstan analyse …         PHPSTAN_EXIT=0  [OK] No errors
```

`php artisan test` is the full suite on SQLite `:memory:` from `phpunit.xml`, run without
a `DB_DATABASE` export (that override wiped the phase-3 fixture). Interactions driven
through CDP on the final build:

- Two row checkboxes → header checkbox `data-state="indeterminate"`, toolbar count
  `2 selected`; select-all → `25 selected`, `data-state="checked"`; deselect → 0 and the
  count/bulk actions disappear.
- `ArrowRight` on the Name cell moves focus to `Email: …` (`data-grid-column="1"`). The
  status Select keeps its arrow keys.
- Inline name edit: typing and Enter persisted `Priya Natarajan-Rao` and re-rendered the
  cell.
- Filters → `APAC founders` gave `?list=2&q=` and one row; the list tab reports
  `aria-current="page"`.
- `Do not contact` on a `New` row changed its pill to `Do not contact`.
- `Add to campaign` added 1 recipient to campaign 1 (`1 recipient added.` toast), and
  `Delete 2 contacts` moved the page-head total from `1,284` to `1,282`.
- Hovering the last data cell of row 1 takes the contact link from `opacity: 0` /
  `pointer-events: none` to `opacity: 1` / `auto`; it is also keyboard-focusable with
  `aria-label="Open <name>"`.

Screenshots (committed next to this report):

- `phase-4/06-contacts-1280.png` — light, 1280×900, two selected rows, every status.
- `phase-4/06-contacts-truncation-1280.png` — the same table with the long company/title
  row and the hovered link.
- `phase-4/06-contacts-1280-dark.png` — dark mode.

The Space tree (`/home/cua/work/campaigns-phase4`) was re-extracted from the host after
`045f845`; the changed PHP/TS files were md5-compared with the host worktree and matched.
It is kept with `vendor/` and `node_modules/` for phase 5. The host tar and `/tmp/phase4`
were deleted.

## Deviations

1. **33px rows / 32px cells, 12px cell padding** (see the conflict note above). The brief's
   44px/`px-4` figures are 02 A's campaigns table, not board 06.
2. **Company and Title are team contact fields**, resolved by name; a team without those
   fields gets no such column. Other custom fields follow Title and can scroll the grid
   horizontally inside its container.
3. **No row-number or Lists-count column.** The row number is replaced by the checkbox
   column; list membership is the bottom tabs.
4. **Open is not a column.** A small arrow appears on hover/focus in the last data cell, so
   the contact page stays reachable without a seventh column. The board has no affordance.
5. **Status copy is mapped in the UI**, not in `ContactStatus::label()`; the enum values and
   the existing `status_label` payload stay unchanged, so the phase-3 tests are untouched.
   `contacts/show.tsx` also reads `New` / `Do not contact` now.
6. **`Add column` moved into the settings/columns menu**, and the create CTA reads
   `Add contact` (the board's copy) instead of `New contact`.
7. **Search says "name or email"**, because `ContactController::index` matches the name and
   email identities only. The board's "company" promise has no query behind it.
8. **Filters filters lists** using the existing `list` parameter. There is no status/column
   filter in the controller or payload, and inventing one was out of scope; the menu still
   gives the list switcher when the bottom strip is narrow.
9. **Pagination sits in the bottom strip** (`Showing x–y of N`, Previous/Next) instead of
   the numbered `Pagination` below the card. The board has no pagination; the strip keeps
   the workspace full-height.
10. **List tabs show counts**, which the board omits; the prompt asks for counts where the
    data exists and `lists.contacts_count` is already in the payload.
11. **Bulk actions are N sequential requests** to the existing per-contact endpoints (max
    25 rows). No bulk route was added. A filtered bulk delete costs one extra `GET` to
    restore `q`/`list`.
12. **Bulk actions are not hidden from members** whose role might not delete: the contact
    policy is team membership for view/update/delete today, so every viewer of this page can
    perform them; the server still gates each request. Field add/edit/delete stays behind
    the existing `can.create` flag.
13. **The subtitle sits ~3px higher** than the board (line box 44–64 against board glyphs
    51–62); `PageHead` is shared with the signed-off campaign pages and was not moved.
14. **The status pill is 83px wide** against the board's ≈74 because the shared `Badge`
    uses 8px horizontal padding. Height and colours match.
15. **`Add to campaign` and `Filters` are 9px and 8px wider** than the board; their starts
    (405 vs 404, 240 vs 242) and the toolbar y are aligned.
16. **Phone values are normalized digits** (`ContactIdentityType::Phone::normalize()` strips
    punctuation on write); the board's formatted numbers are not stored anywhere.
17. **The list strip's active tab is `All contacts`** in the render because that is the
    fixture's initial URL; the board's designer had `Fintech CFOs` selected. The active
    pill/underline state is the same and was exercised through Filters.

## Gaps and open questions

1. **No first-class Company/Title.** They exist only as arbitrary `ContactField`s, so a team
   that never created a matching field cannot show those columns, and an alias like
   `Organization` is treated as Company. A future schema change would be a migration.
2. **No bulk contact endpoints.** Marking 25 contacts do-not-contact is 25 PATCHes; deleting
   them is 25 DELETEs. A bulk route would need its own request/policy/test and was outside
   the "existing endpoints" instruction.
3. **Bulk delete's redirect is query-less.** `ContactController::destroy` still redirects to
   `contacts.index`; the page compensates with a restore `GET`. A `back()` redirect would
   remove that round trip but changes the single-delete behaviour outside this phase.
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
9. **The board's active-cell border** is reproduced as a 2px `--ring` focus ring, which
   only appears once a cell is clicked or tabbed to; the light screenshot has no focused
   cell, so the board's outlined Name cell has no direct visual counterpart in it.
10. **The campaigns payload is a non-column addition.** The brief allows a read-only payload
   addition only when a column is one `with`/join away; `campaigns` is there for the
   `Add to campaign` picker, not for a column. The alternative was a disabled button and a
   gap. The picker lists the team's campaigns and the recipients endpoint re-gates every
   insert.
11. **The status trigger hides its chevron** (`[&>svg]:hidden`) to fit `Do not contact` /
   `Unsubscribed` in the board's 140px column. The pill is still a select (click, arrows,
   Enter) but no longer advertises that visually; the header could not carry a caret the
   board does not have.
