# Phase 1 report — tokens, primitives, shell, campaigns list

Base commit `67251af` on `design/campaigns-os`. Worktree
`/home/azri/projects/contactout/campaigns-design`. Paper MCP was not called.

## What changed

Tokens and type

- `resources/css/app.css` — `:root` replaced with the spec table: white canvas, `#18181b`
  body, `#3222b1` primary, new `--primary-border` `#6552ff`, `#f0eeff` secondary/accent,
  `#f4f4f5` muted, `#e4e4e7` border/input, `#b8afff` ring, `#dc2626` destructive, new
  `--success` `#16a34a`, new `--banner` `#f8f7ff`, white sidebar, `--radius` 6px,
  chart-1..3 `#3222b1 / #6552ff / #b8afff`. `.dark` block untouched. `--font-sans` now
  starts at Inter.
- `vite.config.ts` — Bunny loads Inter 400/500/600/700 instead of Instrument Sans.

Primitives (`resources/js/components/ui/`)

- `button.tsx` — 32px default (`h-8`), 13px/500, 6px radius, primary gets the
  `--primary-border` 1px border, no shadows, focus = `border-ring` + 3px `ring-halo`.
- `badge.tsx` — one pill primitive: 22px tall, 4px radius, 12px/500, plus `success`,
  `warning`, `neutral` and `info` variants (danger reuses the destructive text on the new
  `--badge-danger` fill). Existing variants and exports kept.
- `input.tsx` — 40px (`h-10`), 6px radius, 13px, no shadow, halo focus.
- `select.tsx` — trigger 40px default / 32px `size="sm"`, 6px radius, 13px, halo focus,
  `shadow-dialog` popover.
- `checkbox.tsx` — 4px radius, halo focus, no shadow.
- `card.tsx` — 6px radius, no shadow.
- `table.tsx` (new) — `Table`, `TableHeader`, `TableBody`, `TableRow`, `TableHead`,
  `TableCell`; 36px head/rows, 13px cells, 12px muted column headers, `#e4e4e7` row grid,
  `bg-muted` header tint.

Shell

- `ui/sidebar.tsx` — `SIDEBAR_WIDTH` `16rem` → `216px`; menu buttons 36px / 6px radius,
  13px labels; header `px-3 pt-4 pb-2`, footer `px-3 py-2`; outline variant no longer uses
  `hsl(var(--sidebar-*))` (the tokens are hex now).
- `app-sidebar.tsx` — `variant="inset"` dropped for the default (no inset card). Brand row,
  bordered team-switcher card, six 36px nav items, `Settings` pinned in the footer above a
  divider, user block last. Unused `NavFooter`/footer-items plumbing removed from this file.
- `app-logo.tsx` — 32px violet mark + “Campaigns OS”.
- `team-switcher.tsx` — sidebar trigger is now the bordered 6px card (tinted initial tile,
  team name 13/600, `Team · <role>` subline, chevron). Header variant unchanged.
- `nav-main.tsx` — dropped the “Platform” group label, active state now matches on parent
  URLs (`isCurrentOrParentUrl`).
- `nav-user.tsx` / `user-info.tsx` — user block shows name + email, 999px avatar with
  accent initials.
- `app-sidebar-header.tsx` — unchanged; `app-sidebar-layout.tsx` + `app-layout.tsx` +
  `types/ui.ts` gained `showHeader` (default `true`), so the breadcrumb bar is opt-out per
  page instead of deleted. Settings, teams and auth keep it.
- `components/page-head.tsx` (new) — H1 20/700 `-0.01em`, 13px muted subtitle,
  right-aligned actions, plus a `md:hidden` `SidebarTrigger` (`mobileNav`) so the sheet is
  still reachable on campaigns pages, which no longer render the breadcrumb bar.

Campaigns list

- `pages/campaigns/index.tsx` — rebuilt to board 02 A: page head with `New campaign`,
  toolbar (260px search, `All statuses` select, `N campaigns` right-aligned), table
  `Name / Status / Steps / Recipients / ⋮` with 36px grid and 46px rows (name + relative
  subline), `Showing 1–6 of 6` + Previous/Next inside the same bordered container, and the
  10 A empty state (48px accent tile, `No campaigns yet`, one sentence, `Browse templates`
  outline + `New campaign` primary) inside that container. Page opts out of the breadcrumb
  bar (`showHeader: false`).
