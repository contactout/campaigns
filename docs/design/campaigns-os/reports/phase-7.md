# Phase 7 — sweep, proof and the PR description

Worktree `/home/azri/projects/contactout/campaigns-design`, branch `design/campaigns-os`.
The first run of this phase was killed twice by the host OOM killer and left the phase
partially proven; this run finished it. Every claim below has a measurement or a render
behind it, and the two items that cannot be proved are named as trade-offs, not fixed.

## Provenance

- **Renders:** `git archive 086fc59` extracted into a fresh Space tree
  (`/home/cua/work/campaigns-final`), with `node_modules/`, `vendor/` and `.env` copied from
  the previous run's `/home/cua/work/campaigns-p7`. The committed tree has no
  `__preview-login` route; a Space-only one was appended to `routes/web.php` (never in the
  host tree, never committed), together with an `?as=<user id>` parameter so the Quiet Studio
  empty state could be rendered as its owner.
- **Final checks:** `git archive dc6dfbf` into `/home/cua/work/campaigns-verify`, with a real
  `vendor/` copy and a symlinked `node_modules/`. No preview route, so the check set sees the
  committed bytes exactly.
- **Browser:** Chromium 156 headless over CDP, viewport 1280×900 or 390×844,
  `deviceScaleFactor: 1`, real `Input.dispatchMouseEvent` / `Input.dispatchKeyEvent`.
  Harness in `/tmp/p7/` in the Space; screenshots in `reports/phase-7/`.
- **Sweep:** the dead-code removals and the token edits were made **after** the renders. They
  remove files no route imports and tokens no class references, and were re-verified with a
  green `build`, `types:check` and `check` on the final commit (see Checks). The PHP bytes are
  unchanged by the sweep, so the PHP checks below apply to the final tree too.

## Proven (measured, screenshot kept)

| # | Item | Measured | Evidence |
| --- | --- | --- | --- |
| 1 | P0-1 contacts grid scrolls at 1280 | Scroll container `scrollHeight 860 / clientHeight 735`; scrolled to `scrollTop 125`, its maximum. 25 rows. Last row `noah.novak21@example.com` is 33px tall at y 831–864; `Showing 1–25 of 1,284` at y 872–892 and `Previous` at y 868–896, below the last row and not painted over. The `All contacts / APAC founders 428 / …` strip is visible on the same row in the render. | `p7-01-contacts-1280-scrolled.png` |
| 2 | P1-1 template names left-aligned at the board's x | Icon 16×16 at x 257–273; name span at **x 285**, 171×20, link `285–1103`, `justify-content: flex-start`. The board's 12px icon-to-name gap. | `p7-02-templates-1280.png` |
| 3 | P1-2 threaded checkbox toggles exactly once per label tap | **390:** label 358×44 at x 16, 16×16 box inside a 44×44 wrapper at x 16; one real tap at the text (156, 737) → `aria-checked` false → **true**; a second tap → **false**. **1280:** label 658×20 at x 581, box 16×16 flush at x 581 (the field edge, no indent); tap at (721, 792) → false → **true** → **false**. | `p7-03-threaded-390.png`, `p7-03-threaded-1280.png` |
| 4a | P2-5 dialog semantics at 390 | `role="dialog"`, **`aria-modal="true"`**, title `Edit step · Day 1`, box 390×844 at (0, 0), body `overflow: hidden`, `window.scrollTo(0, 400)` left `scrollY 0`, 14 consecutive Tab presses from the initial `Cancel` stayed inside the dialog (Cancel → Save → Apply template → subject → B → I → U → bullet → numbered → Link → Insert placeholder → Insert signature → body → `{{name}}` → `{{email}}`), every stop `inDialog: true`; the covered body siblings carry `aria-hidden="true"`. | `p7-04-editor-dialog-390.png` |
| 4b | P2-5 inline pane at `lg` unchanged | No dialog in the DOM; root `display: grid`, `grid-template-columns: 300px 692px`; list 300×775 at x 240, pane 692×775 at x 564, footer 690×57 at y 833, mobile head row `display: none`. | `p7-04-editor-inline-1280.png` |
| 4c | Discard guard fires only on real edits | Escape and Cancel with no edits close the editor directly (`dialogs: 0`, `editorOpen: false`). Editing the subject and pressing Escape opened `Discard your changes?` with the value still `Quixck idea for {{company}}`; `Cancel` kept the editor open with the value intact. | `p7-04-editor-discard-guard-390.png` |
| 5 | P2-1 390 sticky bars clear the pagination row | **Contacts:** at max scroll (`scrollY 1015`), `1–25 of 1,284` at y 681–697, `Previous` 667–711, bar top **723** (65px tall). **Recipients:** at max scroll (`1038`), `Showing 1–25 of 312` at 666–682, `Previous` 652–696, bar top **723**. Both bars sit above the page content with the pagination row fully visible. | `p7-05-contacts-selected-390.png`, `p7-05-recipients-selected-390.png` |
| 6 | P2-6 signature aligned to its column header | Header cell x 831 with 16px padding → text starts **847**; trigger (cell `pl-4`, trigger `px-0`) starts **847**, 32px tall, text `Team default`; cell right edge 940. | `p7-06-connections-1280.png` |
| 7 | P2-2 `/settings/security` marks Settings and More current at 390 | More trigger colour `rgb(50, 34, 177)` (primary) against a first-tab `rgb(113, 113, 122)`; the More sheet's `Settings` row has `aria-current="page"`, `bg-accent`, `text-primary`, 44px tall. | `p7-07-settings-security-390.png` |
| 8 | P2-3 emulated safe-area inset | `Emulation.setSafeAreaInsetsOverride` bottom 34 → `env(safe-area-inset-bottom)` = **34px**; tab bar `padding-bottom 34px`, total 91px (1px border + 56 + 34), tab list 754–810; the selection bar bottom is 754, gap to the tab list 0. | `p7-08-safe-area-390.png`, `p7-08-safe-area-selection-390.png` |
| 9 | One dark render of a changed screen | `<html class="dark">`, body `oklch(0.145 0 0)` = `#0a0a0a`, 25 contact rows, status pills and the tab bar legible. | `p7-09-contacts-390-dark.png` |

