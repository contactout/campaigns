# Campaigns OS — implementation spec

Build contract for the Paper file **“Campaigns OS — ContactOut look”**
(`01M4Z5F8FY5S0Y2F45XQ2CWSAW`, page `p-1-0`, 26 artboards, 4,179 nodes).

## Authority

- **These boards are the visual contract.** Where they conflict with
  `docs/prototype/theme-plan.md` (present only in the main checkout at
  `/home/azri/projects/contactout/campaigns/docs/prototype/theme-plan.md`, untracked),
  the boards win. That plan describes a different direction — tinted `#f5f7ff` canvas,
  white cards, 196px sidebar, 14px body, template card grid, provider cards — and is
  superseded. Its §5 (backend aggregate for open/reply rates) is still the agreed
  approach if that work is ever needed; phase 1 does not need it.
- **Rendered references:** `/home/azri/projects/contactout/.design-refs/campaigns-os/`
  holds a 1x screenshot of every artboard, named `NN-screen-{a|b|390|1280}`. Read the
  relevant ones before touching a screen.
- **Do not call Paper MCP.** The weekly quota is exhausted and resets 24h after this
  spec was written. If it has reset, `get_jsx({ fileId, nodeId, includeNodeIds: false })`
  returns exact CSS per artboard; artboard ids are listed per screen below.

## Tokens (`resources/css/app.css`, `:root`)

The file defines no Paper design tokens — the board maps shadcn CSS variables. Set these
values in `:root`. Keep the existing `@theme` mapping untouched (names already match) and
keep the `.dark` block exactly as it is; nothing here designs a dark variant.

| Variable | Value | Use |
| --- | --- | --- |
| `--background` | `#ffffff` | page, table, cards |
| `--foreground` | `#18181b` | body text |
| `--primary` | `#3222b1` | filled CTA, active tab text/underline |
| `--primary-border` (new) | `#6552ff` | 1px border on primary buttons |
| `--primary-foreground` | `#ffffff` | text on primary |
| `--secondary` | `#f0eeff` | soft CTA fill (Import, Upgrade) |
| `--secondary-foreground` | `#3222b1` | soft CTA text |
| `--accent` | `#f0eeff` | active nav, selected row/cell, hover |
| `--accent-foreground` | `#3222b1` | text/icon on accent |
| `--muted` | `#f4f4f5` | neutral pill, table header tint |
| `--muted-foreground` | `#71717a` | labels, column headers |
| `--border`, `--input` | `#e4e4e7` | grid lines, outlined buttons |
| `--ring` | `#b8afff` | focus ring, selected cell border |
| `--destructive` | `#dc2626` | errors, delete |
| `--success` (new) | `#16a34a` | Active / verified |
| `--banner` (new) | `#f8f7ff` | promo / info strip |
| `--sidebar` | `#ffffff` | sidebar bg |
| `--sidebar-accent` | `#f0eeff` | active sidebar pill |
| `--radius` | `0.375rem` (6px, was 10px) | `rounded-lg/md/sm` = 6/4/3 |

Borders are 1px `#e4e4e7` everywhere. Shadows: none, except dialogs `0 8px 24px #18181b14`.
Pills and status badges use 4px radius; avatars/step icons `999px`.

## Type

Font stack: **Inter** (replaces Instrument Sans in `@theme --font-sans`; update the Bunny
font plugin in `vite.config.ts` to load Inter 400/500/600/700). JetBrains Mono stays
available for code-ish values.

| Role | Size / weight / tracking |
| --- | --- |
| H1 page title | 20px / 700 / `-0.01em` |
| H3 section | 16px / 600 |
| Body, table cells | 13px / 400 / line-height 20px |
| Labels, column headers | 12px / 500 / `#71717a` |
| Tabs | 13px / 500 |

Density: table row 36px (grid cells 32px), toolbar buttons 32px, tab bar 40px, page
padding 24px (16px on mobile), mobile bottom tabs 56px. Mobile boards render body text a
step larger than the 13px desktop scale; treat that as a separate mobile type scale to
settle in phase 6.

## Shell

- Sidebar: **216px**, white, 1px right border `#e4e4e7`, no inset card/rounding/shadow.
  Brand row (logo mark + “Campaigns OS”), team-switcher as a bordered 6px-radius card
  (“Northwind Growth / Team · 4 members”), six nav items at 36px with 6px radius,
  `#71717a` labels, active = `--sidebar-accent` pill + `#3222b1` label and icon,
  `Settings` pinned bottom, then the user block (avatar, name, email).
