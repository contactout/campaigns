# Phase 6 — the 390 pass and the phase-5 carry-overs

Every screen rebuilt in phases 1–5 now has its 390 layout, the shell has a bottom tab
bar and a More sheet, and the eight phase-5 review carry-overs are closed. Board
numbers below come from a grey-level pixel scan of the 1x JPEGs in
`/home/azri/projects/contactout/.design-refs/campaigns-os/`; render numbers are DOM
rects from a 390×844 (and 1280×900) headless Chromium render in the cua Space
`local:devtest`, at deviceScaleFactor 1. Renders and the raw measurement JSON are in
`storage/render/` and `/tmp/p6-render.json` in that Space.

## What changed

Shell and tokens:

- `resources/css/app.css` — mobile body token (`--text-body: var(--type-body)`,
  14px below 64rem, 13px from `lg`), and the touch-target note that replaced the
  pseudo-element hit-area experiment.
- `components/ui/button.tsx`, `ui/input.tsx`, `ui/select.tsx` — `max-lg:min-h-11`, so
  every control is a real 44px tall below `lg`.
- `components/ui/sidebar.tsx`, `hooks/use-mobile.tsx` — the sidebar breakpoint moves from
  `md` to `lg`; the static rail is `hidden … lg:flex`. No public API changed.
- `hooks/use-nav-items.ts` (new) — one navigation definition: the sidebar's six
  destinations, the tab bar's five, and what More lists. Dashboard keeps its sidebar
  label; the same page is `Home` in the tab bar, as the board labels it.
- `components/mobile-tab-bar.tsx` (new) — fixed 56px bar, five equal 78px slots, active
  violet icon and label, plus the More bottom sheet.
- `layouts/app/app-sidebar-layout.tsx` — the bar, and `pb-[calc(3.5rem+env(safe-area-inset-bottom))]`
  so content and sticky bars clear it.
- `components/page-head.tsx` — the sheet trigger moved into the title row (`lg:hidden`),
  `mobileDescription` and `hideDescriptionOnMobile` so the 390 copy can differ from the
  desktop subtitle, and `title` now accepts a node (Dashboard vs the greeting).
- `components/form-label.tsx` (new) — the product form labels on the label role.

Screens:

- `pages/campaigns/index.tsx` — stacked rows (name, `steps · recipients · Created …`,
  pill), full-bleed dividers, `+ New`, `All` status on the phone.
- `pages/dashboard.tsx` — `Dashboard` title with the team as subtitle at 390, 2×2 stats,
  checklist CTAs on their own line, no head CTA.
- `pages/campaigns/show.tsx` — status folded into the subline, mobile action cluster
  (Stop/Edit/⋯ plus the primary), 2×2 stats, mobile overflow menu.
- `components/campaigns/campaign-steps-tab.tsx` — no grip, no metric lanes, full-width
  `Add step` at the end of the list.
- `components/campaigns/campaign-recipients-tab.tsx` — stacked name/email rows, row-click
  selection, sticky `N selected / Remove` bar above the tab bar.
- `components/campaigns/sequence-editor.tsx` — below `lg` a full-screen form with
  `Cancel | Edit step · Day N | Save` and a full-width `Delete step`; the sequence list
  and desktop footer stay at `lg`.
- `components/campaigns/step-form.tsx` — `+ Insert` and a pen icon on mobile,
  `FormLabel`, and the apply-template select no longer starts with an empty value.
- `pages/contacts/index.tsx`, `components/contacts/contact-grid.tsx`,
  `components/contacts/list-tabs.tsx` — list chips at the top, icon-only `Import CSV`,
  `+ Add`, icon-only Filters, stacked rows with initials avatar and `Title · Company`,
  sticky `N selected / Add to campaign / ⋯`, compact pagination at the end of the list.
- `pages/templates/index.tsx` — icon-only `New folder`, `+ New`, groups as cards with the
  `Updated …` subline under the name.
- `pages/templates/show.tsx`, `components/templates/placeholder-manager.tsx` — mobile
  header (`Edit template`, `Save`, ⋯ with Discard/Delete), chips-only placeholders with
  the stored fallback in the accessible name, touch-visible delete, `+ Insert`.
