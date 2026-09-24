# Migration Plan — MMOS (Open-Source Email Campaign)

Status: draft for review
Source of truth: `../contactout_website` (branch `master`, module `modules/mail-merge`)
Target: this repo (`mmos`)

---

## 1. Purpose

`mmos` will become an **open-source email outreach / campaign tool**, derived from the
mail-merge feature in `contactout/contactout_website`. It is **not a 1:1 port**:

- UI look & feel should be **similar**, but rebuilt on the MMOS design system.
- Proprietary ContactOut infrastructure (paid gates, MTA/SMS, CRM/lead enrichment,
  internal OAuth apps, Slack escalation, S3, hard-coded ContactOut addresses) is **dropped**.
- Frontend is **rewritten** for React 19 / Inertia v3 / Tailwind v4 / TS 5 (source is
  React 16 / Inertia 1 / Tailwind 3 / TS 4.2).

---

## 2. Stacks

| | Source (`contactout_website`) | Target (`mmos`) |
|---|---|---|
| Framework | Laravel (older major) | Laravel 13 |
| PHP | 8.x | 8.3 (guidelines say 8.4) |
| Frontend | React 16, TS 4.2 | React 19.2, TS 5.7 |
| Inertia | `@inertiajs/react` 1.3 | `@inertiajs/react` 3 |
| CSS | Tailwind 3 + `tailwind-styled-components` + `@emotion/styled` | Tailwind 4 (CSS-first) + shadcn/ui + `cva` + `cn()` |
| HTTP client | `axios` (+ `humps`) | Inertia v3 XHR / `useHttp` |
| Auth | ContactOut session + paid feature gates | Fortify + teams (starter kit) |
| Queues | Redis + Horizon | TBD: database by default, Redis optional |
| State | local state + `react-query` v3 | React 19 + Inertia props |
| Tests (FE) | Jest 29 (~130 files) | Vitest (rewritten) |
| Tests (BE) | Pest (`Contactout\MailMerge\Tests`) | Pest 5 |

Source frontend lives in `resources/assets/js/dashboard/` (+ `modules/mail-merge/resources/ts`).
Target frontend lives in `resources/js/` with Wayfinder-generated routes.

---

## 3. What exists in the source (condensed inventory)

### 3.1 Backend