Two corrections to the phase-6 report survive from the first run: the claim that the 390
editor already had `aria-modal` was **false** (measured `null`; now fixed in the primitives),
and the claim that Escape/Cancel were guarded hid the opposite bug (the guard fired with no
edits; now fixed with a canonical-body baseline).

## Checks

Six checks, one at a time, on the final commit `dc6dfbf` extracted into the Space from
`git archive` (no symlinked dependencies, no preview route):

| Check | Result |
| --- | --- |
| `npm run build` | `BUILD_EXIT=0`, `✓ built in 2.95s` |
| `npm run types:check` | `TSC_EXIT=0`, `tsc --noEmit` clean |
| `npm run check` | `CHECK_EXIT=0`, **151 files** formatted, no warnings in **136** lint files |
| `php artisan test` | `TEST_EXIT=0`, **577 passed** (2,568 assertions) in 8.84s |
| `pint --test` | `PINT_EXIT=0`, **367 files** PASS |
| `phpstan analyse` (level 7) | `PHPSTAN_EXIT=0`, `[OK] No errors` |

The same set ran earlier on `086fc59` (the pre-sweep tree, 158 formatted / 143 linted) with the
same PHP results; the twelve removed files were not reachable from any route. The suite's
first attempt in the verify tree used a symlinked `vendor/`, which resolves composer's
`baseDir` to the other tree and made PHPUnit derive path-based class names (539 failures in
0.34s). With a real `vendor/` copy the suite is green; that failure was the harness, not the
code, and it is recorded here rather than hidden.

The Space's PHP is 8.4.27; the suite ran against the `:memory:` SQLite the repo's
`phpunit.xml` forces. The database checks are not a MySQL run.

## Dead code removed

Each was grepped for importers before removal (zero in `resources/js`, `tests` and the PHP
views). This branch added `step-form-modal`, dead once the editor moved into
`sequence-editor`; the rest is starter scaffolding the redesign superseded.

