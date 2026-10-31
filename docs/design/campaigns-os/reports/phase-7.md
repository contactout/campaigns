# Phase 7 — sweep, proof and the PR description (partial)

Worktree `/home/azri/projects/contactout/campaigns-design`, branch `design/campaigns-os`.
This run started from `04db541` and was killed by the host's OOM killer twice while the
Space ran the check set, so the phase is **partially done and explicitly unfinished**.
Nothing below is marked proven without the measurement or render behind it.

## Proven (measured, screenshot kept)

Provenance for every render in this section: `HEAD` archived with
`git archive 04db541` and extracted into a fresh Space tree
(`/home/cua/work/campaigns-p7`) with `node_modules/`, `vendor/`, `.env` and the phase-6
fixture `database/sqlite` moved over from `/home/cua/work/campaigns-phase6`. A
Space-only `/__preview-login?to=…` route was appended to `routes/web.php` for the renders
(never in the host tree, never committed). Chromium 156 headless over CDP, viewport
1280×900 or 390×844, `deviceScaleFactor: 1`, real `Input.dispatchMouseEvent` taps and
`Input.dispatchKeyEvent` keys. Screenshots are in
`docs/design/campaigns-os/reports/phase-7/`.

Builds used: **build A** = the phase-6 HEAD tree (no phase-7 change) → `BUILD_EXIT=0`,
`✓ built in 3.27s`; **build B** = one temporary instrumentation patch (not committed);
**build C** = build A plus the six fix files in §"Fixed in this phase" → `BUILD_EXIT=0`.

| # | Item | Measured | Evidence |
| --- | --- | --- | --- |
| 1 | P0-1 contacts grid scrolls at 1280 | Scroll container `scrollHeight 860 / clientHeight 735`; scrolled to `scrollTop 125` = its maximum. 25 rows. Last row `noah.novak21@example.com` is 33px tall at y 831–864; `Showing 1–25 of 1,284` at y 872–892 and `Previous` at y 868–896 — below the last row, not painted over. The `All contacts / APAC founders / Fintech CFOs / Webinar Oct 8 / + Add list` strip is visible in the render at the same height. Card y 128–900. | `p7-01-contacts-1280-scrolled.png` (build A) |
| 2 | P1-1 template names left-aligned at the board's x | Board scan (`07-templates-list-a-1280.jpg`, icon/stem columns y 175–192): icon box starts x 257, name starts **x 285** — a 12px gap, not 8. Phase-6 build measured name x **281** (row `gap-2`). Changed to `lg:gap-3`; re-measured **name x 285**, icon 257–273, link 285–1103, name span 285–456 (20px tall), `justify-content: flex-start`. | `p7-02-templates-1280.png` (build C) |
| 3 | P1-2 threaded checkbox toggles exactly once per label tap | **390:** label 358×44 at x 16, 44×44 wrapper, 16×16 box at x 30; one real tap at the text (156, 737) → `aria-checked` false → **true**; a second tap → **false**. **1280:** label 658×20 at x 581, box 16×16 flush at x 581 (the field edge, no indent); tap at (721, 792) → false → **true** → **false**. | `p7-03-threaded-390.png`, `p7-03-threaded-1280.png` (build A) |
| 4a | P2-5 dialog semantics at 390 | `role="dialog"`, **`aria-modal` was null** (see §Fixed), title `Edit step · Day 1`, box 390×844 at (0, 0), `aria-describedby` null, body `overflow: hidden`, `window.scrollTo(0, 400)` left `scrollY 0`, and 14 consecutive Tab presses from `Cancel` stayed inside the dialog (Cancel → Save → Apply template → subject → B → I → U → bullet → numbered → Link → Insert placeholder → Insert signature → body → `{{name}}` → `{{email}}`), every stop `inDialog: true`; the covered body siblings carry `aria-hidden="true"`. | `p7-04-editor-dialog-390.png` (build A, pre-fix) |
| 4b | P2-5 inline pane at `lg` unchanged | No dialog in the DOM; root `display: grid` with `grid-template-columns: 300px 692px`; sequence list 300×775 at x 240, pane 692×775 at x 564, footer 690×57 visible, mobile head row `display: none`. | `p7-04-editor-inline-1280.png` (build A) |
| 4c | Discard guard fires only on real edits | On build A, Escape and Cancel opened `Discard your changes?` **with no edits made** (see §Fixed). On build C, Escape and Cancel with no edits close the editor directly (`dialogs: 0`, `editorOpen: false`). On build A, editing the subject and pressing Escape opened `Discard your changes?` (title, `Cancel` / `Discard changes` buttons), kept the value (`Quixck idea for {{company}}`), and `Discard changes` then closed the editor; **that edited path was not re-measured on build C**. | `p7-04-editor-discard-guard-390.png` (build A — shows the bug, not the fix) |