Feature module: `modules/mail-merge` — PSR-4 `Contactout\MailMerge\`.

- **Models** (table prefix `mm_`): `Campaign`, `Touch` (a campaign step), `Recipient`,
  `Email` (one send unit per recipient×touch), `MailerConnection` (sending inbox),
  `Template`, `Folder`, `Signature`, `Placeholder`, `Attachment`, `Task`, `EmailOpen`,
  `Link`, `LinkClick`, `Unsubscribe`, `Activity`, `Report`, `Prompt`, `Personalization`,
  `Setting`, plus the **contacts/sheets CRM subsystem** (`Contact`, `ContactIdentity`,
  `ContactProperty`, `Sheet`, `ContactSheet`) and **spam-test** (`SpamTest*`).
- **Enums/States**: `CampaignStatus`, `RecipientStatus`, `EmailStatus`, `TouchType`,
  `TaskStatus`, `MailerType`, … modelled with `spatie/laravel-model-states`.
- **Pipeline**: ~40 jobs (`SendEmail`, `ScheduleStepJob`, `ScheduleRecipientStep`,
  `CheckEmailStatus`, `TrackEmailOpen`, `TrackLinkClick`, `ProcessUnsubscribeRequestJob`,
  `SendCampaignSms`, `ProcessPersonalizationJob`, …), ~70 events, ~50 listeners, ~35
  console commands, a scheduler in `MailMerge::schedule()`.
- **Mailers**: Gmail API, Microsoft Graph (Outlook), SMTP/IMAP (`webklex/php-imap`).
  Base `mm_*` migrations are **absent from the repo** (squashed); schema recoverable from
  `database/schema/mysql-schema.sql`.
- **Routes**: module `routes/{dashboard,api,extension,shared}.php`; `routes/api_campaigns.php`
  (public v1 API); admin + sales + search hooks in the main app.
- **Config**: `modules/mail-merge/config/mail-merge.php` (queues, rate limits, AI, uploads,
  tracking, sending-limit tiers, anti-spam, connectivity checks).

### 3.2 Frontend

Module: `resources/assets/js/dashboard/mail-merge/` — **538 files**.

- `pages/mail-merge/*.tsx` Inertia pages: `campaign-index`, `campaign-show`,
  `campaign-edit-v2`, `email-settings`, `recipient-index`, `tasks-index`, `sheets`,
  `template-index`, `template-editor`, `spam-test-*`, `connect`, `campaign-extension`.
- `components/`: `campaign/`, `editor/` (the big one; `campaign-steps.tsx` is 1642 lines),
  `floating-editor/`, `recipient/`, `recipients-grid/` (TanStack table + virtual),
  `recipients-sheet/` (CRM), `add-to-campaign/`, `connect/` (incl. `smtp-settings.tsx`),
  `spamtest/`, `task/`, `template/`, `onboarding/` (Joyride), `recipient-timezone-picker/`.
- `api/` axios layer (~23 files; `link.ts` is empty/dead).
- Styling: `styled.tsx` + per-dir `styled.ts(x)` (`tailwind-styled-components`), some
  `@emotion/styled`, plus scoped CSS `mail-merge.css`.
- Key third-party: TinyMCE, `@tanstack/react-table` + `react-virtual`, `@dnd-kit/*`,
  `react-select`, `react-datepicker-new`, `react-joyride`, `dayjs`, `uuid`, `humps`.

---

## 4. Target architecture (MMOS)

Mirror the existing **`Teams/` vertical slice** conventions:

| Concern | Where |
|---|---|
| Migrations | `database/migrations/` (anonymous class, `constrained()` FKs) |
| Models | `app/Models/` (`#[Fillable]` attributes, `casts()` method, PHPDoc `@property`) |
| Enums | `app/Enums/` (TitleCase cases, `label()`/helpers) |
| DTOs | `app/Data/` (`readonly class`, promoted ctor props) |
| Actions | `app/Actions/Campaigns/…` (single-purpose `handle()`; `DB::transaction`) |
| Policies | `app/Policies/` (auto-discovered) |
| Requests | `app/Http/Requests/Campaigns/…` |
| Controllers | `app/Http/Controllers/Campaigns/…` (+ `Api/V1/…` if a public API is wanted) |
| Pages | `resources/js/pages/campaigns/*.tsx` |
| Components | `resources/js/components/campaigns/…` + shadcn `components/ui/*` |
| Hooks | `resources/js/hooks/` |
| Types | `resources/js/types/` (domain types in their own file, re-exported) |
| Routes | `routes/campaigns.php` (loaded from `routes/web.php`), Wayfinder-generated TS |
| Tests | `tests/Feature/Campaigns/…`, `tests/Unit/…` |

Conventions to obey (from `AGENTS.md` + starter kit):
- Wayfinder route/action functions instead of hardcoded URLs.
- `Inertia::render()` only (no Blade pages outside `app.blade.php`).
- `vendor/bin/pint --dirty --format agent`; PHPStan level 7.
- No new base folders / dependency changes without approval.
- Pest tests with factories; `assertInertia(...)`.
- shadcn/ui + `cn()` for styling; semantic Tailwind tokens; dark mode via `.dark`.

> `.ai/rules/` does **not** exist in this repo. Rules above come from `AGENTS.md`.

### 4.1 Ownership model (decision needed)

Source scopes campaigns to `user_id` + `company_id` with paid feature gates. MMOS has
teams. Options:

- **A. User-owned** (simplest OSS story): `user_id` FK. Teams ignored for campaigns.
- **B. Team-owned**: `team_id` FK, reuse `EnsureTeamMembership` + `SetTeamUrlDefaults`,
  URL prefix `{current_team}`. More consistent with where MMOS is heading.

**Recommendation: B**, so the feature composes with the existing team-prefixed dashboard.

---

## 5. Scope — in / deferred / out

| Area | Decision | Notes |
|---|---|---|
| Campaign CRUD (draft/show/edit/start/stop/archive/duplicate) | **In** | Core |
| Campaign steps ("touches") + scheduling/timezone | **In** | Core; email steps first |
| Recipients: manual add, CSV import, grid, status | **In** | Core |
| Templates + folders + placeholders + signatures | **In** | Core |
| Mailer connections: **SMTP/IMAP** | **In** | Self-hostable, OSS-friendly |
| Sending engine (jobs, rate limits, scheduling, threads) | **In** | Hardest phase |
| Tracking: open pixel, link click, unsubscribe | **In** | Core |
| Reply/bounce detection (IMAP polling) | **In** | Needs IMAP mailer |
| Gmail API + Microsoft Graph mailers | **Deferred** | Needs own OAuth apps/creds |
| Tasks / non-email steps (call, LinkedIn, manual) | **Deferred** | Data model kept, UI later |
| AI composer / personalization (OpenAI/Prism) | **Deferred** | Optional adapter, off by default |
| Spam-test (provider inboxes) | **Out of v1** | External/paid infra; content scan only |
| SMS via Dialer/Telnyx | **Out** | Proprietary module; not portable |
| Contacts/sheets CRM subsystem | **Out of v1** | Large; revisit later |
| Lead/lists/extension "add to campaign" | **Out** | ContactOut ecosystem |
| Admin panel, sales dashboard, feature/billing gates | **Out** | Not open-source relevant |
| Marketing static "email campaigns" page | **In (last)** | Public-facing, low risk |

Open questions for the owner are listed in §14.

---

## 6. Source → target mapping (examples)

| Source | Target |
|---|---|
| `Contactout\MailMerge\Models\Campaign` (`mm_campaigns`) | `App\Models\Campaign` (`campaigns`) |
| `Models\Touch` | `App\Models\CampaignStep` (rename "touch" → "step" for clarity) |
| `Models\Recipient` | `App\Models\Recipient` (`campaigns_recipients` or `recipients`) |
| `Models\Email` | `App\Models\CampaignEmail` |
| `Models\MailerConnection` | `App\Models\MailerConnection` |
| `Actions\Campaign\StartCampaignAction` | `App\Actions\Campaigns\StartCampaign` |
| `Http\Controllers\Api\CampaignController` | `App\Http\Controllers\Campaigns\CampaignController` |
| `routes/api.php` `/api/email/...` | JSON routes in `routes/campaigns.php`, or Inertia v3 `useHttp` against Wayfinder routes |
| `resources/assets/js/dashboard/mail-merge/**` | `resources/js/pages/campaigns/**` + `resources/js/components/campaigns/**` |
| `campaign-index.tsx` | `pages/campaigns/index.tsx` |
| `campaign-edit-v2.tsx` | `pages/campaigns/edit.tsx` |
| `styled.tsx` primitives | shadcn/ui + shared `components/campaigns/*` |
| `api/*.ts` (axios) | `useHttp` / `router` + Wayfinder functions |
| TinyMCE editor | TBD: Tiptap (recommended, MIT) or keep TinyMCE |
| `humps` decamelize | drop; standardise camelCase JSON props |
| Feature gates / `campaigns bonus` | drop |

Naming: prefer `campaign_steps` over "touches" going forward, but keep source vocabulary
in the plan where it aids traceability.

---

## 7. Translation rules

**React 16 → 19**
- `ReactDOM.render` → `createRoot` (MMOS already does this).
- Drop `defaultProps`, `React.ForwardRefRenderFunction` legacy patterns.
- `@testing-library/react` v11 → v16; rewrite Jest tests to Vitest/RTL.

**Inertia 1 → 3**
- Event names changed (`invalid`→`httpException`, `exception`→`networkError`).
- `router.cancel()` → `router.cancelAll()`; `Inertia::lazy` → `Inertia::optional()`.
- Prefer `<Form>`/`useForm` + Wayfinder `.form()` for mutations; `useHttp` for
  standalone JSON calls. Do not port the axios `request.ts` wrapper as-is.

**Tailwind 3 → 4**
- No `tailwind.config.js`; use `@theme`/`@source` in `resources/css/app.css`.
- `tailwindcss/nesting` PostCSS plugin removed.
- **`tailwind-styled-components` has no v4 support** → replace with shadcn/ui + `cva` + `cn()`.
- Drop `@emotion/styled` usage.
- Map the source custom palette/screens to MMOS tokens (see §11).

**TypeScript 4.2 → 5.7**
- Prefer `const` objects + union types over `enum` (source uses enums).
- Upgrade `@types/*` with React 19.

**HTTP/JSON**
- Decide camelCase vs snake_case: source uses `humps`; MMOS uses camelCase props.
  Standardise on **camelCase at the boundary** (matches existing controllers).

---

## 8. Phased plan

Each phase = one or more PRs, ends green (Pest + Pint + `npm run check`) and demoable.

### Phase 0 — Foundation
- Confirm ownership model (§4.1) and queue driver.
- Reconstruct base migrations from source `mysql-schema.sql` (campaigns, campaign_steps,
  recipients, campaign_emails, mailer_connections, templates, folders, signatures,
  placeholders, attachments, email_opens, links, link_clicks, unsubscribes, settings).
- Port enums (`app/Enums`), DTOs (`app/Data`), models + factories + policies.
- `routes/campaigns.php` skeleton + dashboard nav entry.
- Tests: model relationship/cast/scope tests; migration tests.
- Exit: models migrate + factories + `CampaignPolicy` + tests green.

### Phase 1 — Campaign core vertical (UI)
- `CampaignController` (index/store/show/update/destroy) + start/stop/archive/duplicate.
- Form Requests, Actions, `CampaignResource` (if a JSON API is kept).
- Pages: campaign list, campaign show, campaign editor shell (steps list, no sending yet).
- Wayfinder routes; shadcn-based list/summary/tabs; create-campaign modal.
- Tests: controller/feature tests + Inertia assertions; component tests (Vitest).
- Exit: create/edit/duplicate/delete a campaign end to end in the browser.

### Phase 2 — Recipients
- Add/remove recipients, CSV import, recipient grid (TanStack table + virtual),
  recipient statuses, placeholder resolution per recipient.
- Tests: import validation, grid model, status transitions.
- Exit: import a CSV, see recipients, edit cells, remove.

### Phase 3 — Templates, folders, signatures, placeholders
- Template CRUD + editor shell, folders, signature manager, campaign placeholders.
- Exit: save/apply a template and signature in a campaign step.

### Phase 4 — Mailer connections + sending engine
- SMTP/IMAP connection CRUD + connectivity check (no Gmail/Outlook yet).
- Port the send pipeline, simplified: `SendEmail`, `ScheduleStepJob`,
  `ScheduleRecipientStep`, `CheckEmailStatus`, rate limiting/sending limits.
- Scheduler via `routes/console.php` / `app/Console`.
- Exit: create SMTP connection, start campaign, emails actually send on schedule
  (against Mailpit/Mailhog in dev), state transitions correct.

### Phase 5 — Tracking + replies + bounces
- Open pixel, link redirect, unsubscribe; IMAP reply/bounce polling; thread ids.
- Exit: opens/clicks/replies/unsubscribes recorded and shown in the UI.

### Phase 6 — Tasks & remaining step types
- Tasks (call/LinkedIn/manual) + task dashboard; expand step drawer to all types.

### Phase 7 — Onboarding + polish
- Campaign/recipient onboarding (replace Joyride with a maintained lib or custom),
  empty states, skeletons, loading overlays.

### Phase 8 — Public marketing page
- Port `resources/views/static/features/email-campaigns` concept into an MMOS landing page.

### Deferred backlog
Gmail API + Microsoft Graph, AI composer, spam-test, contacts/sheets CRM, SMS, multi-user
team roles for campaigns (if not already covered by team tenancy).

---

## 9. Data model plan

Recreate from `database/schema/mysql-schema.sql` (base migrations were squashed). Suggested
core tables (renames in parentheses):

- `campaigns` — id, team_id/user_id, name, status, timezone, mailer_connection_id,
  settings(json), type, started_at, interrupted_reason, timestamps.
- `campaign_steps` (source `mm_touches`) — campaign_id, sequence, type, subtype, subject,
  body, day, time, is_threaded, setting(json).
- `recipients` — campaign_id, email, phone(nullable), timezone, status, source,
  placeholders(json), sequence, next_scheduled_at, interaction timestamps.
- `campaign_emails` (source `mm_emails`) — campaign_id, step_id, recipient_id,
  mailer_connection_id, thread_id, message_id, tracker, status, data(json), timestamps.
- `mailer_connections` — user/team, name, mailer_type, encrypted smtp settings, status,
  rate-limit/limit counters.
- `email_templates`, `template_folders`, `signatures`, `placeholders`, `attachments`,
  `email_opens`, `tracked_links`, `link_clicks`, `unsubscribes`, `campaign_settings`.

Drop on port: `mm_leads`, `mm_reports`, `mm_prompts`, `spam_test_*`, `sending_domains`,
CRM tables. Keep `mm_activities` only if we keep an activity feed.

Queue columns / jobs tables come from the starter kit.

---

## 10. Sending engine plan

- **Provider abstraction**: port a slim `Contracts\Mailer` with an `SmtpMailer` (Symfony
  Mailer) and `SmtpImapMailer` (webklex/php-imap) implementation. Gmail/Outlook adapters
  are later additions behind the same contract.
- **Scheduling**: `ScheduleStepJob` scans due recipients → `ScheduleRecipientStep` picks the
  next step → `SendEmail` sends. Keep the state machines (`Draft→Active→Stopped/Completed`).
- **Rate limits**: port `SendingLimitService` but rewrite tiers (no paid plans); expose per
  connection limits as config.
- **Reliability**: `WithoutOverlapping`, `AbortIfConnectionDeactivated`, `DeferIfRateLimited`
  middlewares are valuable — port the ideas, not necessarily the exact classes.
- **Dev/testing**: use Mailpit/Mailhog + `MAIL_MAILER=log`; feature tests assert DB state.

---

## 11. OSS hygiene / de-ContactOut-ification checklist

- [ ] Remove all hard-coded addresses (`zaiman@contactout.com`, `rishi.kabra@contactout.io`,
      `azri@contactout.io`).
- [ ] Remove Slack escalation channel + `slack_mail_merge` logging.
- [ ] Replace S3-only disks with configurable local/any-disk uploads.
- [ ] Remove ContactOut OAuth clients/secrets (Google/Microsoft), Telnyx, Postmark, Resend.
- [ ] Remove feature/billing gates (`config/feature.php`, `SendingLimitTier` paid logic,
      `ProductFeature`).
- [ ] Remove internal lead/search/extension integrations.
- [ ] Rewrite `config/mail-merge.php` into a minimal `config/campaigns.php`.
- [ ] Map source palette (`primary/blueberry/caribbean/comet/success/warning/error`) and the
      custom `screens` set to MMOS `@theme` tokens; audit responsive breakpoints.
- [ ] Add `LICENSE`, `README` (setup + `composer run dev`), `CONTRIBUTING`, `.env.example`
      entries for mailer/IMAP settings.
- [ ] Confirm the license is compatible with all retained deps (notably any rich-text editor
      and IMAP/TanStack libs).

---

## 12. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Base migrations absent from source | Rebuild from `mysql-schema.sql`; verify column-by-column |
| `tailwind-styled-components` dead on TW4 | Full rewrite to shadcn/`cva`; do it component-by-component |
| `campaign-steps.tsx` 1642 lines | Decompose into small steps + hooks during port |
| Sending engine complexity | Port incrementally; SMTP only; heavy feature tests |
| React 19 StrictMode double-effects | Audit effects; use refs for idempotency |
| axios→Inertia client change | Standardise on `useHttp`/`<Form>`; centralise error handling |
| Scope creep (CRM/SMS/AI) | Keep §5 "Out/Deferred" list enforced per PR |
| IMAP reply parsing fragility | Isolate behind a service; mock in tests; document limits |

---

## 13. Testing strategy

- **Backend**: Pest feature tests per phase (controllers, actions, jobs with
  `Queue::fake()`, policies, validation). Inertia assertions with `AssertableInertia`.
  Factories for every model.
- **Frontend**: Vitest + Testing Library; component tests for list/editor/grid; snapshot
  sparingly (source over-used snapshots — prefer behavioural assertions).
- **E2E (optional, later)**: Playwright happy path (create → import → connect → send →
  track) against a seeded SQLite/MySQL + Mailpit.
- Run narrowest affected tests per change; `vendor/bin/pint --dirty --format agent`.

---

## 14. Open decisions (owner input needed)

1. **Ownership**: user-owned vs team-owned campaigns (§4.1). Recommend team-owned.
2. **Queue default**: database (zero-infra OSS) vs Redis. Recommend database default,
   Redis optional.
3. **Public API**: keep a JSON `v1/campaigns` API (for integrations) or Inertia-only.
   Recommend Inertia-only for v1, API later.
4. **Rich-text editor**: Tiptap (MIT) vs retain TinyMCE. Recommend Tiptap.
5. **Step naming**: rename "touch" → "step" in code/UI, or keep "touch"? Recommend "step".
6. **Contacts/sheets CRM**: ever in scope, or permanently out? Affects migration design.
7. **Non-email steps** (call/LinkedIn/manual): keep the data model from day one?

---

## Appendix A — Source route map (reference)

- Module dashboard pages: `/email/*` (`routes/dashboard.php`) → Inertia pages listed §3.2.
- Module JSON API: `/api/email/*` (`routes/api.php`).
- Public API: `/v1/campaigns` (`routes/api_campaigns.php`).
- Public tracking: open pixel, `links/track/...`, `/mmu` unsubscribe.
- Admin/sales/search hooks: `routes/admin.php`, `routes/sales.php`, `routes/search.php`.

## Appendix B — Source model list (reference)

Core: Campaign, Touch, Recipient, Email, MailerConnection, Template, Folder, Signature,
Placeholder, Attachment, Task, EmailOpen, Link, LinkClick, Unsubscribe, Activity, Report,
Prompt, Personalization, Setting.
CRM: Contact, ContactIdentity, ContactProperty, Sheet, ContactSheet.
Spam-test: SpamTestProvider, SpamTestInbox, SpamTestEmail, SpamTestReport.