| File | Why |
| --- | --- |
| `components/campaigns/step-form-modal.tsx` | The 390 editor is now a `Dialog` inside `sequence-editor.tsx`; the old wrapper has no importer. |
| `components/app-header.tsx`, `layouts/app/app-header-layout.tsx` | The static sidebar replaced the header layout; the layout was its only consumer. |
| `components/ui/navigation-menu.tsx` | Only `app-header` imported it. |
| `components/pagination.tsx` | Every list renders its own pagination; no importer. |
| `components/nav-footer.tsx` | Old sidebar footer; no importer. |
| `components/ui/collapsible.tsx`, `ui/icon.tsx`, `ui/placeholder-pattern.tsx`, `ui/toggle-group.tsx` | Unused shadcn primitives, no importer. |
| `layouts/auth/auth-card-layout.tsx`, `auth-split-layout.tsx` | Auth pages use `auth-simple-layout`; no importer. |
| Tokens `--chart-1…5`, `--ring-halo` (+ their `@theme` mappings) | Zero references in `resources/js`, `views` or `app`; no charts on any board. |

Kept on purpose: `--type-body` and `--radius` are theme-invariant and live only in `:root`;
`.dark` still defines **40 of the 42** remaining tokens and the two absent ones
(`--type-body`, `--radius`, both theme-invariant) resolve by inheritance on the same element. All other exported types with no external consumer
(`StatDelta`, `StepFormState`, `resolveContactColumns`) are used inside their own module.

## Sweep audits

- **Hex outside `resources/css/app.css`:** 0. `resources/views` and `resources/js` contain no
  hex colours.
- **Hardcoded demo numbers/names:** none. The only literal addresses are form placeholders
  (`ada@example.com`, `smtp.example.com`) and none of the boards' figures are in code.
- **`TODO`/`FIXME`/`XXX`/`HACK`:** 0 across `resources/js`, `resources/views`, `app`, `routes`,
  `database`.
- **Repo hygiene:** 0 tracked tarballs, fixture databases, logs, `.bak`/`.orig`, temp
  directories, `__preview` files or Space artefacts. `docs/design/**` contains only the spec,
  `phases.md`, the prompts, `pr.md` and the reports (including their PNG evidence).
- **Git log:** every commit is `type(scope): subject`, one concern per commit, nothing
  squashed or rewritten. The branch was 54 commits ahead of `origin/design/campaigns-os` at the start of the
  sweep and was not pushed.

## Final board comparison

All 11 screens (12 boards, `02` and `03` have a/b variants) were re-rendered at 1280×900 and
390×844 from the final code: `p7-final-<screen>-1280.png` and `-390.png` in this directory.
All 22 were opened and compared with the boards. Pitches marked **DOM** were read from the
fresh render with `getBoundingClientRect()`; the others were checked visually against the
board for that screen, and where the phase reports already measured the row the value is
quoted from them.

| Board | Screen | Board | Render | How | Verdict |
| --- | --- | --- | --- | --- | --- |
| 00 | Sidebar / tab bar | 240px sidebar; 56px + 1px bar | 240; 56 + 1px | visual + phase-6 measurement | matches |
| 01 | Dashboard | 4 stat cards, checklist, jump rows; 2×2 stats at 390 | same | visual | matches (figures are fixture data) |
| 02 | Campaigns list, 1280 | row pitch 44 | **44** | DOM, 6 rows | matches |
| 02 | Campaigns list, 390 | row pitch 68 | **69** | DOM (68 + 1px divider) | 1px over, recorded |
| 03 | Campaign detail · Steps, 1280 | 44px step rows, 4 stat cards | 44; same cards | visual | matches |
| 03 | Campaign detail · Steps, 390 | 44px tabs/rows, 2×2 stats | 44; 2×2 | visual | matches |
| 04 | Recipients, 1280 | 33px table rows | 33 | visual + phase-3 measurement | matches |
| 04 | Recipients, 390 | row pitch 56 | **57** | DOM | 1px over, recorded |
| 05 | Step editor, 1280 | card 700×761, footer 56 | pane 692×775, footer 690×57 | DOM | matches within the 24px padding |
| 05 | Step editor, 390 | full-bleed sheet | 390×844 dialog, Cancel at top 6 | DOM | matches (now modal) |
| 06 | Contacts grid, 1280 | 33px rows, 25 per page | **33**, 25 | DOM | matches |
| 06 | Contacts grid, 390 | row pitch 56 | **57** | DOM | 1px over, recorded |
| 07 | Templates list, 1280 | icon→name 12px, pitch 39 | name x 285, pitch **39** | DOM | matches |
| 07 | Templates list, 390 | row pitch 55–56 | **55** | DOM | matches |
| 08 | Template editor, 1280 | 2-pane, chips + fallbacks | same | visual | matches |
| 08 | Template editor, 390 | 44px chips beside fallbacks | chips, fallback visible | visual (chip height from phase 6) | deviation 5 |
| 09 | Connections, 1280 | signature text x 847 | **847** | DOM | matches |
| 09 | Connections, 390 | card ≈130px | 174px | visual + phase-6 measurement | 44px targets, deviation 1 |
| 10 | Empty campaigns, 1280/390 | centred empty card + 2 CTAs | same | visual, real Quiet Studio state | matches |
| 11 | Start blocked, 1280/390 | red panel + reasons + disabled Start | 3 reasons, Start faded at 1280, step 2 outlined | visual | matches |