Two corrections to the phase-6 report that the renders forced:

- The phase-6 claim that the 390 editor "gains role=dialog, aria-modal, …" was **false**:
  Radix/shadcn never set `aria-modal`, and it measured `null`. Fixed in the primitives.
- The phase-6 claim that Escape/Cancel are guarded by the discard dialog hid the opposite
  bug: the guard fired even when nothing had been edited.

## Fixed in this phase (six files, type-checked and linted, **not re-rendered**)

| File | Change | Why |
| --- | --- | --- |
| `resources/js/components/ui/dialog.tsx`, `ui/sheet.tsx` | `aria-modal="true"` on `DialogPrimitive.Content` / `SheetPrimitive.Content` | Measured null; an attribute on the stock primitive, no public API change. Applies to every dialog and sheet, which are all modal. |
| `resources/js/components/templates/rich-text-editor.tsx` | New optional `onCanonical(html)` prop; fires once per editor instance with `editor.getHTML()` | TipTap rewrites the stored markup it is given (`<br />` → `<br>`), so a form comparing raw strings reads a freshly opened document as edited. |
| `resources/js/components/campaigns/step-form.tsx`, `pages/templates/show.tsx` | A `savedBody`/`saved` body baseline set from `onCanonical`, used by `isDirty` instead of the raw prop | Root cause of the false "Discard your changes?" prompt: measured body 351 chars after mount against the stored 353 (`<br />` vs `<br>`). |
| `resources/js/pages/templates/index.tsx` | `lg:gap-3` on the template row | Name x 281 → 285, matching the board's 12px icon-to-name gap. |

Nothing else was changed. `git status` was clean before these edits.

## Decisions recorded

