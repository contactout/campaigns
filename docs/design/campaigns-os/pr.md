# Campaigns OS — the redesign branch

`design/campaigns-os` rebuilds the product's six screens — dashboard, campaigns, campaign
detail, contacts, templates and connections — to the 26 artboards in the Campaigns OS design
file (`00`–`11`, each with a 1280 desktop board and a 390 mobile board, plus a second
desktop variant for `02` and `03`), and adds the
390 shell they assume: a five-slot bottom tab bar with a More sheet, no sidebar below 1024px.

It is a rewrite of the presentation layer plus the read paths those screens need. The
routes, models and write actions are the existing ones; the changes to `app/` are new
aggregate queries, four payload groups, one shared status derivation and one optional
request flag (below). No migrations, no new dependencies and no schema change; it also removes
four unused dependencies (below).

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
- **Contacts.** The board's grid: 25 rows a page, 33px rows at 1280, a scrolling grid whose
  fixed and custom-column header cells stay sticky over the rows, with the list chips and
  pagination fixed below it; a mobile card list with 44px selection targets and a sticky
  selection bar.
- **Templates.** Folder groups, 39px rows at 1280 and 55px rows with an `Updated …` subline at
  390, and a template editor with a rich-text body, placeholder chips that keep their stored
  fallback visible, and a discard guard that clears once a save or a discard has been confirmed.
  The guard is shared with the step editor and covers every Inertia visit, not only the
  editor's own Cancel and back link: see "Unsaved drafts" below.
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

## Unsaved drafts

Two editors hold work no server has seen: the template body and a campaign step. One shared hook
(`resources/js/hooks/use-unsaved-changes-guard.ts`, used by `pages/templates/show.tsx` and
`components/campaigns/sequence-editor.tsx`) guards every way that work can leave the page:

- **Every Inertia visit** — a tab-bar item, a sidebar or More-sheet link, a head action that
  redirects (`Duplicate`, `Archive`, `Stop`), the back chevron, the editor's own Cancel and Esc —
  is cancelled at `inertia:before` and replayed once the user confirms, **with the visit's own
  callbacks**, so a save that fails still renders its errors and one that succeeds still refreshes
  the dirty baseline and closes the step editor.
- **Browser Back** cannot be cancelled by that event: Inertia's `popstate` handler restores the
  previous entry with `preserveState: false`, which remounts the page and takes the draft with it.
  A capture-phase listener with `stopImmediatePropagation()` was tried and, in the Chromium build
  used for the proofs, the page still swapped. What is used instead is the one branch Inertia does
  not swap on: an entry whose history state is `null` only has its URL restored. So while the
  draft is dirty the current entry is emptied and a copy is pushed above it; Back lands on the
  emptied entry, the page stays mounted, and the dialog asks. The trap is re-armed as soon as it
  fires, so a second Back — or a Back with the dialog open, which is how Android dismisses one —
  asks again instead of leaving, and the hook writes the page's state back into the emptied entry
  itself rather than trusting Inertia's repair (its `History.replaceState` skips a repair equal to
  its current state, which would leave a `null` entry behind after a Back/Cancel/leave sequence).
- **A closed tab or a hard reload** keeps the browser's own `beforeunload` prompt, which no dialog
  can replace.

Four things are deliberately let through, because they reload the same page and keep the draft:
the editors' own saves, the placeholder rail's add/remove forms, and the step endpoints
(`/campaigns/{id}/steps…`, including reorder and delete). Each of those takes the trap off first,
so a save cannot leave the emptied entry behind. Proved by click-through at 1280 and 390
(`reports/deep-review/`, `round2-*.png`): a dirty edit then a tab-bar tap or Back raises the
dialog; Cancel keeps the draft and stays; Confirm leaves and the draft goes; two Backs in a row
both ask; a Back with the dialog open cancels it; Back → Cancel → leave → Back from the next page
lands on a real page; a draft typed back to clean after a scroll leaves in one press; a save that
fails validation shows its error and keeps the draft; a save that succeeds leaves without a
prompt; and hovering a nav link raises nothing.

Not guarded: a history jump of two or more entries (the long-press history menu) crosses the trap
in one step, and Forward after a cancelled Back reaches the hard-reset entry before it reaches the
page it skipped.

## Deviations and accessibility waivers

Each has a number behind it in the phase reports. The short list:

