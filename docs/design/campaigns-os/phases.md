# Campaigns OS — phase plan

One agent run per phase, sequential, same worktree (`campaigns-design`) and branch
(`design/campaigns-os`). Never start a phase before the previous one is committed and
its report written. The spec is `docs/design/campaigns-os/spec.md`; read it and the
screenshots under `/home/azri/projects/contactout/.design-refs/campaigns-os/` first.

Rules that apply to every phase:

- Paper MCP is unavailable (weekly quota). Work from the screenshots and the spec.
- Builds and tests run in an isolated cua Space (`cua-space-build-test` skill), never on
  the host. `npm run types:check` and `npm run check` may run there too. Phase 1 has no
  backend change, so `php artisan test` is optional; report if the Space has no working
  test DB rather than skipping silently.
- No hex outside `resources/css/app.css`. No changes to `resources/js/components/ui/*`
  public APIs (they are regenerated shadcn files; restyle in place).
- Keep existing routes, controllers, props and Inertia conventions. New shared components
  go under `resources/js/components/<domain>/`.
- Commit once per phase with a conventional message; write
  `docs/design/campaigns-os/reports/phase-N.md` with: what changed (file list),
  deviations from the spec, verification commands and their actual output, open questions.
- Stop and report instead of guessing when a data field the board shows does not exist.

## Phase 1 — Tokens, primitives, shell, campaigns list

Scope: `resources/css/app.css` tokens + Inter; `button`, `badge`, `input`, `select`,
`checkbox`, `card`, plus a new shared `table` primitive; `ui/sidebar.tsx` width and
variant; `app-sidebar.tsx` brand/team/footer nav; page-head pattern with an opt-out from
`AppSidebarHeader`; `pages/campaigns/index.tsx` rebuilt to board 02 A with the 10 A empty
state; `components/campaigns/*` follow-on styling (modals keep their behaviour).

Done when: campaigns list and its empty state match the boards at 1280; `/dashboard` still
renders with the new shell; `types:check`, `check`, `build` pass in a Space; snapshots
updated; no other page regressed visually.

## Phase 2 — Dashboard

Board 01 A. Greeting head, invitation banner, four stat cards, “Finish setting up”
checklist with progress, “Jump back in” rows. Uses existing `DashboardSetup` props;
add props only if a board element has no data source, and say so in the report.

## Phase 3 — Campaign detail, recipients, step editor, start-blocked

Boards 03 A, 04 A, 05 A, 11 A. Tab bar, stat cards, day groups and step rows with fixed
metric lanes, recipients table with bulk bar, two-pane step editor, blocked-start panel
with a disabled `Start`. Resolve the `RecipientStatus` vs board vocabulary question by
inspecting the controller; do not migrate.

## Phase 4 — Contacts grid

Board 06 A. Full-height workspace shell, toolbar with danger-styled delete, six-column
grid with truncated cells and a tooltip instead of the board's clipping, roster avatars,
bottom list-tab strip, stray header glyphs removed.

## Phase 5 — Templates, template editor, connections

Boards 07 A, 08 A, 09 A. Folder-grouped template list with indented children, editor with
the placeholders rail, connections table with cap meters and the failure banner. Derive
`Verified`/`Failed` from `MailerConnection`; report if impossible without a migration.

## Phase 6 — Mobile 390 pass

All 390 boards plus the bottom tab bar. Shared mobile type scale, sticky bulk-action bars,
icon-only controls with `aria-label`s, and the mobile information losses noted in the spec
(placeholder fallbacks, delete step) closed. Re-check every desktop board afterwards for
regressions from shared-component changes.

## Phase 7 — Sweep

Dead code from replaced components, snapshot regeneration, `php artisan test` if the Space
supports it, a light/dark smoke check, and a PR description that maps each board to the
files that implement it and lists every deviation. Do not open the PR without asking.
