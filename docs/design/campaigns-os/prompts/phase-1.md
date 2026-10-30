# Phase 1 — tokens, primitives, shell, campaigns list

You are implementing **phase 1 only** of the Campaigns OS redesign in this worktree:
`/home/azri/projects/contactout/campaigns-design` (branch `design/campaigns-os`, a Laravel
+ Inertia/React campaign app).

## Read first, in this order

1. `docs/design/campaigns-os/spec.md` — the build contract. Follow it exactly.
2. `docs/design/campaigns-os/phases.md` — phase boundaries and the rules that apply to
   every phase. Phase 1 is the only phase in scope today.
3. Screenshots in `/home/azri/projects/contactout/.design-refs/campaigns-os/`:
   `00-token-board`, `00b-navigation-a-vs-b`, `02-campaigns-list-a-1280`,
   `10-empty-no-campaigns-a-1280`, and `01-dashboard-a-1280` (regression reference).
   These are the visual contract.
4. Code you will touch: `resources/css/app.css`, `vite.config.ts`,
   `resources/js/components/ui/{button,badge,input,select,checkbox,card}.tsx`,
   `resources/js/components/ui/sidebar.tsx`, `app-sidebar.tsx`,
   `app-sidebar-header.tsx`, `app-content.tsx`,
   `resources/js/layouts/app/app-sidebar-layout.tsx`,
   `resources/js/pages/campaigns/index.tsx`, `resources/js/components/campaigns/*`,
   `resources/js/types/campaigns.ts`, and `.ai/rules/*`.

## Scope

1. **Tokens** — replace the `:root` values with the spec's table, add `--primary-border`,
   `--success`, `--banner`, set `--radius` to 6px; load Inter in `vite.config.ts` and point
   `--font-sans` at it. Keep the `.dark` block and the `@theme` mapping as they are.
2. **Primitives** — restyle `button`, `badge` (add success/warning/neutral variants),
   `input`, `select`, `checkbox`, `card` to the board's metrics (32px toolbar buttons,
   40px form controls, 6px radius, focus ring `#b8afff` / halo `#f4ebff`); add a shared
   `resources/js/components/ui/table.tsx` (`Table`, `TableHeader`, `TableRow`,
   `TableHead`, `TableCell`) with 36px rows and the `#e4e4e7` grid. Keep public APIs.
3. **Shell** — sidebar 216px, white, 1px right border, no inset card
   (`Sidebar` variant `inset` → default, `SIDEBAR_WIDTH` `16rem` → `216px`); brand row,
   bordered team-switcher card, 36px nav items with violet active pill, `Settings` pinned
   bottom, user block — as on the boards. Add a page-head pattern (H1 20/700, 13px muted
   subtitle, right-aligned actions, 24px padding) and make the breadcrumb bar
   (`AppSidebarHeader`) opt-out per page so campaigns pages lose it while settings, teams
   and auth keep working. Below `lg`, keep the existing mobile header/sheet so navigation
   stays reachable until phase 6.
4. **Campaigns list** — rebuild `pages/campaigns/index.tsx` to board 02 A: page head with
   `New campaign`, toolbar (search, `All statuses` select, campaign count), table
   `Name / Status / Steps / Recipients / ⋮` with 36px rows and `Updated …` sublines,
   `Showing 1–N of N` + Previous/Next in the same bordered container, and the 10 A empty
   state inside that container with exactly one primary CTA. Restyle the campaign modals'
   trigger buttons to the new primitives; do not redesign the modals themselves.

Out of scope: dashboard redesign, campaign detail, contacts, templates, connections, 390
layout, backend PHP, dark-mode design, settings/teams/auth restyling.

## Constraints

- Paper MCP is rate-limited; do not call any `mcp__paper__*` tool. Work from the spec and
  screenshots. If a detail is unreadable, implement the closest faithful thing and list it
  under open questions in the report — do not stall.
- No hex outside `resources/css/app.css`. Use semantic tokens everywhere else.
- `resources/js/components/ui/*` is lint-ignored; edit in place, keep exports stable.
- `.ai/rules/pages.md` asks for a centred `max-w-6xl` container; the boards are full-width
  with 24px padding. Follow the boards and record the deviation in the report.
- Keep routes, controllers, props and Inertia form conventions unchanged.
- Do not invent data. `CampaignSummary` is already `name, status, status_label,
  steps_count, recipients_count, started_at, created_at` — enough for this board.

## Verification

Follow `/home/azri/.pi/agent/skills/cua-space-build-test/SKILL.md`: create an isolated cua
Space, copy the worktree in, install dependencies there, and run `npm run types:check`,
`npm run check`, `npm run build`, plus the Jest suite for snapshots. Never run installs,
builds or tests on this host. If the Space cannot be provisioned, stop and report the
blocker with the error text instead of running on the host. Capture the real command
output for the report.

## Deliver

- One or two commits on `design/campaigns-os`, conventional messages.
- `docs/design/campaigns-os/reports/phase-1.md`: files changed, each board requirement and
  how it was met, deviations (the `max-w-6xl` rule, anything you had to decide), the actual
  verification output, and open questions for the next phase.
- Finish with a summary of at most 20 lines: what changed, checks run and their results,
  deviations, open questions.