- `pages/mailer-connections/index.tsx` — cards with `Sent today` as text, `+ Add` menu
  (Add SMTP / Connect Gmail / Connect Outlook), shared row/card actions.
- `components/mailer-connections/signature-select.tsx`, `cap-meter.tsx` — the clamp and
  the meter fix (carry-overs).

Backend (carry-overs, committed separately in `ae5bdae`):

- `app/Http/Controllers/MailerConnections/MailerConnectionController.php` — `sent_today`
  reads `sent_count` inside its refresh window; `sentTodayByConnection()` and its query
  are gone.
- `app/Http/Controllers/Campaigns/CampaignController.php` — the start-blocked connection
  reason switches on `displayStatus()`; `connection.has_exception` is gone.
- `app/Http/Requests/Campaigns/StoreCampaignRecipientsRequest.php` — optional `idle_only`.
- `resources/js/components/contacts/add-to-campaign-modal.tsx` — sends `idle_only: true`
  and shows the server's `campaign` error.
- Tests: the sent-today test now covers inside-window, stale-window and at-cap; a new
  test posts `idle_only` at a started campaign and asserts the 422 plus that the detail
  dialog (no flag) still adds.

## Mobile type scale

Measured from the boards: a campaign row's name is **169px** wide at 390 against
**158px** at 1280 for the same string (≈14px vs 13px); the `Name` field label is
**31px** wide and 9px cap at both (12px); the H1 `Campaigns` is 106px vs 102px (20px);
the stat value `4` has the same cap height at both (24px). So the 390 scale is a
**body-only** step and the rest of the scale is shared:

| Token | Below `lg` | `lg` and up |
| --- | --- | --- |
| `--text-body` | 14px | 13px |
| `--text-label` | 12px | 12px |
| `--text-h1` | 20px | 20px |
| `--text-h3` | 16px | 16px |
| `--text-stat` | 24px | 24px |
| `--text-brand` | 14px | 14px |
| `--text-caption` | 11px | 11px |

`--text-body` reads `var(--type-body)`; `:root` sets 14px and a `min-width: 64rem`
block restores 13px. Line-height stays an explicit `leading-*` at each call site, per
the phase-2 amendment. Sizes need no dark pairs; the dark render confirms the same
geometry (below). Inputs stay at 16px below `lg` (`text-base lg:text-body`) to avoid
iOS focus-zoom.

## Measurements

Board / render at 390. Rows with a board value the scan could not settle are marked
`—`.

| Screen | Element | Board 390 | Render 390 |
| --- | --- | --- | --- |
| shared | bottom bar height | 64 (56 + 8px home indicator; border y780/844) | **57** (56 + 1px top border, plus a safe-area inset when present) |
| shared | bottom bar item pitch | 78 (5 × 78) | **78** |
| shared | bottom bar icon / label | 18px icon, 9px label cap (≈12px) | **20px** icon, **11px/16** label |
| 02 | H1 box top / x | cap y58 − 44 status bar = 14 into the page; x 17 | **y 24, x 44** (title starts after the 28px sheet trigger + 2px gap) |
| 02 | search / status select | 222×32 at y93; status 101 wide | **249×44; 101×44** (44px target) |
| 02 | list row height | 68 (dividers 137/205/273/341/408/476/544) | **68** |
| 02 | name / meta type | 169px name run; meta 12px | **14px / 12px** |
| 03 | stat card height / gap | 84 tall; gap 16 | **84; 16** (`gap-4 lg:gap-3`) |
| 03 | step row | 43 (cards 490–533, 593–635, 695–738) | **60** (44px kebab + two type lines) |
| 03 | metric lanes / drag handle | absent at 390 | **absent** (measurement: metrics hidden, grip not rendered) |
| 03 | Add step | full width | **358×44** |
| 04 | recipient row | 56 pitch (dividers 306/362/418/474/530/586/642) | **65** (min 64 + border) |
| 04 | sticky bar | ~56 (`1 selected` + `Remove`) | **65** (44px Remove + 20px padding), bottom 56 above the screen edge |
| 05 | editor pane | full screen | **390×844 at 0,0**; sequence list hidden; tab bar covered (`elementFromPoint` inside the pane) |
| 05 | Cancel / Save / Delete step | header row; footer action | **44** each; Delete step **358×44** |
| 06 | contact row | 56–67 pitch (dividers 291/358/414/469/525/581/637) | **68** |
| 06 | list chips / search | chips above the toolbar; search 32 tall | **tabs top y80**; search **246×44** |
| 06 | import / add | 32 icon; `+ Add` | **32×44** (aria `Import CSV`); **73×44** (aria `Add contact`) |
| 07 | group header / row | 44 header; rows 55–64 | **39 header; 61 row** |
| 07 | New folder / New | icon; `+ New` | **38×44** (aria `New folder`); `+ New` |
| 08 | Save / ⋯ | text `Save`; no overflow menu on the board | **44** each, `Save` violet text; ⋯ **44** (aria `More template actions`) |
| 08 | placeholders | chips only | **4 chips**, fallback hidden below `lg` and present in the chip's accessible name (`Insert first_name, fallback there`) |
| 09 | card height | 120–138 | **174** (44px signature control + 16px card padding) |
| 09 | failure banner | ~56, two lines | **102**, four lines (full stored error, no `role`) |
| 09 | Sent today | `0 / 1,000` as text | **text**, no meter bar; `<div aria-hidden>` bar only from `lg` |
| 10 | empty state | icon 64, one primary | **64**, one primary (`New campaign`) |
| 11 | blocked panel | 5 reasons + 2 actions | **246 tall**, reasons + `Edit step 2` / `Go to Connections`, Start `aria-disabled` + 0.5 opacity |

