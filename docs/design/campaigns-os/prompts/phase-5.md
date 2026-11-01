# Phase 5 — templates list, template editor, connections

Phase 5 of the Campaigns OS redesign. Worktree `/home/azri/projects/contactout/campaigns-design`,
branch `design/campaigns-os`. Phases 1–4 are committed and reviewed; read the phase reports and the
closest review evidence at `$(git rev-parse --git-dir)/review/{phases-1-2,phase-3,phase-4}.md`
(last rounds only) before starting.

## Read first

1. `docs/design/campaigns-os/spec.md` **including Amendments** — tokens (with dark pairs), type
   scale (`text-h1/h3/body/label/stat` now survive `cn()` via the extended tailwind-merge), status
   table, `--ring` #6552ff 2px focus, 44px table rows / 33px contacts grid rows, 12px stat gap.
2. `docs/design/campaigns-os/phases.md`.
3. Boards: `07-templates-list-a-1280.jpg`, `08-template-editor-a-1280.jpg`,
   `09-connections-a-1280.jpg`. `07/08/09-…-390` are phase 6.
4. Code: `pages/templates/{index,show}.tsx`, `components/templates/*`,
   `pages/mailer-connections/index.tsx`, `components/mailer-connections/*`,
   `app/Http/Controllers/Templates/TemplateController.php`,
   `app/Http/Controllers/MailerConnections/MailerConnectionController.php`,
   `app/Enums/MailerConnectionStatus.php`, `resources/js/types/{templates,mailer-connections}.ts`,
   `components/{page-head,empty-state,table}.tsx`, `components/templates/rich-text-editor.tsx`
   (the `toolbar="board"` variant from phase 3).

## Carry-over from the phase-4 review (fix in this run)

- R2-1: a bulk delete that fails after at least one success on a filtered view or page 2+ reloads
  the bare URL, so the grid shows unfiltered page 1 while the filter UI still shows the old filter.
  Use the existing `restoreAfterDelete(done)` on the failure path.
- R2-2: the Add-to-campaign dialog's campaign list is filtered once at page load, so a campaign
  started by another member before submit can still receive contacts. Reload the campaigns prop
  when the dialog opens, and re-check the status at submit.

## Scope

**Templates list (07 A).** `PageHead` (`New folder` outline, `New template` primary); search and
the `N templates · M folders` count; folder groups as tinted header rows (`Cold outreach 3`) with
child rows indented one step, `Draft` pill where applicable, `Updated …`, `⋮`. The payload already
carries `folders[].templates_count` and per-template `updated_at`/`is_draft` — no new query is
needed. Keep folder/template create, rename, move and delete working through the existing modals.

**Template editor (08 A).** Back + name + `Draft` label, `Discard` + `Save template`; `Name`,
`Folder`, `Subject`; body editor with the board toolbar; right rail `Placeholders` card with chips
and their fallback values, `+ Add placeholder`. Phase 3 established that the schema has no stored
placeholder fallbacks: render the chips and, only if a fallback value exists, its value column;
otherwise render the chip alone and record the gap — do not invent values. Keep the save/discard,
dirty-guard and focus behaviour that phase 3 established for the inline editor.

**Connections (09 A).** `PageHead` (`Add SMTP` outline, `Connect Gmail or Outlook` primary);
danger banner for a failed connection with `Retry` (reuse the existing retry action); table
`Name / Type / Server / From / Signature / Status / Sent today` with a cap meter (violet bar +
`142 / 400`) and the footer note about daily caps resetting in each connection's timezone.
`Sent today` needs a count of today's sends per connection, which the payload does not carry:
add one read-only aggregate in the controller (no migrations), paste the query and its EXPLAIN in
the report, and render `—` when a connection has no cap. `Verified` / `Failed` derive from
`MailerConnectionStatus` plus `exception_type` (the phase-2 dashboard mapping is the precedent:
needs-attention = `status !== Active || exception_type !== null`); align the two so the dashboard
and this table cannot disagree. Phase 5 also owns the phase-1 review's P2-8: extract the duplicated
connection-options mapping (`CampaignController::mailerConnectionOptions` and the dashboard copy)
into one place and call it from all three.

**Status pills.** Every status renders through the shared `Badge` with the spec's semantic
variants; no page-local colour classes.

## Rules

- No hex outside `resources/css/app.css`; every new token gets a dark pair.
- No migrations. Read-only aggregates are allowed, documented with their query and cost.
- Keep routes, payload contracts, permission gates, modal flows and the OAuth connect paths
  unchanged; these screens are a restyle plus the two aggregates above.
- Do not touch campaigns, contacts, dashboard or the 390 layouts.
- Paper MCP is unavailable.

## Verification

Isolated cua Space (`cua-space-build-test`), never the host. `npm run build`, `npm run types:check`,
`npm run check`, `php artisan test`, `pint --test`, `phpstan`. Seed one failed, one verified and one
at-cap connection, plus folders with 0/1/N templates. Render at 1280 **and measure** against each
board: page-head offset, list row pitch and folder-row indent, editor column widths, placeholders
rail width, connections column x positions, meter width/height, banner height. Take one dark-mode
screenshot of each of the three screens. Report board vs implementation numbers per screen.

## Deliver

- Commit(s) on `design/campaigns-os`.
- `docs/design/campaigns-os/reports/phase-5.md`: files changed, the new aggregate with query and
  cost, the placeholder-fallback gap, the unified status mapping, measured coordinate tables per
  board, deviations, real check output, gaps.
- Final summary ≤20 lines: what changed, checks and results, measurements, deviations, gaps.