- No page bar. Product pages start with the page head: H1 20/700, 13px muted subtitle,
  actions right-aligned, inside 24px padding. The existing breadcrumb bar
  (`AppSidebarHeader`) must become opt-out per page rather than deleted, so settings,
  teams and auth keep working; campaigns pages opt out.
- Option A (sidebar) is chosen over Option B (top tabs). Option B is not built. Note that
  the current app renders `Sidebar variant="inset"` (`resources/js/components/app-sidebar.tsx`)
  which produces the rounded `SidebarInset` card — switch to the default variant, and
  `SIDEBAR_WIDTH` in `resources/js/components/ui/sidebar.tsx` from `16rem` to `216px`.
- Keep all page markup on semantic tokens (`bg-background`, `text-muted-foreground`, …).
  No hex in page or component files; hex lives only in `app.css`.

## Status system

One badge primitive, 4px radius, 12px/500, 22px tall.

| Semantic | Text | Fill | Notes |
| --- | --- | --- | --- |
| success (`Active`, `Replied`, `Verified`) | `#35754e` | `#dcfce7` | 5.0:1 |
| warning (`Opened`, `Do not contact`) | `#854d0e` | `#fef3c7` | clears 4.5:1; the board's `#9b692f` on `#fce4af` measures 3.8:1 and fails |
| info/brand (`Contacted`) | `#3222b1` | `#f0eeff` + 1px `#b8afff` border | the border keeps the pill readable on selected rows, where the row tint is the same lavender |
| danger (`Bounced`, `Failed`) | `#dc2626` | `#fef2f2` | |
| neutral (`Draft`, `Completed`, `Stopped`, `Archived`, `New`, `Not contacted`, `Unsubscribed`) | `#71717a` | `#f4f4f5` | |

Every status gets a pill; the boards show `New` and `Contacted` sometimes as bare text.
Do not reproduce that — it is a defect, not a pattern.

## Data mapping (verified against the codebase)

- **Campaign status** — `app/Enums/CampaignStatus.php` matches the boards exactly
  (`Draft, Active, Stopped, Completed, Archived`).
- **Contacts grid** — `app/Enums/ContactStatus.php`: `NotContacted, Contacted, Replied,
  Bounced, Unsubscribed, DoNotContact`. Boards label `NotContacted` as **“New”**; keep the
  board's copy, drive the value from the enum, and keep `Unsubscribed` in the set (the
  board omits it — show it, it exists).
- **Recipients tab** — `app/Enums/RecipientStatus.php` is `Active, Pending, Bounced,
  Completed, Error, Paused, Replied, Unsubscribed, Archived`; `campaigns/show.tsx` renders
  `recipient.status_label` from it. The board instead shows `Contacted / Opened / Not
  contacted`. Before building, inspect `CampaignRecipientController` and the
  `CampaignRecipient` payload: if contact-level or email-level state is available, derive
  the board's labels; if not, keep `RecipientStatus` labels and record the deviation in
  the phase report. **Do not add columns or migrations without asking.**
- **Connections** — `app/Enums/MailerConnectionStatus.php` is `Pending, Active,
  Deactivated, Disconnected`. The board's `Verified`/`Failed` must be derived (for example
  `Active` + `exception_type === null` → Verified, `exception_type !== null` → Failed);
  confirm against `app/Models/MailerConnection.php` and report if the data does not exist.
  `Sent today 142 / 400` maps to `sending_limit` + `sending_limit_refreshed_at`; check for
  an existing count query before writing one.
- **Campaign detail stats** — `CampaignStats` already carries `emails_sent, opened,
  clicked, unsubscribed`; the board's `Sent`/`Opened %` cards should use it. The board's
  numbers do not reconcile (steps weight to 41.5% opened, card says 38%) — sample data,
  ignore.

## Screens

Reference screenshots are the copy source; this list fixes structure and states.

1. **01 Dashboard · A · 1280** (`5T-0`) — greeting H1 + date line, full-width invitation
   banner (`You have 1 pending team invitation from Harbor Labs` + `Review`), four stat
   cards (Emails sent 30d, Open rate, Reply rate, Active campaigns) with green delta,
   two-panel row: “Finish setting up” checklist with progress bar and `Go to Campaigns`
   secondary button, and “Jump back in” rows (tinted 32px icon tile, title, subline,
   chevron).