Horizontal overflow: `document.scrollWidth === innerWidth` on all 390 screens.

Touch targets, measured in the DOM (visual height, not a hit-area guess): every
button, input, select trigger and row on the 390 screens is **44px or taller** —
head controls 44, search 44, select 44, list rows 68, tab-bar items 56, More rows 44,
checkbox wrappers 44. The first attempt used an `::after` inset to grow 32px buttons;
the probe showed the extension is painted over by the next sibling, so the primitives
now set a real `min-height` instead.

## 1280 regression

Re-rendered every screen at 1280×900 (`*-1280-regression.png`). No layout regressed
from the phase-3/4/5 numbers:

- Sidebar visible at 216px, bottom tab bar hidden, no horizontal overflow on any of
  the nine screens.
- Campaigns: table rows 44, search 260×32, `New campaign` 32; H1 at x 240.
- Contacts: table visible, mobile list hidden, rows 33, `px-3` cells.
- Connections: rows 44, meter bar visible, card list hidden, signature trigger
  **99px** inside the 109px column — right edge **936 < 940** (cell edge), so it can no
  longer reach the Status pill. Longer names truncate with a `title`.
- Campaign detail: desktop action cluster (Edit / Duplicate / Archive / Stop / Add
  recipients / ⋯), step metric lanes back, `Sends from …` line back.
- Step editor: sequence list visible (300px), pane 692px, desktop footer visible.
- Two desktop-only regressions the first render caught and this commit fixes: the
  mobile `⋯` menu and the mobile `+ Add` menu both rendered at `lg` alongside their
  desktop buttons (campaign detail and connections).

The dashboard's 390 headline is `Dashboard` with the team below it; at 1280 it is the
greeting with `Northwind Growth · <date>`.

## The `More` panel

The 390 boards have no More panel, so it was designed here: a bottom `Sheet` (`More`
title, 44px rows) listing **Signatures**, **Connections** and **Settings** with the same
labels and icons the sidebar uses, all read from `useNavItems()` so the two cannot
drift. The trigger is active whenever one of those destinations is current. Verified
by clicking through: `/northwind/signatures` → `/northwind/mailer-connections` →
`/settings/profile`, each closing the sheet.

The page head keeps the existing sidebar sheet trigger, as the brief asks, because that
sheet is the only mobile route to the team switcher, the user menu and sign-out.
Screens with a back link (campaign detail, template editor) show the back link instead
of the trigger and rely on the tab bar.

## Phase-5 carry-overs