- `components/campaigns/campaign-status-badge.tsx` — Active → `success`, Draft / Stopped /
  Completed / Archived → `neutral`.
- `lib/relative-time.ts` (new) — `2h ago` / `yesterday` / `8d ago` / `Oct 10` formatting.
- `app.tsx` — Inertia progress colour `#4B5563` → `var(--primary)`, the last hex outside
  `app.css`.

Campaign modals were not touched beyond inheriting the new primitives: every campaign
modal trigger is a plain `<Button>` passed as `children`, so `New campaign` renders with the
new primary (border, 32px, 6px) automatically.

## Board requirements → implementation

| Requirement | Where |
| --- | --- |
| Token table, 6px radius, Inter | `app.css`, `vite.config.ts` |
| 32px toolbar buttons, 40px form controls, halo focus | `ui/button.tsx`, `ui/input.tsx`, `ui/select.tsx` |
| Status pills, 4px/12px/22px, success + neutral | `ui/badge.tsx`, `campaign-status-badge.tsx` |
| 216px white sidebar, 1px right border, no inset card | `ui/sidebar.tsx`, `app-sidebar.tsx` |
| Brand row, team card, 36px nav + violet pill, Settings pinned, user block | `app-sidebar.tsx`, `app-logo.tsx`, `team-switcher.tsx`, `nav-main.tsx`, `nav-user.tsx` |
| Page head pattern, breadcrumb opt-out | `page-head.tsx`, `layouts/*`, `types/ui.ts` |
| 36px table grid, bordered container | `ui/table.tsx` |
| 02 A list | `pages/campaigns/index.tsx` |
| 10 A empty state, one primary | `pages/campaigns/index.tsx` |

Rendered check (Space only, see Verification): the list, the empty state and `/dashboard`
match the 02 A / 10 A boards at 1280px, and 390px keeps a working nav trigger.

## Deviations

1. **`max-w-6xl` (`.ai/rules/pages.md`).** The campaigns list is full width with
   `px-4 py-6 lg:px-6` (16/24px padding), matching the boards. The rule file is untouched.
2. **`@theme` additions.** The mapping block is otherwise unchanged, but the three new
   tokens and the badge colours needed `--color-*` entries or `bg-success`,
   `border-primary-border`, `bg-banner`, `ring-halo` and the new badge variants would
   silently not exist. Added: `--color-primary-border`, `--color-success`, `--color-banner`,
   `--color-ring-halo`, `--color-badge-success(-foreground)`,
   `--color-badge-warning(-foreground)`, `--color-badge-danger`, `--shadow-dialog`.
3. **`--radius-sm` calc changed** from `- 4px` to `- 3px` so `rounded-lg/md/sm` land on
   the spec’s 6/4/3 instead of 6/4/2.
4. **`--destructive-foreground` stays red** (`#dc2626`). Dropdown and alert items read it
   as *text* colour; setting it to white (the usual meaning) would have made destructive
   menu items invisible.
5. **Subline copy is `Created …`, not `Updated …`.** `CampaignSummary` exposes
   `created_at` but no `updated_at`, and adding it means touching backend PHP. Copy follows
   the data; see open questions.
6. **Team card subline is `Team · Owner`** (the current user’s role) instead of
   `Team · 4 members`. `UserTeam` carries `role`/`roleLabel` but no member count, and a count
   needs a backend payload change. The card still matches the board’s two-line shape.
7. **True empty state hides the toolbar and the header CTA.** Board 10 A shows no
   search/select row, and the spec wants exactly one primary per screen, so with zero
   campaigns the page renders the panel only (panel `New campaign` is the primary). Filters
   that match nothing keep the toolbar, keep the header primary, and show a
   `Clear filters` outline action in the panel — a state the boards do not cover.
8. **`campaigns/show.tsx` keeps its breadcrumb bar** this phase; it gets a page head and
   opts out in phase 3. Only `campaigns/index.tsx` sets `showHeader: false` today.
9. **Brand mark** still uses the app’s existing `AppLogoIcon` cube; the board draws a paper
   plane. Changing the app mark would also restyle auth screens, which the spec puts out of
   scope. The campaigns empty-state icon is a paper plane as on the board.
