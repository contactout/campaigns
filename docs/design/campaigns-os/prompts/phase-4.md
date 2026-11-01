# Phase 4 — Contacts grid

Phase 4 of the Campaigns OS redesign. Worktree `/home/azri/projects/contactout/campaigns-design`,
branch `design/campaigns-os`. Phases 1–3 are committed and reviewed; read
`docs/design/campaigns-os/reports/phase-3.md` and the closest review evidence at
`$(git rev-parse --git-dir)/review/phase-3.md` before starting.

## Read first

1. `docs/design/campaigns-os/spec.md` **including Amendments** — tokens, type scale, shell, status
   table, badge rules, dark-token values, focus treatment (`--ring` #6552ff, 2px ring).
2. `docs/design/campaigns-os/phases.md`.
3. Boards: `06-contacts-grid-a-1280.jpg` (scope) and `06-contacts-grid-390.jpg` (reference only,
   phase 6). Also `02-campaigns-list-a-1280.jpg` for the table primitive already in place.
4. Code: `pages/contacts/index.tsx`, `components/contacts/*` (status badge, list tabs, import and
   create modals, inline editing), `app/Http/Controllers/Contacts/ContactController.php` (the
   index payload), `resources/js/types/contacts.ts`, `components/ui/table.tsx`,
   `components/{page-head,empty-state,stat-card}.tsx`, `.ai/rules/pages.md`.

## Scope — board 06 A

**Shell.** Full-height workspace: this page may keep the height-constrained flex shell with
internal scrolling (`.ai/rules/pages.md` allows it for `contacts/index.tsx`); the page head is
`PageHead` (`Import CSV` secondary, `Add contact` primary) and the breadcrumb bar is opted out.

**Toolbar.** `Filters` (outline), selection count, bulk actions (`Add to campaign`,
`Do not contact`, `Delete` — `Delete` uses the danger variant, not neutral), right-aligned search
and the settings/columns icon button. Below the board's 2-selected state exists; also keep a
sensible zero-selection state (the actions disabled or hidden, count hidden).

**Grid.** Select-all checkbox with indeterminate state, then `Name / Email / Phone / Status /
Company / Title`, 44px rows (the board's divider pitch; the shared `Table` already does this),
`px-4` cells, 12px muted column headers with **no** stray `T`/`@`/`#`/`●` glyphs. Long values
truncate with `min-w-0` plus a title tooltip — the board clips the Title column past the artboard
edge, which is a defect to fix, not copy. Selected rows use the accent tint; keep the keyboard
and single-cell selection behaviour that exists today.

**List tabs.** The `All contacts / Fintech CFOs / APAC founders / Webinar Oct 8 / + Add list`
strip pins to the bottom of the content area as on the board, with the active tab underlined and
the counts where they exist.

**Status.** `ContactStatus` drives the pill: `NotContacted` renders the board's `New` copy,
`Contacted` info, `Replied` success, `Bounced` danger, `Unsubscribed` neutral, `DoNotContact`
warning. Keep the enum as the source of truth; do not add statuses.

**Columns and data.** The board's Phone/Company/Title columns must come from the payload's contact
fields. Inspect the index payload and `contactFields()` first: render a column only when the data
exists for the team, and if a column has no source, drop it and record it as a gap rather than
rendering blanks. No migrations; a read-only payload addition is allowed only if a column is one
`with`/join away, and must be documented with its query.

**Preserve behaviour.** Inline cell editing, the import flow, create-contact and the list
management stay functionally unchanged; they pick up the new primitives only. Bulk actions keep
their existing endpoints and permission gates.

## Carry-over from the phase-3 review (fix in this run)

R2-1: the one-line start-blocked summary introduced for every view also renders on the Steps tab
above the full blocked panel, so board 11 A shows two danger boxes. Make the summary `sr-only`
when the Steps tab is active and the editor is closed, and reword it into a full sentence (it
currently lacks a verb).

## Rules

- No hex outside `resources/css/app.css`; semantic tokens; every new token needs a dark pair.
- Icon-only buttons need `aria-label`; the toolbar must stay usable at 1280 without horizontal
  scrolling of the page itself (the grid may scroll inside its container).
- Reuse `PageHead`, `EmptyState`, `Badge`, `Table`; no second inline copy.
- Do not touch campaigns, dashboard, templates, connections or the 390 layouts.
- Paper MCP is unavailable.

## Verification

Isolated cua Space (`cua-space-build-test`), never the host. `npm run build`, `npm run types:check`,
`npm run check`, `php artisan test`, `pint --test`, `phpstan`. Render at 1280 with seeded contacts
covering every status, and **measure** against the board: page-head offset, toolbar height and
control y, column x positions for all six columns, row pitch, list-tab strip y and height, status
pill size. Take one dark-mode screenshot and one screenshot of the table at 1280 with a long
company/title value to prove truncation. Report board vs implementation numbers.

## Deliver

- Commit(s) on `design/campaigns-os`.
- `docs/design/campaigns-os/reports/phase-4.md`: files changed, the column-source decision, any
  query added, the measured coordinate table, deviations, real check output, gaps.
- Final summary ≤20 lines: what changed, checks and results, measurements, deviations, gaps.