| Item | Outcome |
| --- | --- |
| P2-1 meter vs cap | **Fixed.** `sent_today` is `sending_limit_refreshed_at?->isFuture() ? sent_count : 0`, the exact counter `SendEmail::isRateLimited()` reads. The aggregate and its unindexed scan are deleted; deleting a campaign no longer makes room appear. The test covers inside-window (142), stale window (0) and at-cap (400). |
| P2-2 third copy of the status rule | **Fixed.** `startState()` switches on `displayStatus()`: `Failed` → "failed verification", any other non-`Verified` → "is :status". `connection.has_exception` is removed from the payload, the TS type and the test. |
| P2-3 signature overflow | **Fixed.** Trigger `w-full max-w-full min-w-0` plus `pr-1 pl-1.5` on the cell; measured 99px wide, right edge 936 inside the 940 cell edge. |
| P2-4 editor Delete removed without a deviation | **Fixed.** The editor head has a `⋯` menu with `Delete template` (gated on `can.delete`, reusing `DeleteTemplateModal`) at both breakpoints, and `Discard changes` for mobile where the board shows no Discard button. |
| P2-5 guarded Discard focus | **Fixed.** `ConfirmDialog` forwards an optional `onCloseAutoFocus`; the editor prevents Radix's restore and focuses `Name`. |
| P2-6 form labels 14px | **Fixed.** `FormLabel` (12px, `lg` 12px) in the template editor, the step form and the placeholder form. `ui/label` is untouched so the settings and auth screens keep their scale. |
| P2-7 meter `role="img"`, banner `role="alert"` | **Fixed.** The bar is `aria-hidden` decoration and the text carries the value; the banner has no live role. |
| P2-8 wrong index claim | **Fixed in the reports.** InnoDB creates `campaign_emails_mailer_connection_id_foreign`; the probe scanned because all 50k rows matched. The aggregate is gone, so the recommendation is moot. See the correction appended to `reports/phase-5.md`. |
| Phase-4 R2-2 last window | **Closed cheaply.** `idle_only` on the request is rejected with a `campaign` error once the campaign is not Draft/Stopped; the grid dialog sends it and shows the message. The campaign detail's dialog omits it, so running campaigns still accept recipients by design. |

## Deviations

1. **Sheet trigger in the page head.** The brief keeps it; the 390 boards have no
   hamburger. The title therefore starts at x 44 instead of 17, and a head row with an
   action is 44 tall instead of 28. Screens with a back link show the back link only.
2. **44px controls, not the board's 28–32.** Buttons, inputs and select triggers get
   `max-lg:min-h-11`; the boards' 28px `+ New`, 32px search and 32px select are 44 tall
   below `lg`. Rows, tab items and More rows were already ≥44.
3. **A real `Sent today` in the connections cards without the meter bar**, and the
   signature stays an inline select (`Team default`, `Sam — default`) rather than the
   board's static `Signature: None`.
4. **An overflow kebab on every connection card** (the board has none) so Verify,
   Reconnect, Edit and Delete stay reachable on a phone.
5. **Recipients sticky bar is `N selected | Remove`**, following board 04 rather than
   the brief's `Add to campaign` (which is the contacts bar, board 06).
6. **`+ Recipients` is kept on a Draft campaign's head** (board 11 shows only Edit, ⋯ and
   Start) so recipients can still be added at 390, where the recipients toolbar's add
   button is hidden.
7. **Connections banner shows the whole stored error** (four lines, 102px); the board
   truncates it to "535 Authentication failed." and 56px. Nothing is hidden from a
   screen reader or a user here.
8. **Template editor has no pen/Signature action** — the template show payload carries no
   signatures (phase-5 deviation 6 still holds). Its placeholders are chips only at
   390, as the board shows, with the stored fallback in the chip's accessible name.
9. **`Created …` not `Updated …`** in the campaign row subline: the index payload has
   `created_at`, not `updated_at` (phase-1 decision). The timestamp format and the
   relative wording match.
10. **Order is data-driven, as on desktop:** template folders by name (Cold outreach,
    Events, Follow-ups, Q1 experiments), contacts lists by name, connections by name.
11. **Contacts keeps a "25 on this page" select-all row and a pagination row** at 390;
    the board shows neither, but bulk select and paging have to stay reachable. The
    column manager stays desktop-only.
12. **Step editor body scrolls.** Apply template, subject, body, placeholders, day, time
    and the threaded checkbox live in the scrollable body; the board fits them without
    scrolling because it omits the placeholder chips' wrapping.
13. **Stat grid gap 16 at 390, 12 from `lg`** (boards 01/03 show 16; the 1280 amendment
    keeps 12).