No drift was found in the 22 renders that is not already in the deviations or the phase-6
gaps below. The only visual difference between the old phase-6 renders and these is the
phase-7 fix set itself.

## Decisions recorded

1. **The 390 editor remounts across the 1024px line; unsaved edits are lost.** `isMobile ?
   <Dialog>…</Dialog> : editorPane` are different parents, so `StepForm` (keyed by step id)
   remounts and re-seeds from the saved step. Reaching it needs a resize across 1024 or a
   rotation that crosses it. Guarding it means lifting the form state above the breakpoint
   branch — a `step-form.tsx` refactor, not a one-liner — so it is a recorded gap. The dirty
   baseline fix removed the false prompt; it does not survive a remount.
2. **The shared Sheet/Dialog close stays a bare 16×16 ✕** (measured in the unpadded
   `Primitive.Close`), below the 24×24 WCAG 2.5.8 minimum. Waived per the brief: it is a
   secondary affordance (Escape and Cancel/Close are present, the step editor hides it), but
   it is the only pointer route out of a sheet for a user who cannot see the small target.

## Remaining gaps

Carried from phase 6, unchanged: its eight-item gap list (fixture sample data, the sheet
trigger as a board deviation, the desktop-only column manager, no signature action in the
template editor, no recipients status filter, dark mode rendered on a subset, the 174px
connection card, and the Space state), and its deviations 1–20 — 44px controls, the 16px
checkbox box, `Sent today` as text, the recipients bar wording, `+ Recipients` on a Draft head,
placeholder fallbacks, `Created …` where the board reads `Updated …`, and the rest.

New or still open in this phase:

- **The 1024 remount trade-off** and the **16×16 close** above.
- **Start blockers are UI-only:** `CampaignController::start` and `StartCampaign` do not check
  the rules the disabled Start and the panel enforce.
- **No index for the new aggregates.** The dashboard's 30-day email statement, the per-step
  metrics and the connections cap query all scan by `campaign_id`/table; the measured costs are
  in `reports/phase-2.md`, `phase-3.md` and `phase-5.md`, and the phase-5 covering index
  (`mailer_connection_id, dispatched_at`, 8.81 ms → 1.53 ms at 50k rows) is not migrated.
- **Not rendered:** the MySQL plans (SQLite only) and the 1024-cross remount itself.

## Space state after this run

- `/home/cua/work/campaigns-final` is no longer pure `086fc59`: the sweep's twelve file
  deletions and the edited `app.css` were copied in after the renders, and the post-sweep
  build ran there. It also carries the Space-only preview route and the phase-6 fixture
  database.
- `/home/cua/work/campaigns-verify` is `dc6dfbf` extracted from `git archive` (no preview
  route), with a real copied `vendor/` and a symlinked `node_modules/`; the final six checks
  ran there. A symlinked `vendor/` was tried first and is wrong: composer resolves its
  `baseDir` to the other tree and PHPUnit derives path-based class names, which produced 539
  spurious failures in 0.34s.
- `/home/cua/work/campaigns-p7` remains from the first run.
- `php artisan serve` on `127.0.0.1:8000` and headless Chromium on `127.0.0.1:9222` were
  stopped after the renders; both ports were re-checked free.
- The two source tarballs were deleted from the host and the Space; the
  `__preview-login` route is Space-only and the host tree and every commit are clean of it.
