# Campaigns OS — the redesign branch

`design/campaigns-os` rebuilds the product's six screens — dashboard, campaigns, campaign
detail, contacts, templates and connections — to the 26 artboards in the Campaigns OS design
file (`00`–`11`, each with a 1280 desktop board and a 390 mobile board, plus a second
desktop variant for `02` and `03`), and adds the
390 shell they assume: a five-slot bottom tab bar with a More sheet, no sidebar below 1024px.

It is a rewrite of the presentation layer plus the read paths those screens need. The
routes, models and write actions are the existing ones; the changes to `app/` are new
aggregate queries, four payload groups, one shared status derivation and one optional
request flag (below). No migrations, no new dependencies, no schema change.

Read it as: the boards are the visual contract, the phase reports in
`docs/design/campaigns-os/reports/` are the measurement record, and this file is the map for
a reviewer who has not seen either.

## What the branch does, by area

- **Shell.** A static sidebar at `lg` (240px, `svh` height, one nav list shared with the
  mobile bar through `use-nav-items.ts`), a fixed 56px + 1px bottom tab bar below `lg`, and a
  More bottom sheet holding Signatures, Connections and Settings. The sidebar trigger stays in
  the 390 page head (see deviations). Settings is current on every `/settings/*` route, and the
  More slot is active there too.
- **Dashboard.** Four stat cards with 30-day deltas, a setup checklist, a "Jump back in" list
  and the pending-invitation banner. The aggregates are computed in `DashboardController`.
- **Campaigns.** Table at 1280, cards at 390, status pills on both; the row pitch is the
  board's 44px at 1280.
- **Campaign detail.** Steps and Recipients tabs, a start-blocked panel that lists every
  reason, and per-step sent/opened/replied metrics. The step editor is a two-pane inline
  editor at `lg` and a full-screen modal dialog below it.
- **Contacts.** The board's grid: 25 rows a page, 33px rows at 1280, a scrolling grid with the
  list chips and pagination fixed below it, a mobile card list with 44px selection targets and
  a sticky selection bar.
- **Templates.** Folder groups, 39px rows at 1280 and 55px rows with an `Updated …` subline at
  390, and a template editor with a rich-text body, placeholder chips that keep their stored
  fallback visible, and a guarded discard.
- **Connections.** Cards at 390 and a table at 1280, derived status pills, a `Sent today /
  limit` counter, the signature clamp and a signature select aligned to its column.

## Boards → files

| Board | Screen | 1280 | 390 | Files |
| --- | --- | --- | --- | --- |
| 00 | Navigation, sidebar/tab bar, tokens | ✓ | ✓ | `components/app-sidebar.tsx`, `mobile-tab-bar.tsx`, `nav-main.tsx`, `nav-user.tsx`, `team-switcher.tsx`, `hooks/use-nav-items.ts`, `layouts/app/app-sidebar-layout.tsx`, `resources/css/app.css` |
| 01 | Dashboard | ✓ | ✓ | `pages/dashboard.tsx`, `components/dashboard/stat-card.tsx`, `team-invitation-alert.tsx`, `app/Http/Controllers/DashboardController.php` |
| 02 | Campaigns list | ✓ (`a`, `b`) | ✓ | `pages/campaigns/index.tsx`, `components/campaigns/campaign-status-badge.tsx`, `create-campaign-modal.tsx`, `delete-campaign-modal.tsx`, `edit-campaign-modal.tsx` |
| 03 | Campaign detail · Steps | ✓ (`a`, `b`) | ✓ | `pages/campaigns/show.tsx`, `components/campaigns/campaign-steps-tab.tsx`, `start-blocked-panel.tsx`, `delete-campaign-modal.tsx` |
| 04 | Campaign detail · Recipients | ✓ | ✓ | `components/campaigns/campaign-recipients-tab.tsx`, `add-recipients-modal.tsx`, `recipient-status-badge.tsx` |
| 05 | Sequence step editor | ✓ | ✓ | `components/campaigns/sequence-editor.tsx`, `step-form.tsx`, `components/templates/rich-text-editor.tsx`, `components/ui/dialog.tsx`, `ui/sheet.tsx` |
| 06 | Contacts grid + lists | ✓ | ✓ | `pages/contacts/index.tsx`, `components/contacts/contact-grid.tsx`, `add-to-campaign-modal.tsx`, `create-contact-modal.tsx`, `import-contacts-modal.tsx`, `list-tabs.tsx` |
| 07 | Templates list | ✓ | ✓ | `pages/templates/index.tsx`, `components/templates/folder-form-modal.tsx`, `delete-folder-modal.tsx` |
| 08 | Template editor | ✓ | ✓ | `pages/templates/show.tsx`, `components/templates/placeholder-manager.tsx`, `delete-template-modal.tsx`, `rich-text-editor.tsx` |
| 09 | Connections | ✓ | ✓ | `pages/mailer-connections/index.tsx`, `components/mailer-connections/cap-meter.tsx`, `signature-select.tsx`, `status-badge.tsx`, `connection-form-modal.tsx` |
| 10 | Empty · no campaigns | ✓ | ✓ | `pages/campaigns/index.tsx` (empty state), `components/empty-state.tsx` |
| 11 | Error · start blocked | ✓ | ✓ | `pages/campaigns/show.tsx`, `components/campaigns/start-blocked-panel.tsx`, `CampaignController::startState()` |
| — | Shared | — | — | `components/page-head.tsx`, `form-label.tsx`, `confirm-dialog.tsx`, `empty-state.tsx`, `ui/table.tsx`, `ui/button.tsx`, `ui/input.tsx`, `ui/select.tsx`, `ui/dialog.tsx`, `ui/sheet.tsx` |