14. **Bottom bar 57px** (56 + border) and a safe-area inset; the board's 64px includes
    the 8px home indicator.
15. **Contacts is a flowing page on mobile** (no `h-svh` workspace) so the list and the
    pagination row scroll together; the desktop workspace is unchanged.
16. **The campaigns empty panel keeps the full `New campaign` label** while the head
    shows `New` — the board does the same at 390.

## Verification

Provenance: the committed tree (`665f078`, on top of `00d58ad` and `ae5bdae` for the
carry-overs) was tarred
from the host worktree and extracted over the Space copy after removing the temporary
preview route, the fixture seeder and the render scripts. `md5sum` of
`routes/web.php`, `resources/js/pages/campaigns/index.tsx` and the campaign controller
match the host byte-for-byte. Nothing was installed, built, served or tested on the
host.

Fixture (Space only, removed before the final run): Northwind Growth, Maya Reyes and
three members, one pending invitation from Harbor Labs; contacts `1,284` with Company
and Title fields across three lists; six campaigns with the board's names, statuses,
step and recipient counts (`312` recipients on Q4 Fintech CFO outreach, `731` dispatched with engagement
rows that make the Opened card read 37%); the draft Series A campaign with an empty-subject step, the
failed Mailgun relay and four do-not-contact recipients; `14` templates in `4` folders
with four placeholders (`there`, `your team`, null, `Sam`); connections at `142 / 400`,
`388 / 400` and `0 / 1,000` with the Mailgun exception.

```
$ npm run build            BUILD_EXIT=0    ✓ built in 2.80s
$ npm run types:check      TYPES_EXIT=0    tsc --noEmit
$ npm run check            CHECK_EXIT=0    All 160 files are correctly formatted
                                           Found no warnings or lint errors in 143 files
$ php artisan test         TEST_EXIT=0     Tests: 577 passed (2568 assertions), 8.83s
$ php vendor/bin/pint --test  PINT_EXIT=0  PASS 367 files
$ php vendor/bin/phpstan analyse …  PHPSTAN_EXIT=0  [OK] No errors
```

Interactions driven over CDP on that build:

- More sheet → Signatures, Connections, Settings (URLs above), each sheet closed on
  navigation.
- Recipients: clicking a row selected it, the sticky bar showed `1 selected` + `Remove`
  and sat 56px above the screen edge (above the tab bar).
- Contacts: same, with `Add to campaign` and the ⋯ menu (`Do not contact`, `Delete`).
- Step editor: opened from the row kebab, full-screen at 390 with `Cancel` / `Save` in
  the head and `Delete step` at the end; `Save` submitted and closed it. At 1280 the
  two panes and the desktop footer are back.
- Template editor: `Save` violet text at 390, `⋯` with `Discard changes` and
  `Delete template`.
- Connections: `+ Add` opened the Add SMTP / Connect Gmail / Connect Outlook menu.
- Dark mode at 390: `01-dashboard-390-dark.png`, `02-campaigns-list-390-dark.png`;
  `html.dark`, page background `oklch(0.145 0 0)`, tab bar background the same, no layout
  change.

## Gaps and open questions

1. **The fixture's sample numbers differ from the boards'** even where the shapes
   match (dashboard deltas, `1,105` sent vs `2,418`, and five do-not-contact contacts on
   the first page against the board's one). The tables above compare layout, not sample
   data.
2. **The sheet trigger is a deliberate board deviation** (deviation 1). If the next
   review prefers the boards, the trigger can move to the More sheet's header, but the
   team switcher and sign-out then need a home in More.
3. **The contacts column manager is desktop-only**; adding a field at 390 has no route.
4. **The template editor cannot insert a signature on mobile** (no payload), same as
   desktop.
5. **Dark mode was rendered for two screens**, not all eleven; the screens share the
   token set and the phase-2 dark pairs, and no new colour was introduced in this phase.
6. **The connection card is 174px tall** against the board's ~130 because the signature
   control is a 44px target. If the review prefers the board, dropping the inline select
   for static text (and losing assignment at 390) would bring the card back to ~130.
7. **Renders stay in the Space** (`storage/render/`, `/tmp/p6-render.json`), as in
   phases 1–5; only the numbers above are committed.