2. **02 Campaigns list · A · 1280** (`AP-0`) — page head + `New campaign` primary; toolbar
   row: search 260px, `All statuses` select, `6 campaigns` count right-aligned; table
   `Name / Status / Steps / Recipients / ⋮` with 36px rows, name + `Updated …` subline,
   ghost pagination footer `Showing 1–6 of 6` + Previous/Next inside the same bordered
   container. **02 B** (`AQ-0`) is the Option-B top-tab variant — reference only.
3. **10 Empty · No campaigns · A · 1280** (`1LJ-0`) — empty state inside the same
   container: 48px tinted rounded icon, `No campaigns yet`, one-sentence body, secondary
   `Browse templates` + primary `New campaign`. Drop the duplicate primary in the header
   or the panel — one primary per screen.
4. **03 Campaign detail · Steps · A · 1280** (`AS-0`) — back chevron + title + status pill,
   action cluster (`Edit`, `Duplicate`, `Archive`, `Stop`, `Add recipients` primary); tab
   bar `Steps | Recipients` with 2px active underline; four stat cards (Steps, Recipients,
   Sent, Opened); meta line `Sends from … (UTC+08:00) Kuala Lumpur` + `Add step`; day
   groups (`Day 1 · 09:00`) with step rows: drag handle, tinted mail icon, subject,
   `Email · New thread` subline, right-aligned `Sent / Opened / Replied` columns in fixed
   lanes, `⋮`. **03 B** (`AT-0`) is the Option-B variant — reference only.
5. **04 Campaign detail · Recipients · A · 1280** (`AV-0`) — same head; tab `Recipients`;
   bulk bar `2 selected | Remove` (danger), search + `Add recipients` on the right; table
   `checkbox / Name / Email / Status / ⋮` with selected-row tint. Remove the duplicate
   `Add recipients` in the campaign header on this tab.
6. **05 Sequence step editor · A · 1280** (`1L9-0`) — two panes: left `SEQUENCE` list with
   the active step tinted + `Add step`; right editor card with `Edit step · Day 3` header
   and `Esc` hint, `Apply template` input, `Subject`, `Body` rich toolbar
   (`B I U • 1. Link | + Placeholder | Signature`) + editor, placeholder chips, `Day` and
   `Time` fields, `Send as a reply in the same thread` checkbox, footer `Cancel` +
   `Save step`. The board also needs a step `Delete` affordance (present only on the 390
   board) — add it to the desktop footer as a destructive ghost.
7. **06 Contacts grid · A · 1280** (`1LA-0`) — full-height workspace: page head +
   `Import CSV` secondary + `Add contact` primary; toolbar `Filters`, selection count,
   `Add to campaign`, `Do not contact`, `Delete` (danger, not neutral), right-aligned
   search + settings icon; six-column grid with select-all, 32px cells, roster avatars on
   narrow widths; the bottom list-tab strip (`All contacts / Fintech CFOs / APAC founders
   / Webinar Oct 8 / + Add list`) sits at the bottom of the content area. The board clips
   the Title column past the 1280 edge — that is a defect: truncate with `min-w-0` and a
   tooltip instead of clipping, and drop the stray `T`/`@`/`#`/`●` header glyphs.
8. **07 Templates list · A · 1280** (`1LD-0`) — page head + `New folder` + `New template`;
   search + `14 templates · 3 folders`; folder groups as tinted header rows
   (`Cold outreach 3`) with child rows indented one step (folder icon, name, `Updated …`,
   `⋮`, `Draft` pill where applicable).
9. **08 Template editor · A · 1280** (`1LE-0`) — back + title + `Draft` label, `Discard` +
   `Save template`; `Name` + `Folder` fields, `Subject`, body editor with the same toolbar;
   right rail `Placeholders` card listing chips with their fallback values
   (`there`, `your team`, `Sam`) and `+ Add placeholder`.
10. **09 Connections · A · 1280** (`1LH-0`) — page head + `Add SMTP` + `Connect Gmail or
    Outlook` primary; danger banner (`Mailgun relay failed verification: 535 …` + `Retry`);
    table `Name / Type / Server / From / Signature / Status / Sent today` with cap meters
    (violet bar + `142 / 400`), footer note `Daily sending caps reset at 00:00 in each
    connection's timezone.`
11. **11 Error · Start blocked · A · 1280** (`1LL-0`) — detail with a danger panel listing
    blocking reasons (`Step 2 (Day 3) has an empty subject`, failed connection, `4
    recipients are marked Do not contact and will be skipped`), inline `Edit step 2` /
    `Go to Connections`, the offending step row outlined in red with `(no subject)`, and
    `Start` disabled with the panel as its reason.