10. **`fmt.ignorePatterns` gained `docs/design/**`.** Prettier mangles the spec documents
    (`+ Inertia/React …` at line start is parsed as a list item, tables get realigned), and
    `npm run check` greps every file on disk, so the design contracts broke the check with
    no code change involved. The docs stay byte-identical to what the phase prompt shipped.
11. Nav labels are 13px/500 and rows 36px as specified; the boards’ 46px list rows come
    from the two-line name cell, so `TableRow`/`TableCell` keep the 36px default and the
    campaigns page adds `py-[5px]` to its name cell (46px rows).

## Verification

Everything ran in the isolated cua Space `local:devtest` (Ubuntu 24.04, Node 24.21.0, npm
11.19.0, PHP 8.4.27, Composer 2.10.3). Nothing was installed, built or tested on the host.
Staged from a `tar` of the working tree (25 changed/untracked paths at staging, `.git`
excluded); `npm ci` and `composer install` ran in the Space.

```
$ npm run build
BUILD_EXIT=0
✓ built in 2.32s

$ npm run types:check
TYPES_EXIT=0
> tsc --noEmit

$ npm run check
CHECK_EXIT=0
pass: All 145 files are correctly formatted
pass: Found no warnings or lint errors in 128 files
```

`types:check` needs the Wayfinder output, so `build` runs first in a clean tree.

### UI verification (not a board-perfect pixel diff)

To check the actual rendering rather than only compilation, the Space copy (not the
committed tree) was given a `.env` with SQLite, `php artisan migrate`, a temporary
`PreviewSeeder` with the board’s six campaigns/statuses/dates, and a temporary
`/__preview-login` route. `php artisan serve` plus headless Chromium
(`--headless=new --window-size=1280,860`) produced screenshots for: the 1280 list, the 1280
empty state, the 1280 filtered-empty state, `/dashboard`, and the campaigns page at 390px.
Comparing them to `02-campaigns-list-a-1280.jpg`, `10-empty-no-campaigns-a-1280.jpg` and
`01-dashboard-a-1280.jpg` drove three fixes: the team card chevron/`has-[>svg]:px-3` bug
that truncated the team name, the missing `Browse templates` document icon, and hiding the
toolbar in the true empty state. None of the temporary PHP, seeder or env files are
committed; `git status` shows no PHP change.

### Not run

- **No Jest suite exists in this worktree.** There is no `test` script in `package.json`,
  no jest/vitest config, no `*.test.tsx`, no `__snapshots__`. The phase prompt’s “Jest suite
  for snapshots” step has nothing to run, so no snapshots were updated. `npm run check` and
  `types:check` are the only JS checks available.
- **`php artisan test` was not run.** Phase 1 changes no PHP, `git diff` contains no `.php`
  file, and the suite’s five-schema MySQL environment was not provisioned. Say so rather
  than treating it as a pass; phase 7 owns the backend suite.

## Open questions for phase 2+

1. **`updated_at` for the list subline.** Adding it to `CampaignController::campaignSummary`
   flips the copy from `Created …` to the board’s `Updated …`. One field, no migration.
2. **Team member count.** The team card wants `Team · N members`; `UserTeam` has no count.
   Adding `membersCount` (or `withCount('members')` in `UserTeam`) is a backend change —
   confirm before it lands.
3. **Paginated list length.** `CampaignController::index` paginates 25 per page; the board
   shows six rows with `Showing 1–6 of 6`. Not changed here.
4. **Badge variants for later screens.** `info` (lavender + ring border) is in place for
   `Contacted`; `warning`, `success` and `danger` are ready for contacts/connections, but the
   status-label derivation in the spec (`Contacted/Opened/Not contacted`, `Verified/Failed`)
   is still a phase 3/5 question.
5. **Dark mode** is untouched by design. The new pill and badge colours are light-mode only;
   the `.dark` block still maps the old neutral palette, so a dark pass is owed whenever it
   is scheduled.
6. **`Sidebar collapsible="icon"`** is kept for now. The boards show no collapse affordance,
   and campaigns pages have no visible trigger above `md`; decide whether the collapse
   survives the redesign or is removed in phase 6.