1. **The 390 editor remounts when the inline pane opens across the 1024px line; unsaved
   edits are lost.** This is a trade-off, deliberately not guarded in this run. The cause
   is `isMobile ? <Dialog>…</Dialog> : editorPane` in `sequence-editor.tsx`: the two
   branches are different parents, so `StepForm` (keyed by step id) remounts and its
   field state is re-seeded from the saved step. Reaching it needs a window resize across
   1024 or a rotation that crosses it (a phone's 390→844 does not). Guarding it means
   lifting the form state above the breakpoint branch, which is a refactor of
   `step-form.tsx`, not a one-liner; it is recorded as a gap rather than rushed.
   *Unmeasured:* the loss was not re-rendered after the six fixes.
2. **The shared Sheet/Dialog close stays a bare 16px ✕.** Measured target 16×16 in an
   unpadded `Primitive.Close` (`ui/sheet.tsx`, `ui/dialog.tsx`), below the 24×24 WCAG 2.5.8
   minimum. Left as-is per the brief and recorded as a waiver: it is a secondary
   affordance (Escape and Cancel/Close are present, and the step editor hides it), but it
   is the only pointer route out of a sheet for a user who cannot see the small target.

## Checks

```
# build A, phase-6 HEAD tree (before the six fix files)
$ npm run build            BUILD_EXIT=0    ✓ built in 3.27s

# build C, with the six fix files — run in the Space /home/cua/work/campaigns-p7
$ npm run check            CHECK_EXIT=0    All 158 files are correctly formatted
                                           Found no warnings or lint errors in 143 files
$ npm run types:check      TSC_EXIT=0      tsc --noEmit
```

`npm run check` and `types:check` ran on bytes identical to the host tree (md5 of all six
files compared both ways). **Build C** was run for the renders and reports `BUILD_EXIT=0`;
its timing was not captured.

**Not run this phase:** `php artisan test`, `pint --test`, `phpstan analyse`. No PHP class
changed (the only PHP change in the Space was the temporary preview route, never in the
host tree).

## Not done — the outstanding proof list

These are the phase-6 items and sweep tasks with **no** render or measurement behind them.
Nothing here may be reported as fixed until it is.

1. **Item 5 — 390 sticky selection bars.** Contacts and recipients with one row selected:
   the `Showing … / Previous / Next` row must sit above the bar. Phase-6 measured the bar
   at 65px with 68px reserved; not verified now.
2. **Item 6 — connections signature at 1280.** Needs `Signature` header x and trigger/value
   x to both read 847. Not rendered.
3. **Item 7 — `/settings/security` at 390.** `More` tab active and the sheet's `Settings`
   row current. Not rendered.
4. **Item 8 — emulated safe-area inset.** Chromium 156 exposes
   `Emulation.setSafeAreaInsetsOverride`, so this is testable: assert the tab bar's
   resolved bottom padding is non-zero with `viewport-fit=cover`. Not done.
5. **Item 9 — one dark render** of a screen phase 6 changed. Not done.
6. **Item 4 re-prove:** the `aria-modal` fix and the dirty-baseline fix were not re-rendered.
   Re-run: the dialog's `aria-modal` must be `"true"`; Escape and Cancel with no edits must
   close; an edited form must still be guarded. (Escape/Cancel-close with no edits **was**
   re-measured on build C through the probe; the `aria-modal` attribute and the
   edited-form guard were not.)
7. **Item 1 re-render on the final tree**, plus the 390 contacts scroll equivalent.
8. **Sweep, entirely undone:** dead-code usage greps and removal, the final 1280/390 board
   re-render comparison, `git log` message/repo-hygiene review (temp scripts, tarballs,
   fixture databases, Space artefacts), the hex-outside-`app.css` grep, the hardcoded-demo
   number grep, the `TODO` audit and the `.dark`-covers-`:root` token audit.
9. **`docs/design/campaigns-os/pr.md`** — not written. The brief for it stands: what the
   branch does, board → files table for all 20+ screens, deviations and accessibility
   waivers with numbers, the backend/data changes (dashboard aggregates, connections cap
   counter, per-step aggregates, shared status mapping, payload additions) with costs, what
   was verified and how, and the remaining gaps.

## Space state for the next run

- Tree: `/home/cua/work/campaigns-p7` (HEAD archive, deps and fixture DB), Space only.
- A `/__preview-login` route is appended to that tree's `routes/web.php` and **not** in the
  host tree; remove it before any `pint`/`phpstan`/`artisan test` run so those checks see
  the committed bytes.
- `php artisan serve` on `127.0.0.1:8000` and headless Chromium with
  `--remote-debugging-port=9222` were used for the renders and were then stopped to free
  the Space (ports 8000 and 9222 verified free). Start both again before rendering:
  `setsid nohup php artisan serve --host=127.0.0.1 --port=8000 > /tmp/p7-serve.log 2>&1 &`
  and
  `setsid nohup chromium --headless=new --remote-debugging-port=9222 --no-sandbox --disable-dev-shm-usage --disable-gpu --hide-scrollbars --user-data-dir=/tmp/p7-chrome --window-size=1280,900 about:blank &`.
- Harness: `/tmp/p7/cdp.mjs` (connect, viewport, goto, waitFor, shot, clickAt, key) and
  `proof1..4.mjs`, `probe-dirty.mjs`, `probe-state.mjs`. Screenshots in `/tmp/p7/shots/`,
  already copied into this report directory.
- Fixture note: the 25th contacts row is `noah.novak21@example.com`, not the
  `mei.patel18@example.com` the phase-6 report expected; `mei.patel18` is on the same page,
  mid-list. Data ordering, not a layout difference.

## Remaining gaps carried from phase 6

Unchanged by this run; see `reports/phase-6.md`: the sheet trigger and the shifted 390
title, 44px controls against the boards' 28–32, the 16px checkbox box, `Sent today` as
text without a meter, the connection-card height, the recipients bar wording, `+ Recipients`
on a Draft head, no recipients filter, the template editor having no signature action,
placeholder fallbacks at 390, `Created …` in the campaign subline, data-driven ordering,
the contacts pagination/select-all rows at 390, the step-editor body scroll, per-breakpoint
stat labels, the 57px bottom bar, the contacts flowing page, and the two remaining
empty-state labels. New in this phase: the 1024 remount trade-off and the 16×16 close.