12. **390 boards** — one per screen above, plus the shared bottom tab bar
    (`Home / Campaigns / Contacts / Templates / More`, active = violet icon + label, 56px).
    Phase 6.

## Non-goals and deviations

- `.ai/rules/pages.md` asks for a centred `max-w-6xl` container; the boards are full-width
  with 24px padding. Follow the boards, note the deviation in the phase report, and leave
  the rule file alone.
- Do not delete the `.dark` block, the appearance settings, or the auth/settings styling.
  Only product screens are redesigned.
- Phase 1 must not touch backend PHP: the campaigns list board needs no open/reply rates.
- Modals and creation flows (`New campaign`, `New folder`, `Add contact`, `Import CSV`,
  `Filters`, `Add SMTP`, OAuth connect, `Add placeholder`) have no boards. Keep the
  existing workings; do not invent new designs for them.

## Amendments — phases 1–2 fix round

Recorded after the independent review of phases 1–2 (verdict BLOCK). Each item is a deliberate
deviation from the boards or from the original spec text, with the measurement that justifies
it. These override the corresponding lines above.

### Dark tokens (additive only)

`.dark` gained counterparts for the tokens phases 1–2 introduced. No existing `.dark` value was
changed. Contrast is WCAG 2.x against `oklch(0.145 0 0)` = `#0a0a0a` unless noted.

| Token | Dark value | Measured contrast |
| --- | --- | --- |
| `--banner` | `#221d3a` | dark violet surface |
| `--banner-foreground` (new) | `#c9c2ff` | 9.69:1 on the banner |
| `--primary-border` | `#8b7dff` | 6.10:1 on the page, 3.11:1 on the dark primary button |
| `--success` | `#4ade80` | 11.36:1 |
| `--success-foreground` | `#86efac` | 14.10:1 |
| `--badge-success` / `--badge-success-foreground` | `#14351f` / `#86efac` | 9.59:1 on the fill |
| `--badge-warning` / `--badge-warning-foreground` | `#3a2c0a` / `#fcd34d` | 9.43:1 on the fill |
| `--badge-danger` / `--badge-danger-foreground` (new) | `#3b1518` / `#fca5a5` | 8.47:1 on the fill |
| `--badge-neutral-foreground` (new) | `#d4d4d8` | 10.24:1 on dark muted |

Three paired foreground tokens were added beyond the tokens the review named, because the
existing ones cannot express the semantic: `--banner-foreground` (the banner text used
`--primary`, which is near-white in dark rather than light violet), `--badge-danger-foreground`
(the pill read `--destructive`, which is dark red in dark and fails on a dark fill), and
`--badge-neutral-foreground` (the pill read `--muted-foreground`, which is 4.40:1 on `--muted`,
and the agreed `#52525b` cannot be expressed without a token). Their light values are `#3222b1`
on `#f8f7ff` (9.87:1), `#b91c1c` on `#fef2f2` (5.91:1) and `#52525b` on `#f4f4f5` (7.03:1).

### Focus indicator — accessibility waiver

The board's `--ring` `#b8afff` is **1.98:1** on white and `--ring-halo` `#f4ebff` is **1.16:1**,
both below the WCAG 2.x 3:1 non-text minimum. The ring values are therefore replaced:

- `--ring` is `#6552ff` in `:root` (**4.99:1** on white).
- The focus indicator is a **2px** ring on buttons, inputs, selects, checkboxes and row links,
  replacing the old 3px halo.
- The ring colour is `--primary-border` (`#6552ff` light, `#8b7dff` dark, **6.10:1** on
  `#0a0a0a`), because `.dark --ring` is frozen at `oklch(0.439 0 0)` (**2.53:1** on the dark
  page) and this round may not change existing dark values.
- `--ring-halo` was removed in phase 7 (see the token-removal amendment below): nothing
  referenced it, on the boards or in the code.

### Board measurements that override the spec text

