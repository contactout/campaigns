# Phase 2 — Dashboard

Phase 2 of the Campaigns OS redesign. Worktree `/home/azri/projects/contactout/campaigns-design`,
branch `design/campaigns-os`. Phase 1 is committed (`6a626e3`, `7de7117`, `72723c0`); read its
report at `docs/design/campaigns-os/reports/phase-1.md` before starting.

## Read first

1. `docs/design/campaigns-os/spec.md` — build contract (tokens, type scale, shell, badge rules).
2. `docs/design/campaigns-os/phases.md` — phase boundaries and per-phase rules.
3. `/home/azri/projects/contactout/.design-refs/campaigns-os/01-dashboard-a-1280.jpg` — the board.
   `02-campaigns-list-a-1280.jpg` for how the shell and page head landed in phase 1.
4. `app/Http/Controllers/DashboardController.php`, `resources/js/pages/dashboard.tsx`,
   `resources/js/components/team-invitation-alert.tsx`,
   `resources/js/components/pending-invitations-modal.tsx`,
   `resources/js/components/page-head.tsx`, `resources/js/types/teams.ts`
   (`DashboardSetup` lives there, not in a dashboard types file).

## Scope — board 01 A only

- **Page head.** Reuse `PageHead`, container `px-4 py-6 lg:px-6`, `showHeader: false` so the
  breadcrumb bar goes away as on the board. Greeting is the H1 (time-of-day + first name from
  `auth.user.name`), subtitle is team name + today's date. No second heading component.
- **Invitation banner.** Restyle the existing `team-invitation-alert.tsx` onto the `--banner`
  token with the `Review` action that opens the existing `PendingInvitationsModal`. Do not build a
  parallel component; the banner renders only when `pendingInvitations.length > 0`.
- **Four stat cards.** Emails sent (30d), Open rate, Reply rate, Active campaigns. Label 12px
  muted, value 20px/700, delta chip only where the number is real (see data rules).
- **Finish setting up.** Driven by the existing `setup` booleans: three rows, done/undone icons,
  a progress bar = done/3, and the secondary CTA on the pending row only (the board shows
  `Go to Campaigns` on “Create a campaign”). Keep each row's existing destination.
- **Jump back in.** Four rows with a 32px tinted icon tile, title, one qualifier line, chevron:
  Contacts, Campaigns, Templates, Connections. Links use the existing route helpers.

Out of scope: campaign detail, contacts, templates, connections, 390 layout, dark mode, settings
and auth restyling, the campaigns list beyond review follow-ups below.

## Data rules — do not fabricate numbers

`DashboardController` currently returns only `pendingInvitations` and `setup[has_connection,
has_contact, has_campaign]`. The board's metrics and counts do not exist yet.

- Add the aggregates the cards and rows need in `DashboardController` — **count queries only, no
  migrations, no new tables**, scoped with the existing `forTeam(...)` scopes and
  `campaign_emails.dispatched_at / opened_at / replied_at` where a rate is needed. Paste each
  query in the report and say what it costs.
- A delta chip (`+12%`, `+3.1pt`) requires a previous-window comparison. Compute it only when that
  is one extra query per metric; otherwise render no chip and list it as a gap under open
  questions. A missing number is a gap; an invented one is a defect.
- If a row's qualifier (e.g. `14 templates · 4 folders`, `3 inboxes · 1 needs attention`) needs a
  join or a new column, either a simple `withCount`/`count()` or drop that half of the line and
  report it. Never hardcode demo copy.
- Keep the payload typed in `resources/js/types/teams.ts` (with `DashboardSetup`) and documented
  in the controller.

## Review follow-ups from phase 1 (from the branch review, not the agent's own report)

1. **Button blast radius.** Phase 1 shrank the shared `Button` (default 36→32px, `text-sm`→13px,
   `rounded-md`→`rounded-lg`) and the input/select/badge primitives, which also restyles auth and
   settings pages that were out of scope. In the Space, render `/login` and `/settings/profile` at
   1280 as well as the dashboard, compare them against nothing but sanity (no overlap, no
   cramped hit areas, `size="lg"` still reads as a primary action) and report what you saw.
2. **Campaigns search state.** `pages/campaigns/index.tsx` keeps `search` in local state while
   `filters.q` comes from the server, so after changing the status filter the input can show text
   that is not applied. If it is a ≤5-line fix (sync on `filters.q` change), make it; otherwise
   report and leave it for phase 6.
3. `lib/relative-time.ts` hardcodes `en-US`; check call sites before changing anything, and leave
   it alone unless the dashboard needs it.
4. Do not introduce a second type-scale convention: `text-[20px]`/`text-[13px]` one-offs are what
   phase 1 used, `PageHead` owns the page head.

## Constraints

- Paper MCP is rate-limited; do not call `mcp__paper__*`. Work from the screenshot and the spec.
- No hex outside `resources/css/app.css`. Use the tokens; `--banner` and `--success` are already
  defined and currently unused — this phase consumes them.
- Keep routes, Inertia props, middleware and form conventions. No migrations.
- `.dark` stays untouched.

## Verification

Follow `/home/azri/.pi/agent/skills/cua-space-build-test/SKILL.md`: isolated cua Space, worktree
copied in, installs there. Run `npm run build`, `npm run types:check`, `npm run check`. Render the
dashboard at 1280 against `01-dashboard-a-1280.jpg` plus `/login` and `/settings/profile` as above.
No host installs, builds or servers. If the Space cannot be provisioned, stop and report the
blocker with the error text.

## Deliver

- Commit(s) on `design/campaigns-os`, conventional messages.
- `docs/design/campaigns-os/reports/phase-2.md`: files changed, every controller query added and
  its cost, board requirements vs what shipped, deviations, real verification output, gaps.
- Final summary of at most 20 lines: what changed, checks run and results, deviations, gaps.