Every board exists at both breakpoints; `02` and `03` have two desktop variants, and the
branch follows variant `a`, the variant every phase report measures against.

## Deviations and accessibility waivers

Each has a number behind it in the phase reports. The short list:

1. **44px controls at 390, not the boards' 28–32px.** Buttons, inputs, select triggers,
   campaign tabs and list chips get `max-lg:min-h-11`; the brief's touch-target rule outranks
   the board. Cost: taller heads and cards (connection card 174 vs ≈130; head rows 44 vs 28).
2. **The page-head sheet trigger shifts the 390 title to x 44.** It is the only route to the
   team switcher, user menu and sign-out. Accepted for this branch and recorded as a gap: the
   next pass should move those into the More sheet or give the trigger an avatar glyph.
3. **`+ Recipients` stays on a Draft campaign's head** (board 11 shows only Edit, ⋯ and
   Start) so recipients can still be added at 390, where the recipients toolbar's add button
   is hidden.
4. **Recipients sticky bar is `N selected | Remove`** (board 04), not the contacts bar's
   `Add to campaign` (board 06). The two bars are deliberately different.
5. **Placeholder fallbacks stay visible beside their chips at 390** — the brief's
   no-information-loss rule outranks the board's chips-only treatment. The chip is 44px tall
   rather than the board's 22px.
6. **A real `Sent today` in the connections cards and an inline signature select**,
   without the meter bar at 390, rather than the board's static `Signature: None`.
7. **Connections banner shows the stored error in full** (up to four lines, measured 102px)
   rather than the board's single truncated line.
8. **The campaign row subline reads `Created …` where the board reads `Updated …`** at both
   breakpoints: the index payload carries `created_at`, not `updated_at` (a phase-1 decision).
   The relative format matches.
9. **Dark-mode tokens are additive.** Light-only board colours that failed contrast were
   replaced and measured: destructive focus ring 2px `#6552ff` **4.99:1** (board `#b8afff`
   **1.98:1**, halo **1.16:1**); `--primary-border` **6.10:1** in dark; success **11.36:1**;
   banner **9.69:1**; warning **9.43:1**; danger **8.47:1**; neutral **10.24:1**. Recorded as an
   accessibility waiver in the spec's Amendments.
10. **The shared Sheet/Dialog close is a bare 16×16 ✕** in an unpadded `Primitive.Close`,
    below the 24×24 WCAG 2.5.8 minimum. Left as-is per the brief and recorded: Escape and
    Cancel/Close are present, and the step editor hides it, but it is the only pointer route out
    of a sheet for a user who cannot see the small target.

## Backend and data changes, with costs

All plans are SQLite `EXPLAIN QUERY PLAN` from the Space; MySQL may choose differently. No
migration is included, so nothing here adds an index.

- **Dashboard aggregates** (`DashboardController`, 8 extra queries on top of the invitation
  query and three `exists()` setup queries): one statement computes both 30-day windows for
  sent/open/reply and their deltas, so the three delta chips add zero queries; two campaign
  counts by status; four covering counts (contacts, lists, templates, folders); one connections
  read that also supplies the row and "needs attention" counts. The email statement is the
  expensive one: `campaign_emails` has no index on `dispatched_at`, so its date filter is
  applied per row and cost scales with the team's total sent mail. Nothing is cached.