1. **44px controls at 390, not the boards' 28–32px.** Buttons, inputs, select triggers, the
   nine native `<select>`s across six files (`components/native-select.tsx`, 44px and 16px text below `lg`, so iOS
   does not zoom on focus), campaign tabs and list chips get `max-lg:min-h-11`; the brief's
   touch-target rule outranks the board. Cost: taller heads and cards (connection card 174 vs
   ≈130; head rows 44 vs 28).
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
    Cancel/Close are present, the step editor hides it, and Radix also closes on an outside
    pointer-down, so it is not the only pointer route — the waiver rests on the undersized
    target itself, not on reachability.

## Backend and data changes, with costs

All plans are SQLite `EXPLAIN QUERY PLAN` from the Space; MySQL may choose differently. No
migration is included, so nothing here adds an index.

- **Dashboard aggregates** (`DashboardController`, 9 extra queries on top of the invitation
  query and three `exists()` setup queries, not 8: `can.create` is `CampaignPolicy::create` →
  `belongsToTeam`, a query of its own): one statement computes both 30-day windows for
  sent/open/reply and their deltas, so the three delta chips add zero queries; two campaign
  counts by status; four covering counts (contacts, lists, templates, folders); one connections
  read that also supplies the row and "needs attention" counts. The email statement is the
  expensive one: `campaign_emails` has no index on `dispatched_at`, so its date filter is
  applied per row and cost scales with the team's total sent mail. Nothing is cached.
