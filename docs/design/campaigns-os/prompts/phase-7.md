# Phase 7 — sweep, proof, and the PR description

Last implementation phase. Worktree `/home/azri/projects/contactout/campaigns-design`, branch
`design/campaigns-os`. Phases 1–6 are committed; phase 6's fix round (`200a313`…`000bcee`) was
**not** verified beyond `tsc` and `npm run check` because the machine ran out of memory twice. This
phase exists to prove or disprove it, with nothing else running on the box.

## Read first

1. `docs/design/campaigns-os/reports/phase-6.md` — its last section lists the nine items this phase
   must prove; the fix-round notes say exactly what was and was not run.
2. `$(git rev-parse --git-dir)/review/phase-6.md` — the review that drove the fix round.
3. `docs/design/campaigns-os/spec.md` (with Amendments) and `phases.md`.

## 1. Prove the phase-6 fixes (blocking — no hand-waving)

Run the Space checks **one command at a time** and report real output for each: `npm run build`,
`npm run types:check`, `npm run check`, `php artisan test`, `pint --test`, `phpstan`. A build is
required this time: the committed `*-1280-regression.png` and `*-390.png` still show the pre-fix
bundle, so no render currently reflects the code.

Then render and prove, each with the measured value and the screenshot kept:
1. Contacts at 1280 scrolled to the last row, with the list tabs and pagination visible and not
   painted over (P0-1).
2. Templates at 1280: names left-aligned at the board's x (P1-1).
3. The threaded checkbox: one tap on the label toggles exactly once, at 1280 and 390 (P1-2).
4. The 390 step editor as a dialog: `role`, `aria-modal`, focus contained, background scroll locked,
   Escape closes, and the inline pane unchanged at `lg` (P2-5).
5. The 390 sticky selection bars with the pagination row clear (contacts and recipients, P2-1).
6. Connections at 1280: the signature value aligned to its column header (P2-6).
7. `/settings/security` marks Settings and the More slot active at 390 (P2-2).
8. An emulated safe-area inset (`Emulation.setDeviceMetricsOverride` with a display feature, or an
   inset override) showing the bottom bar respects it (P2-3).
9. One dark-mode render of a screen that phase 6 changed.

If any item fails, fix it in this phase and re-prove it. Do not mark a phase-6 claim "fixed"
without the render behind it.

Two behaviours the fix round flagged, decide and record: the 390 dialog remounts the form when the
editor is open across the 1024px line (unsaved edits lost) — add a guard or state the trade-off;
and the shared Sheet/Dialog close is a 16px bare X — leave it and record it.

## 2. Sweep

- Delete dead code left by the redesign (unused components, props, CSS classes, tokens); check each
  with a usage grep before removing, and list what went.
- Re-render every 1280 and 390 board screen one final time and compare to the boards; anything that
  drifted since its phase goes in the report, not silently fixed unless it is a one-liner.
- `git log --oneline` review: squash-nothing, but confirm every commit is intentional, messages are
  conventional, and no temp scripts, tarballs, fixture databases or Space artefacts are tracked.
  `git status` must be clean and `docs/design/**` must contain only the spec, phases, prompts,
  reports.
- Confirm no hex outside `resources/css/app.css`, no hardcoded demo numbers, no `TODO`s left that
  are not in the report's gaps, and that `.dark` still has a value for every token in `:root`.

## 3. PR description draft (do not open the PR)

Write `docs/design/campaigns-os/pr.md`: what the branch does, a board → files table for all 20+
screens, the deviations and accessibility waivers with their numbers, the data/backend changes
(the dashboard aggregates, the connections cap counter, the per-step aggregates, the shared status
mapping, the payload additions) with costs, what was verified and how, and the gaps that remain.
The PR body should be readable by a reviewer who has never seen the design file.

## Rules

- Do not open, push or create the PR. Do not touch `main`. No migrations.
- Do not call Paper MCP.
- Keep memory low: one Space command at a time, no parallel browser sessions, and if the process
  dies from OOM, resume (`pi -c`) and continue from the tree instead of restarting the work.
- Anything you cannot prove goes in the report as unverified. No claim without evidence.

## Deliver

- Commit(s) on `design/campaigns-os`.
- `docs/design/campaigns-os/reports/phase-7.md`: the nine proofs with measured values and screenshot
  paths, the final check output, the dead-code list, the final board comparison table, and the
  remaining gaps.
- Final summary ≤20 lines.