- **Campaign detail adds four queries** to `show()`: per-step metrics (grouped over the
  campaign's emails; no `(campaign_id, campaign_step_id)` index, so a temp B-tree), recipient
  engagement for the 25 rows on the page, a do-not-contact recipient count, and the recipients
  search only when `recipients_q` is set. Measured total: **39 queries** with a connection and
  one page of three recipients, **34** with neither a connection nor recipients.
- **Connections cap counter** reads `sent_count` inside the same UTC-day window that
  `SendEmail` resets it in, rather than the old `sentTodayByConnection()` aggregate. The
  aggregate cost **8.81 ms at 50k `campaign_emails` rows** with no useful index, **1.53 ms**
  with a covering `(mailer_connection_id, dispatched_at)` index that is *not* added here; with
  no connections the query is skipped.
- **One status derivation** (`app/Enums/MailerConnectionDisplayStatus.php`, new): the
  connection pill, the campaign start-block rule and `needsAttention()` all read
  `displayStatus()`. `has_exception` left the payload, its type and the test.
- **Payload additions** (read-only, no write path changed): contacts index gains a `campaigns`
  array of the team's Draft/Stopped campaigns for "Add to campaign" (archived and running
  campaigns are excluded because adding to an Active campaign queues its first step
  immediately); campaign detail gains `steps[].metrics` and per-recipient `display_status`/
  `display_status_label` (the do-not-contact count reaches the start panel through
  `start.reasons`); connections gain
  `display_status`/`display_status_label` and a `sent_today`/limit pair.
- **One write-path addition:** `StoreCampaignRecipientsRequest` accepts an optional
  `idle_only` flag, validated in the request's `after` hook against the bound campaign's
  current status, and the Add-to-campaign modal sends it. This closes the "add to a campaign
  that started while the dialog was open" race to the validation window; a few milliseconds
  between validation and insert remain (a `lockForUpdate` would close them and is not worth
  it here).
- **Not done server-side:** `CampaignController::start`/`StartCampaign` still do not check the
  start-block rules themselves. The disabled Start and the panel are UI-only. Recorded as a
  gap.

## What was verified, and how

On the final commit `dc6dfbf`, in an isolated cua Space (`local:devtest`), one command at a
time:

| Check | Result |
| --- | --- |
| `npm run build` | exit 0, `✓ built in 2.95s` |
| `npm run types:check` | exit 0, `tsc --noEmit` clean |
| `npm run check` | exit 0, **151 files** formatted, 136 lint files, no warnings |
| `php artisan test` | exit 0, **577 passed** (2,568 assertions) in 8.84s |
| `pint --test` | exit 0, **367 files** PASS |
| `phpstan analyse` (level 7) | exit 0, `[OK] No errors` |

The six phase-6 fixes were then proved with live renders (Chromium 156, headless, CDP,
1280×900 and 390×844, real mouse and key events) and measured, not eyeballed. The nine items
and their numbers are in `reports/phase-7.md`; the screenshots are in
`reports/phase-7/`. The short version: the contacts grid scrolls to `scrollTop 125 = max`, the
last row ends at y 864 and the pagination row starts at 868; template names start at x 285
beside an icon at 257; one tap on the threaded label toggles exactly once at both breakpoints;
the 390 editor is `role=dialog`, `aria-modal=true`, 14 consecutive Tab presses stayed
inside it, it locks body scroll and closes on Escape with no edits while still guarding a
real edit; both 390 selection bars
sit above the pagination row; the signature value and header text both start at x 847; the
More tab and the sheet's Settings row are current at `/settings/security`; an emulated 34px
safe-area inset is carried by the tab bar's padding and the sticky bar sits above it; and a
dark 390 render of the contacts screen.

## Remaining gaps

The full list with numbers is in `reports/phase-6.md` and `reports/phase-7.md`. The ones a
reviewer should weigh:

- **Start blockers are UI-only** (server-side check missing).
- **The 390 step editor remounts across the 1024px line**, so an unsaved edit is lost if the
  window is resized while the editor is open. Guarding it means lifting the form state above
  the breakpoint branch; recorded as a trade-off, not fixed.
- **The 16×16 sheet close** (waiver above).
- **The page-head sheet trigger on mobile** (deviation 2).
- **No index for the new aggregates** (`dispatched_at`, `(mailer_connection_id,
  dispatched_at)`, `(campaign_id, campaign_step_id)`); costs above, a follow-up migration.
- **Board-level differences kept on purpose:** 390 row pitches are 1px over the boards
  (contacts/recipients 57 vs 56, campaign 69 vs 68), the step editor body scrolls at 390, and
  the bottom bar is 57px rather than the board's 64px (which includes an 8px home indicator).
- **Data states:** the fixture's numbers differ from the boards' (1,105 sent vs 2,418, five
  do-not-contact contacts on the first page), so shapes match but figures do not. The empty
  (10) board is rendered from Quiet Studio's team, which has no campaigns; the blocked (11)
  board is a Draft campaign with an empty subject and a connection that failed verification.

The PR is not opened, pushed or created — this file is the description only.