| Element | Board measurement | Shipped |
| --- | --- | --- |
| Empty-state icon tile | 64px (10 A x 716–779) | 64px (spec said 48px) |
| Table row pitch | 44px (02 A dividers y 211/255/299/343/387/431) | 44px |
| Table header / footer | 35px / 52px | 35px / 52px |
| Table cell padding | 16px ("Name" x 258 against border x 241) | `px-4` |
| Stat-card gap | 12px (01 A card edges 240/484, 497/741) | `gap-3` |
| Stat value | 24px (cap height 17px vs 14px for the 20px H1) | `--text-stat` 24px |
| Page head | title block starts at 16px, centred on the 32px button (H1 cap top y 23) | `-mt-2`, `items-center` |
| Sidebar nav pitch | 36px (01 A pill 119–152, next 155–188) | 36px, `gap-0` |
| Sidebar brand mark | 24px (01 A x 12–35, y 16–40) | `size-6`, 6px radius |
| List dividers | inset 18px (Jump-back-in divider x 835–1237 inside card 816–1255) | `mx-5` on the list |

`--text-h1/h3/body/label` replace the `text-[20px]/[16px]/[13px]/[12px]` one-offs;
`--text-stat` is the 24px stat role. Brand and sidebar sublines that used 14px/11px now use
`text-body`/`text-label` to stay on the scale (a 1px change each).

### Round 2 amendments

- **Static sidebar (R2-1).** `collapsible="none"` renders a `sticky top-0 h-svh` column with a
  1px `--sidebar-border` right edge. Without the sticky/h-svh pair the column stretched to the
  document height and Settings/user scrolled away on lists taller than the viewport.
  Verified on a 25-row list: document 1348px, viewport 813px, after `scrollY 535` the sidebar
  top is still 0, height 813, and the user block is inside the viewport.
- **Info pill border (R2-3).** The `info` variant no longer reads `--ring`; it has its own
  `--badge-info-border` (`#b8afff`, dark `#8b7dff`, 4.46:1 on the dark info fill). Changing
  `--ring` cannot move the pill border again.
- **Text tokens carry no line height (R2-4).** `--text-h1/h3/body/label/stat` set size (and h1
  tracking) only; `leading-*` is explicit at each call site. The tokens' own line heights were
  overriding `leading-tight` inside the shell and made the team card 54px against the board's
  50px. Two roles were added: `--text-brand` 14px (the wordmark is 14/700 on the board, not
  13/600) and `--text-caption` 11px (settings-only chips stay at their pre-refactor size).
- **Stat value line height (R2-6).** `text-stat leading-7` (24/28) makes the card 84px, matching
  01 A; the earlier 32px line height made it 88px.
- **Destructive focus (R2-0).** The destructive button variant no longer carries
  `focus-visible:ring-destructive/20`; the base 2px `--primary-border` ring (4.99:1) now applies
  to confirm buttons too. The badge variant keeps the class because badges are not focusable.
- **Out-of-range page (R2-7).** An empty page with no filters says "This page has no campaigns"
  with a "Back to page 1" link; the filtered copy and `Clear filters` only appear when a filter
  is active.
- **Collapse plumbing (R2-2, accepted).** `SidebarTrigger` remains for the mobile sheet and the
  Ctrl/⌘+B shortcut and `sidebar_state` cookie are left alone. With a static sidebar the desktop
  trigger and shortcut have no visible effect; a stale `false` cookie can still move `NavUser`'s
  menu placement. Revisit when the 390 layout lands.
- **Connections pills vs needs-attention (phase 5 note).** Deactivated and Disconnected render
  neutral pills but count as "needs attention", and an Active connection with an exception is
  still green. The Verified/Failed derivation in phase 5 must reconcile the pill and the count.

### Token removal (phase 7)

`--chart-1..5` and `--ring-halo` were removed from `:root`, `.dark` and the `@theme` mapping in
phase 7. Neither had a reference in `resources/js`, `resources/views` or `app` (no `chart-${…}`
either), and no board has a chart; the focus treatment had already stopped using the halo when
`--ring` gained its 2px indicator. This overrides the `--chart-1..3` row in the token table and
the dark `--ring-halo` row above.

### Contacts grid (phase 4) — 32px cells, 33px pitch, `px-3`

Board 06 A's rules are at y 164, 197, 230, 263, 296, 328, 361, 394, 427, 460, 492, 525,
558 (x 215–1279), a 32.8px mean pitch: **32px cells plus the 1px rule**. Cell text starts
11–12px inside the column rules (Name 291 against the divider 279, Email 481 against 469,
Phone 701 against 689, Company 1001 against 989, Title 1151 against 1139), so the cells use
`px-3`. The phase-4 brief's "44px rows / `px-4` cells" are the 02 A campaigns-table
measurements (dividers 211/255/299/…, Name 258 against 241) and do not apply here. Phase 6
must keep 33px rows and `px-3` for the contacts grid rather than restoring the table
density.
