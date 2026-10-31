# Phase 3 — campaign detail, recipients, step editor, blocked start

Phase 3 of the Campaigns OS redesign. Worktree `/home/azri/projects/contactout/campaigns-design`,
branch `design/campaigns-os`. Phases 1–2 are committed and have been through an independent
review and a fix round; read `docs/design/campaigns-os/reports/phase-2.md` and the review at
`$(git rev-parse --git-dir)/review/phases-1-2.md` (round 2 at the end) before starting.

## Read first

1. `docs/design/campaigns-os/spec.md` — build contract **including its Amendments section**
   (dark token values, `--ring` #6552ff waiver, 44px rows, 12px stat gap, 24px stat value,
   64px empty icon). Those amendments are decisions, not suggestions.
2. `docs/design/campaigns-os/phases.md`.
3. Boards: `03-campaign-detail-steps-a-1280.jpg`, `04-campaign-detail-recipients-a-1280.jpg`,
   `05-sequence-step-editor-a-1280.jpg`, `11-error-start-blocked-a-1280.jpg`; `03-…-b` and
   `04-…-390` are reference only (B is the rejected nav option, 390 is phase 6).
4. Code: `pages/campaigns/show.tsx`, `components/campaigns/{step-form-modal,add-recipients-modal,
   edit-campaign-modal,delete-campaign-modal,campaign-status-badge}.tsx`,
   `app/Http/Controllers/Campaigns/CampaignController.php` (especially `show()`),
   `app/Enums/{RecipientStatus,ContactStatus,CampaignStatus}.php`,
   `components/{stat-card,empty-state,page-head}.tsx` (shared primitives from the fix round),
   `resources/js/types/campaigns.ts`, `.ai/rules/*`.

## Scope

**Page shell.** `PageHead` with the back chevron, campaign name, status pill and the action
cluster (`Edit`, `Duplicate`, `Archive`, `Stop`, primary `Add recipients`), `showHeader: false`.
Tab bar `Steps | Recipients` with the 2px active underline, local state, `aria-selected` and
arrow-key support.

**Steps tab (03 A).** Four stat cards (Steps, Recipients, Sent, Opened) using the shared
`StatCard`; meta line `Sends from <connection> (<tz>)` + `Add step`; day groups (`Day 1 · 09:00`)
with step rows: drag handle, tinted mail icon, subject, `Email · New thread` subline,
right-aligned `Sent / Opened / Replied` in fixed lanes with tabular figures, `⋮` menu.
Keep the existing reorder behaviour if it exists today; do not add a DnD library. Empty-subject
steps get the red outline only in the blocked state, as on 11 A.

**Recipients tab (04 A).** Bulk bar (`N selected` + danger `Remove`), search, `Add recipients`;
table `checkbox / Name / Email / Status / ⋮` with the selected-row tint and a select-all
checkbox; pagination stays. The campaign header must not repeat `Add recipients` on this tab.
Status vocabulary: `RecipientStatus` (Active/Pending/Bounced/Completed/Error/Paused/Replied/
Unsubscribed/Archived) is what the payload carries today, and the board shows
`Contacted/Opened/Not contacted`. Decide in this phase: if contact-level or email-level state is
available cheaply, derive the board's labels; otherwise keep `RecipientStatus` labels, map them
onto the spec's badge table, and record the deviation. **No migrations.**

**Step editor (05 A).** Replace the modal-on-open flow with the board's two-pane layout on
desktop: left `SEQUENCE` list (active step tinted, `Add step`), right editor card with
`Edit step · Day N`, `Esc` hint, `Apply template`, `Subject`, the body editor and toolbar
(`B I U • 1. Link` + Placeholder + Signature), placeholder chips with fallbacks, `Day` and
`Time` fields, threaded checkbox, footer `Cancel` / `Save step`, plus a destructive `Delete step`
(the 390 board has it, the desktop board omits it — include it). Reuse the existing form logic
from `step-form-modal.tsx` rather than rewriting it; keep the modal component usable if another
page imports it.

**Blocked start (11 A).** `Start` disabled with the reasons panel listing the real blockers, the
offending step outlined, and inline `Edit step N` / `Go to Connections` actions. Derive every
reason from data that exists: empty subjects come from `steps`; the connection state needs the
campaign's connection status/exception; the "N recipients are marked Do not contact" line needs a
contact-status count. Read-only aggregate queries in `show()` are allowed (no migrations, no new
tables) — paste each in the report. If a reason cannot be derived from real data, leave it out
and list it as a gap; never render a hardcoded reason. `Start` must stay enabled when nothing
blocks.

## Rules

- No hex outside `resources/css/app.css`; semantic tokens only. Dark values exist for the new
  tokens — do not add light-only tokens without a dark pair.
- No migrations, no new tables. Read-only aggregates are allowed and must be documented.
- Keep routes, props, middleware, Inertia conventions and permission checks. `can.*` flags stay
  authoritative for the action cluster.
- Reuse `StatCard`, `EmptyState`, `PageHead`, `Badge`, `Table`; do not inline a second copy.
- Do not touch the campaigns list, dashboard, contacts, templates or connections screens except
  through the shared primitives. Do not change the `Created …` subline decision.
- Paper MCP is unavailable; work from the boards and the spec.

## Verification

Isolated cua Space (`cua-space-build-test` skill), never the host. `npm run build`,
`npm run types:check`, `npm run check`, `php artisan test`, `pint --test`, `phpstan`. Render at
1280 and **measure** at least these against the board, reporting board vs implementation
coordinates: tab-bar height, stat-card gap and value size, step-row pitch, the x of the
`Sent/Opened/Replied` lanes, recipients table column x positions, editor pane width. Take one
dark-mode screenshot of the detail page. If a board and the spec disagree, the board wins and the
report says so.

## Deliver

- Commit(s) on `design/campaigns-os`.
- `docs/design/campaigns-os/reports/phase-3.md`: files changed, each query added and its cost,
  the measured coordinate table, the vocabulary decision, deviations, real check output, gaps.
- Final summary ≤20 lines: what changed, checks and their results, measurements, deviations, gaps.
