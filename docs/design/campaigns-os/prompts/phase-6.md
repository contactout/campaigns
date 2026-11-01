# Phase 6 — the 390 pass, and the phase-5 review carry-overs

Phase 6 of the Campaigns OS redesign. Worktree `/home/azri/projects/contactout/campaigns-design`,
branch `design/campaigns-os`. Phases 1–5 are committed and reviewed. Read
`docs/design/campaigns-os/reports/phase-5.md` and the last rounds of
`$(git rev-parse --git-dir)/review/phase-{3,4,5}.md` before starting.

## Read first

1. `docs/design/campaigns-os/spec.md` **including Amendments**. This phase settles the mobile type
   scale and adds the mobile nav to it.
2. `docs/design/campaigns-os/phases.md`.
3. Every `*-390.jpg` board in `/home/azri/projects/contactout/.design-refs/campaigns-os/`:
   `01-dashboard-390`, `02-campaigns-list-390`, `03-campaign-detail-steps-390`,
   `04-campaign-detail-recipients-390`, `05-sequence-step-editor-390`, `06-contacts-grid-390`,
   `07-templates-list-390`, `08-template-editor-390`, `09-connections-390`,
   `10-empty-no-campaigns-390`, `11-error-start-blocked-390`. The 1280 boards remain the desktop
   contract.
4. Code: the shells (`layouts/app/app-sidebar-layout.tsx`, `components/{app-sidebar,app-sidebar-header,page-head,app-content}.tsx`,
   `ui/sidebar.tsx`, `ui/sheet.tsx`), every page rebuilt in phases 1–5, and the shared primitives.

## Carry-overs from the phase-5 review (fix in this run, or state why not)

- P2-1: the sent-today meter counts surviving `campaign_emails` rows, while the cap enforces
  `sent_count`, so deleting a campaign or recipient makes an at-cap connection show room left.
  Show `sent_count` within its refresh window, or reconcile the two and record the decision.
- P2-2: the start-blocked rule has a third copy in `CampaignController` (~:606-608) and the report
  claims it has none. Unify it with the dashboard/connections mapping, and correct the report.
- P2-3: the connection `Signature` trigger's `max-w-150` overflows its 109px column into the Status
  pill. Clamp to the column.
- Remaining P2s: the editor `Delete` was removed without being listed as a deviation; guarded
  Discard returns focus to the button via Radix; form labels are 14px against the board's 12–13px;
  the meter is `role="img"` and the failure banner `role="alert"`; the report repeats a wrong claim
  that MySQL does not index `mailer_connection_id`. Also close phase 4's residual R2-2 race window
  if it is cheap (server-side status check on submit).

## Scope — the 390 layouts

For every screen above, implement the 390 board: the layout, the type scale, the row/list pattern
(tables become stacked rows or cards as the boards show), the sticky action bars, and the states
(empty, filtered-empty, blocked, selected). The shared pieces come first, then the screens:

1. **Shell.** Below `lg` there is no sidebar: the bottom tab bar (`Home / Campaigns / Contacts /
   Templates / More`, 56px, active violet icon + label) is the navigation, and the mobile header is
   the existing sheet trigger in the page head. `More` needs an actual panel — the boards do not
   show one, so give it a sheet listing the remaining destinations (Signatures, Connections,
   Settings) with the same labels and icons as the sidebar, and record the decision.
2. **Mobile type scale.** Settle it as tokens in `@theme` (the 390 boards render body larger than
   desktop 13px) and apply it through the primitives rather than per-page one-offs, so the desktop
   scale is untouched at `lg` and above.
3. **Per-screen layouts.** Follow each 390 board: campaign list rows (name, steps/recipients/updated
   meta line, pill right), detail stat grid 2×2 and the steps list, recipients list with the sticky
   `N selected / Add to campaign / ⋯` bar, step editor as a full-screen form with `Cancel`/`Save`
   and the destructive `Delete step`, contacts list with avatar initials and the sticky selection
   bar, templates list with folder groups, template editor with the chips, connections cards with
   `Sent today` as text, empty state, and the blocked-start case.
4. **Consistency.** Labels must not drift across breakpoints ("Add recipients" vs "Recipients",
   "Save template" vs "Save"): pick the board's copy per breakpoint and make the aria-labels match
   the action. Icon-only controls (`Import CSV`, `New folder`, the editor's pen/Insert) need
   `aria-label`s, and every interactive target should be at least 44px tall on mobile.
5. **No information loss.** Close the gaps noted in the spec: the template placeholders rail (chips
   only, no fallback column exists), the step editor's `Delete step`, and the destructive actions
   that were desktop-only.

## Rules

- No hex outside `resources/css/app.css`; new mobile tokens need dark pairs.
- Desktop (1280) must not regress — re-render every 1280 screen at the end of the phase and compare
  against the 1280 boards.
- Reuse the shared primitives and page components; no forked mobile page files unless a board
  genuinely differs in structure, and then keep the data logic in one place.
- Do not add migrations. Do not touch auth or settings content beyond the shell they inherit.
- Paper MCP is unavailable.

## Verification

Isolated cua Space (`cua-space-build-test`), never the host. `npm run build`, `npm run types:check`,
`npm run check`, `php artisan test`, `pint --test`, `phpstan`. Render every screen at 390 and again
at 1280; measure against both board sets and report a table: bottom-bar height and item pitch,
page-head offset, list row height, sticky bar height, stat grid gap, control heights. Take one
dark-mode 390 screenshot. Check touch target heights in the DOM, and that the `More` sheet reaches
Signatures, Connections and Settings.

## Deliver

- Commit(s) on `design/campaigns-os`.
- `docs/design/campaigns-os/reports/phase-6.md`: the mobile token values, per-screen table of board
  vs implementation measurements at 390 and the 1280 regression results, the `More` panel decision,
  every carry-over with its outcome, deviations, real check output, gaps.
- Final summary ≤20 lines: what changed, checks and results, measurements, deviations, gaps.