- **Campaign detail adds four queries** to `show()`: per-step metrics (grouped over the
  campaign's emails; no `(campaign_id, campaign_step_id)` index, so a temp B-tree), recipient
  engagement for the 25 rows on the page, a do-not-contact recipient count, and the eager load
  of the campaign's mailer connection. A recipients search adds **no** query of its own: it
  narrows the message paginator's query, and runs the whole `show()` again as a new visit.
  Measured total: **39 queries** with a connection and
  one page of three recipients, **34** with neither a connection nor recipients.
- **Connections cap counter** reads `sent_count` inside the same UTC-day window that
  `SendEmail` resets it in, rather than the old `sentTodayByConnection()` aggregate. The
  aggregate cost **8.81 ms at 50k `campaign_emails` rows** with no useful index, **1.53 ms**
  with a covering `(mailer_connection_id, dispatched_at)` index that is *not* added here; with
  no connections the query is skipped.
- **One status derivation** (`app/Enums/MailerConnectionDisplayStatus.php`, new): the
  connection pill, the campaign start-block rule and `needsAttention()` all read
  `displayStatus()`. `has_exception` left the payload, its type and the test. Two payload
  builders still ship the *stored* `status`/`status_label` alongside it —
  `CampaignController::connectionSummary()` (the campaign detail's `connection`) and
  `optionPayload()` (the campaign dialogs' connection list). Nothing renders either today, so
  the screen has one derivation, but the payload carries two: dropping the stored fields is
  recorded as a follow-up below rather than done here.
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
  it here). The request also authorises the bound campaign against the current team and answers
  `404` for a foreign one, so the flag can no longer be used to read another team's campaign
  *state* (a running campaign used to answer `302` with an error, a stopped one `404`).
- **Tenancy hardening on the read paths:** the campaign detail's per-page contact lookup is now
  scoped to the team (`forTeam()`), so a recipient row pointing at another team's contact
  renders as a deleted contact instead of leaking its name and address; and the stored
  `last_error_message` on a connection is capped at 300 characters and cut at the provider
  echo (`… response: {json}`) before it reaches every team member. Every member seeing it is the
  policy's intent (`MailerConnectionPolicy::update` is `belongsToTeam`), so the field is trimmed
  rather than gated.
- **Not done server-side:** `CampaignController::start`/`StartCampaign` still do not check the
  start-block rules themselves. The disabled Start and the panel are UI-only. Recorded as a
  gap.

## Sweep

The branch also deletes thirteen unreachable files left by the redesign and the starter — the old
step-form modal, the header component, its layout and its navigation-menu primitive, the
standalone pagination, the nav footer, two unused auth layouts, and the collapsible, icon,
placeholder-pattern, toggle-group and toggle primitives — and the four Radix packages that
would otherwise stay in `package.json` with no consumer (`react-toggle`, `react-toggle-group`,
`react-collapsible`, `react-navigation-menu`). Each file was grepped for importers first; the lockfile is regenerated
and a clean `npm ci` installs 321 packages.

## What was verified, and how

In an isolated cua Space (`local:devtest`), one command at a time. The six checks ran on
`dc6dfbf` (pre-review); after the review fixes the affected checks ran again on `858a9e0`, and
the whole set again on a clean `git archive` of the deep-review head (`756ad56`, 0 dirty
lines), one command at a time:

| Check | Result |
| --- | --- |
| `npm run build` | exit 0, `✓ built in 3.2s` |
| `npm run types:check` | exit 0, `tsc --noEmit` clean |
| `npm run check` | exit 0, **155 files** formatted, 138 lint files, no warnings |
| `php artisan test` | exit 0, **603 passed** (2,927 assertions) in 8.3s |
| `pint --test` | exit 0, **367 files** PASS |
| `phpstan analyse` (level 7) | exit 0, `[OK] No errors` |

On `858a9e0`, after a clean `npm ci`: build exit 0 (3.41s), `tsc` clean, `vp check` 151/136,
and `php artisan test tests/Feature/Templates tests/Feature/Contacts` **138 passed** (573
assertions). The full suite is unchanged by that commit (no PHP file was touched).

The fixes were then proved with live renders (Chromium 156, headless, CDP,
1280×900 and 390×844, real mouse and key events) and measured, not eyeballed. The nine items
and their numbers are in `reports/phase-7.md`; the screenshots and the DOM readings
(`evidence.json`) are in `reports/phase-7/`. The short version: the contacts grid scrolls to `scrollTop 125 = max`, the
last row ends at y 864 and the pagination row starts at 868; template names start at x 285
beside an icon at 257; one tap on the threaded label toggles exactly once at both breakpoints;
the 390 editor is `role=dialog`, `aria-modal=true`, 14 consecutive Tab presses stayed
inside it, it locks body scroll and closes on Escape with no edits while still guarding a
real edit; both 390 selection bars
sit above the pagination row; the signature value and header text both start at x 847; the
More tab and the sheet's Settings row are current at `/settings/security`; an emulated 34px
safe-area inset is carried by the tab bar's padding and the sticky bar sits above it; and
the contacts screen renders in dark at 390.

## Remaining gaps

The full list with numbers is in `reports/phase-6.md`, `reports/phase-7.md` and the
deep-review round there. The ones a reviewer should weigh:

- **Start blockers are UI-only** (server-side check missing).
- **The 390 step editor remounts across the 1024px line**, so an unsaved step edit is lost
  silently when the window is resized — and every common tablet rotation crosses it
  (820↔1180, 834↔1194), so this is a real path, not an edge case. The navigation guard does not
  help: a resize is not a visit, and the remounted form has no draft to restore. Fixing it means
  lifting the form state above the breakpoint branch (follow-up R1).
- **Two Back-shaped holes remain, both documented above and in the hook:** a history jump of two
  or more entries crosses the trap in one step (never trapped, even on the first press), and
  Forward after a cancelled Back reaches the emptied entry before the page it skipped. The
  two-or-more jump is the one path where Back can still discard a draft without asking — a trap
  cannot see it. A single Back, the press a user normally makes, is trapped, re-armed after every
  press and proved clean in the paths listed in the "Unsaved drafts" section.
- **No JS tests.** The branch ships no runner, so the guard, the bulk loop and the selection
  pruning rest on click-throughs. `vitest` + React Testing Library is a follow-up, not a
  dependency this branch adds.

### Deferred, with the review's numbers

- **A contacts bulk delete keeps running after you navigate away (P2-5).** Each step is
  `async: true` so it cannot be cancelled, and each answers with a redirect to the contacts
  index, so a user who starts a 25-row delete and opens Templates can be swapped back by the
  next completed step. While the loop runs the page is not "dirty", so the draft guard does not
  cover it; the fix is the server-side endpoint in R2.
- **The bulk loop sends no partial headers (P2-6).** `only: []` is treated as a full visit, so
  each of the N steps re-runs the whole contacts `index()`, including the `latest()` filesort
  over the team's contacts. `only: ['flash']` would make them partial; R2 removes the loop.
- **The Recipients tab reloads the whole campaign payload (P2-7), its search is two correlated
  scans (P2-8), and the do-not-contact count re-joins every recipient (P2-10).** A search or a
  page change re-runs all 39 queries of `show()`, five of them full scans of `campaign_emails`
  and one a 50k-row join for 50k-recipient campaigns. The search is also not escaped, so `_`
  matches every recipient. `only: ['recipients', 'recipientsSearch']`, `Inertia::optional()` for
  the dialog payloads, an escaped `LIKE`, a join instead of a correlated `EXISTS`, and computing
  the count only for Draft/Stopped campaigns are the fixes; all are deferred.
- **No index for the new aggregates** (P2-9): `(campaign_id, dispatched_at, opened_at,
  replied_at)` turns the dashboard's 30-day statement into an index-only range scan — measured
  **8.81 ms at 50k `campaign_emails` rows without it, 1.53 ms with it**, and it also runs on
  hover because the nav and tab bar prefetch Dashboard. A follow-up migration; this branch
  ships none.
- **A pre-existing recipients existence oracle survives** (P2-1's older sibling):
  `BulkDestroyCampaignRecipientsRequest` validates `recipients.*` against the unscoped route
  binding, so a member can learn whether a given recipient id belongs to another team's
  campaign. It writes nothing and 404s at the controller, and the `idle_only` oracle that did
  leak campaign *state* is closed on this branch.
- **The 16×16 sheet close** (waiver above).
- **The page-head sheet trigger on mobile** (deviation 2).
- **The contacts column manager is desktop-only**, so adding a field at 390 has no route.
- **No recipients status filter:** board 04's funnel has no payload or route behind it.
- **No signature action in the template editor** (the template payload carries no signatures).
- **Dark mode has been rendered on a subset of screens** (dashboard, campaigns, contacts), not
  all eleven; the token set and the phase-2 dark pairs are shared.
- **Board-level differences kept on purpose:** 390 row pitches are 1px over the boards
  (contacts/recipients 57 vs 56, campaign 69 vs 68), the step editor body scrolls at 390, and
  the bottom bar is 57px rather than the board's 64px (which includes an 8px home indicator).
  Campaign detail (board 11) keeps its stat cards and `Sends from …` row between the blocked
  panel and Day 1 (**+167px** against the board), its head carries `+ Add recipients` beside
  `⋯` (≈200px wider than the board's four buttons), Start is faded while blocked, and the
  step-row mail tile is 22×22 against the board's 30×30.
- **Data states:** the fixture's numbers differ from the boards' (1,105 sent vs 2,418, five
  do-not-contact contacts on the first page), so shapes match but figures do not. The empty
  (10) board is rendered from Quiet Studio's team, which has no campaigns; the blocked (11)
  board is a Draft campaign with an empty subject and a connection that failed verification.

## Follow-ups this branch deliberately does not do

- **R1 — one editor-state and guard layer.** Lift `StepForm`'s state into a `useStepDraft(step)`
  hook above the `isMobile` branch, so the 390 dialog and the 1280 pane share one draft; it fixes
  the 1024 remount (and would let the guard cover a resize).
- **R2 — server-side bulk endpoints and partial reloads.** `contacts/bulk-destroy` and
  `contacts/bulk-status` (one validated id array, one transaction, one flash) delete 150 lines of
  client orchestration and remove P2-5 and P2-6; `only` + `Inertia::optional()` on the campaign
  detail removes P2-7 and P2-10.
- **R3 — stop editing shadcn in place.** Move the product variants out of `components/ui/*` into
  wrappers (as `NativeSelect` already does), and make the row pitches, column widths and the 1024
  line `@theme` tokens so the next shadcn upgrade is a no-op.
- **Drop the stored status from the two connection payloads** (`connectionSummary()`,
  `optionPayload()`), leaving `display_status` as the only status a client can read.
- **Two guard follow-ups from review round 3, deliberately not applied** because the review had
  reached its round cap: `pageStateRef` is captured only when a page first goes dirty, so a repair
  after a placeholder-rail reload can write a stale snapshot (`rememberPageState()` belongs in
  `rearm()`); and the confirmed-leave action stopped clearing `dirtyRef.current`, so the finish
  step re-arms on the destination entry — Inertia's repair rescues it today, but the line should
  come back.

This branch is pushed as `design/campaigns-os`; the text above is the body of PR #52.
