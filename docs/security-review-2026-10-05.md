# Security Review — ContactOut Campaigns

- **Date:** 2026-10-05
- **Commit reviewed:** `57fce12` (working tree clean)
- **Reviewer:** automated read-only review
- **Scope:** application code (`app/`, `routes/`, `config/`, `resources/js/`, `database/`), dependencies (`composer.lock`, `package-lock.json`, `docs-site/package-lock.json`), and deployment/infra config (`Dockerfile`, `docker-compose*.yml`, `docker/`, `.env*.example`, `.github/workflows/`)
- **Method:** manual code walkthrough of entry points, authorization, input handling, secrets, and deployment config; version-aware advisory scan against the Packagist and npm registry advisory APIs. **No code was changed.** Automated scanner output was triaged by hand.
- **Out of scope:** live penetration testing, runtime exploitation, production infrastructure, third-party provider internals, threat modelling of the outreach workflow.

## Summary

| ID | Severity | Area | Finding |
|----|----------|------|---------|
| H1 | **High** | Authorization | A team **Admin can invite a new member with the `owner` role**, escalating to full team control |
| M1 | Medium | Configuration | `trustProxies(at: '*')` with no trusted-host/`X-Forwarded-Host` restriction → host-header / reset-link poisoning and IP spoofing risk |
| M2 | Medium | SSRF | Any team member can point SMTP/IMAP at an arbitrary host:port; the app connects (and on verify connects immediately) → internal network probing |
| M3 | Medium | Transport | Session cookie not forced `Secure` in the shipped Docker config while HSTS is disabled |
| L1 | Low | CSRF | Blanket `unsubscribe/*` CSRF exemption (currently safe, but wider than needed) |
| L2 | Low | Open redirect | Public tracking-click endpoint redirects to a stored external URL |
| L3 | Low | Info disclosure | Connection exceptions (message text) are persisted and surfaced to all team members |
| L4 | Low | Headers | No Content-Security-Policy; HSTS commented out |
| L5 | Low | Dependencies | `docs-site` dev tooling (esbuild/vite) has advisories; not shipped |
| I1 | Info | Sessions | `SESSION_ENCRYPT` defaults to false |

No Critical issues and no unauthenticated remote-code-execution or SQL-injection path was found. Dependency audit against the advisory APIs returned **0 applicable advisories** for the installed runtime packages (details in [Dependency scan](#dependency-scan)).

---

## H1 — Admin can invite an Owner (privilege escalation)

**Severity:** High
**Category:** Broken access control / privilege escalation
**Locations:**
- `app/Http/Requests/Teams/CreateTeamInvitationRequest.php:27` — `'role' => ['required', 'string', Rule::enum(TeamRole::class)]`
- `app/Enums/TeamRole.php:26-35` — `Admin` is granted `TeamPermission::CreateInvitation`
- `app/Enums/TeamRole.php:71-78` — `assignable()` deliberately excludes `Owner`
- `app/Http/Requests/Teams/UpdateTeamMemberRequest.php:20` — correctly restricts to `Rule::in(array_column(TeamRole::assignable(), 'value'))`
- `app/Http/Controllers/Teams/TeamInvitationController.php:29` and `:68-71` — the supplied role is stored and then applied to the membership on accept

**Description.** The invitation request validates the requested role with the plain `Rule::enum(TeamRole::class)`, which accepts every enum case including `Owner`. The equivalent "change a member's role" request restricts values to `TeamRole::assignable()`, which intentionally excludes `Owner` — so the invitation path is the odd one out. Because `Admin` holds `CreateInvitation`, an Admin can POST an invitation with `role=owner`, have it accepted (or accept it themselves with a second account), and create a full Owner.

**Evidence.**
- `CreateTeamInvitationRequest` accepts any enum case (line 27).
- `TeamPolicy::inviteMember` only checks `CreateInvitation` (`app/Policies/TeamPolicy.php:80-83`), which Admin has.
- `TeamInvitationController::store` persists `TeamRole::from($request->validated('role'))` (line 29) and `accept` writes it to `team_members.role` (lines 68-71).

**Impact.** A lower-privileged Admin can silently mint an Owner, then use Owner-only capabilities (delete team, remove members, cancel invitations, change team settings). Depending on trust assumptions between Admins and Owners, this defeats the role boundary.

**Remediation.** Validate invitations with the same allow-list used for role updates:
`'role' => ['required', Rule::in(array_column(TeamRole::assignable(), 'value'))]`.
Additionally consider restricting invitation creation to `UpdateMember`/Owner, or validating that the inviter's role is at least the invited role.

---

## M1 — Wildcard trusted proxies and no trusted-host restriction

**Severity:** Medium
**Category:** Security misconfiguration (host header / IP trust)
**Locations:**
- `bootstrap/app.php:21-22` — `$middleware->trustProxies(at: '*');`
- No `$middleware->trustHosts()` / `TrustHosts` middleware is configured.
- `config/app.php:55` — `'url' => env('APP_URL', 'http://localhost')`

**Description.** Trusting every proxy means Laravel accepts `X-Forwarded-For`, `X-Forwarded-Proto`, `X-Forwarded-Host`, and `X-Forwarded-Port` from *any* peer. Because no trusted-host middleware constrains the `Host`/`X-Forwarded-Host` used for absolute URL generation, any request that can reach the app with those headers can influence the host used in generated URLs. The classic consequence is password-reset link poisoning: the victim receives a reset link pointing at an attacker-controlled host, leaking the reset token on click. It also lets a client spoof `X-Forwarded-For` to evade IP-based login throttling (`app/Providers/FortifyServiceProvider.php:107-111`).

**Nuance.** In the shipped stack the app container only `expose`s port 8080 and sits behind Caddy (`docker-compose.yml:33-34`), and Caddy normally overwrites the `X-Forwarded-*` family for its upstream, which substantially reduces exposure. The risk is real for direct-to-app access (local/alternate deployments, other reverse proxies, or two proxies in a chain) and is a latent footgun in the framework config itself.

**Impact.** Password-reset/host poisoning, IP-based rate-limit bypass, and misleading absolute URLs, conditional on the request path.

**Remediation.**
- Restrict proxies to the actual proxy network/CIDR instead of `'*'`.
- Add host validation (Laravel's `TrustHosts` middleware or explicit allowed hosts).
- Consider `URL::forceScheme('https')` / `URL::forceRootUrl(config('app.url'))` in production so generated URLs never depend on request headers.

---

## M2 — Authenticated SSRF via mailer connection host/port

**Severity:** Medium
**Category:** Server-side request forgery
**Locations:**
- `app/Http/Requests/MailerConnections/StoreMailerConnectionRequest.php:20-21,27-28` — `host`/`port` and `imap_host`/`imap_port` accept any string / 1-65535 with no allow-list
- `app/Services/Mail/SmtpConnectionVerifier.php:25-32` — builds `ssl://host:port` / `tcp://host:port` and calls `stream_socket_client()`
- `app/Services/Mail/SmtpCampaignMailer.php:55-81` — builds a Symfony Mailer DSN from the same values and connects at send time
- `app/Services/Mail/ImapMailboxReader.php:37-52` — connects to the stored `imap_host:imap_port`

**Description.** Creating or verifying a mailer connection causes the server to open a TCP connection to an attacker-chosen host and port. The SMTP verifier even reads the first 512 bytes of the response and reports success/error, giving a partial response oracle. This is a blind/limited SSRF that can be used to scan internal ranges and interact with internal services (databases, Redis, cloud metadata endpoints on HTTP ports via the SMTP greeting check, etc.). Any user who belongs to a team — including one invited by an Admin — can trigger it; the default Docker posture sets `REGISTRATION_ENABLED=false`, but operators may enable open registration.

**Impact.** Internal network reconnaissance, interaction with internal-only services, and resource probing from the application server. Severity is moderated because it is authenticated, port-scannable only over TCP with a weak oracle, and the "server" is the mail-sending host by design.

**Remediation.**
- Resolve the host and reject loopback, link-local, private, and metadata IP ranges (and IPv6 transition forms) before connecting.
- Prefer connecting to the provider by DNS-over-TLS/HTTPS or restrict outbound egress in the deployment network.
- At minimum, document that mailer host fields must be operator-controlled and gate connection creation to a higher role.

---

## M3 — Session cookie not forced Secure; HSTS disabled

**Severity:** Medium
**Category:** Transport / session protection
**Locations:**
- `config/session.php:172` — `'secure' => env('SESSION_SECURE_COOKIE')`
- `.env.docker.example:45-49` — sets `SESSION_DRIVER`, `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_PATH`, `SESSION_DOMAIN`, but **not** `SESSION_SECURE_COOKIE`
- `docker/Caddyfile:9-11` — the `Strict-Transport-Security` header is present but commented out

**Description.** `SESSION_SECURE_COOKIE` is unset in the production Docker example, so the session cookie is emitted without the `Secure` attribute. With HSTS also disabled, a user who reaches the site over `http://` (link, redirect, or an attacker-stripped request) can have the session cookie sent in cleartext. The `docker-compose.local.yml` self-signed override makes plain-HTTP first-contact realistic during setup.

**Impact.** Session hijacking via cleartext transmission / downgrade, bounded by whether HTTP is reachable on the deployment.

**Remediation.** Set `SESSION_SECURE_COOKIE=true` in `.env.docker.example` (and document it for other deployments), and enable the HSTS header once HTTPS is confirmed working.

---

## L1 — Blanket `unsubscribe/*` CSRF exemption

**Severity:** Low
**Location:** `bootstrap/app.php:24-26` — `$middleware->validateCsrfTokens(except: ['unsubscribe/*']);`

**Description.** The exemption is broader than the one route that needs it. RFC 8058 one-click requires only the signed POST (`POST unsubscribe/{recipient}`). The GET routes don't need CSRF (they're safe methods), but the wildcard would also silently exempt any future state-changing route added under `unsubscribe/`. Today the POST is protected by a signed URL, so this is not exploitable.

**Remediation.** Narrow the exemption to the exact path/method (e.g. `unsubscribe/*` only for POST, or the explicit store route name) so future additions are fail-closed.

---

## L2 — Tracking click endpoint redirects to a stored external URL (open redirect)

**Severity:** Low
**Locations:** `app/Http/Controllers/Tracking/TrackingController.php:41-56`; `app/Services/Mail/CampaignBodyBuilder.php:47-79`

**Description.** `t/c/{hash}` issues `redirect()->away($hash->url)`. URLs are extracted from campaign HTML and constrained by the builder's regex to `https?://`, and each link is keyed by a random 32-character hash, so an outsider cannot mint arbitrary redirects. A team member can nonetheless create a redirect that appears to live on the trusted campaign domain and points anywhere, which is useful for phishing. This is inherent to click tracking.

**Remediation.** Accept as a design tradeoff, or show an interstitial page for off-domain destinations, and/or validate destinations against an allow-list.

---

## L3 — Connection exception text persisted and shown to team members

**Severity:** Low
**Locations:** `app/Actions/MailerConnections/VerifyMailerConnection.php:39-46`; `app/Jobs/Campaigns/SendEmail.php:306-313`; `app/Http/Controllers/MailerConnections/MailerConnectionController.php:128-131`

**Description.** Failure messages are stored in `mailer_connections.exception_data` and flashed back to the UI. Symfonymailer DSN/transport errors generally do not include the password, but the text is provider/infrastructure-derived and is not sanitised before display or logging. It also discloses internal host/network detail to every team member.

**Remediation.** Store a stable, categorized error code and a generic user-facing message; keep full exception text in server-side logs only.

---

## L4 — Missing security headers (CSP, HSTS)

**Severity:** Low
**Locations:** `docker/nginx.conf:11-12` (sets `X-Frame-Options`, `X-Content-Type-Options`); `docker/Caddyfile:8-13` (sets `X-Content-Type-Options`, `Referrer-Policy`)

**Description.** No `Content-Security-Policy` is set, and HSTS is commented out. Combined with the app's CDN-free, self-hosted nature, a defense-in-depth CSP would help contain any future XSS. (Note: the only `dangerouslySetInnerHTML` sinks found are Laravel's own paginator labels and the 2FA QR SVG — see [Clean areas](#reviewed-and-clean-areas).)

**Remediation.** Add a baseline CSP (start report-only) and enable HSTS after verifying HTTPS across the deployment.

---

## L5 — `docs-site` dev-tooling advisories

**Severity:** Low
**Location:** `docs-site/package-lock.json`
**Evidence (npm advisory API, dev dependencies only):**
- `esbuild` — moderate, dev-server request disclosure
- `vite` — moderate path traversal in optimized-deps `.map`, high `server.fs.deny` bypass on Windows alternate paths
- `launch-editor` (via vite) — moderate NTLMv2 hash disclosure on Windows

**Description.** These affect the VitePress documentation build/dev server, which is not part of the deployed application. They are recorded for completeness.

**Remediation.** Run `npm audit fix` inside `docs-site` at the next docs change; not urgent.

---

## I1 — Session payload not encrypted at rest

**Severity:** Informational
**Location:** `.env.docker.example:47` — `SESSION_ENCRYPT=false`; sessions are stored in the database (`SESSION_DRIVER=database`)

**Description.** Session payloads (including the `mailer_oauth` handshake state) are stored unencrypted in the session table. Database access is already a high-trust boundary, so this is informational; enabling encryption adds defense in depth if the DB is shared or backed up separately.

---

## Dependency scan

Method: for each installed version in `composer.lock` and `package-lock.json`, queried the Packagist security-advisories API and the npm advisories bulk API, then filtered advisories to those whose affected-version range is satisfied by the installed version.

| Ecosystem | Packages scanned | Applicable advisories |
|-----------|------------------|-----------------------|
| Composer (runtime + dev) | 159 | **0** |
| npm (`package-lock.json`, prod + dev) | 405 | **0** |
| npm (`docs-site/package-lock.json`, dev only) | 173 | 4 (esbuild/vite/launch-editor — see L5) |

Notable runtime versions: `laravel/framework v13.33.0`, `symfony/* v8.1.x`, `guzzlehttp/guzzle 8.2.0`, `google/apiclient v2.20.0`, `webklex/php-imap 6.2.0`, `laravel/fortify v1.40.0`, `web-auth/webauthn-lib 5.3.9`. Advisory checks flagged this tree as clean; the recently patched laravel `email`-rule CRLF, Symfony Mime header injection, and Symfony `NoPrivateNetworkHttpClient` SSRF advisories do **not** apply to the installed versions. The PHP dependency tree is very current; keep it that way via Renovate/Dependabot and re-run `composer audit` / `npm audit` in CI.

> Limitation: this was an API-based, version-aware scan, not `composer audit`/`npm audit` executed in CI. It reflects the advisory databases at the scan time and should be repeated as part of release.

---

## Reviewed and clean areas

These were examined and no exploitable issue was found; they are recorded so coverage is auditable.

- **Team-scoped authorization is consistently enforced.** Every nested resource is re-scoped to the route team before use (e.g. `CampaignStepController.php:46-47`, `CampaignRecipientController.php:50-51`, `ContactListController.php:113-114`, `SignatureController.php:72`, `TemplateFolderController.php:38`, `MailerConnectionController.php:81`), and policies all re-check `belongsToTeam` / team role. No cross-team IDOR was found.
- **Nested parent/child binding is validated** (`$campaign->steps()->findOrFail(...)`, `$campaign->recipients()->findOrFail(...)`).
- **Mass assignment is guarded** — every model uses the `#[Fillable]` attribute or explicit assignments; no `unguard()` / `$guarded = []`.
- **No SQL injection** — the only raw SQL uses bound parameters (`UniqueTeamInvitation.php:28,38`, `HasTeams.php:185`); `LIKE` searches bind the user input.
- **Unsubscribe is protected by signed URLs** with a confirmation page, and one-click POST is handled without a CSRF hole in practice (`routes/tracking.php`, `UnsubscribeController`, `CampaignBodyBuilder::appendUnsubscribe`).
- **XSS surface is minimal** — the only `dangerouslySetInnerHTML` sinks are Laravel's paginator labels (`resources/js/components/pagination.tsx`) and the Fortify QR SVG (`resources/js/components/two-factor-setup-modal.tsx`). Campaign/template bodies are not injected as raw HTML in the browser.
- **Secrets & credentials** — SMTP/OAuth credentials use the `encrypted:array` cast (`app/Models/MailerConnection.php:97`); `User` hides `password`, `two_factor_secret`, `two_factor_recovery_codes`, `remember_token` (`app/Models/User.php:38`). `.env` is excluded from the Docker build context (`.dockerignore:29`), the entrypoint refuses to start on an empty `APP_KEY` (`docker/entrypoint.sh:52-56`), and `.env.docker.example` ships `APP_DEBUG=false` and `REGISTRATION_ENABLED=false`.
- **Authentication controls** — login/throttling and 2FA/passkey flows are wired through Fortify with login/two-factor/passkey rate limiters (`FortifyServiceProvider.php:101-120`); registration is gated by invitation or first-user (`RegistrationAccess`), and invitation acceptance verifies the signed-in email matches (`ValidTeamInvitation`); password changes require the current password and are throttled.
- **Deployment** — MySQL is not published to the host, the app is only reachable through Caddy, and CI workflows pin actions by SHA, use least-privilege `permissions`, and set `persist-credentials: false`.
- **No secrets committed** to the working tree; `git status` is clean.

## Open questions / limitations

1. Is `REGISTRATION_ENABLED=true` ever used on an internet-facing deployment? If so, M2 (SSRF) and the invitation escalation (H1) are reachable by any self-registered user, which raises their effective severity.
2. Is there always exactly one reverse proxy in front of the app (Caddy), and does it overwrite client-supplied `X-Forwarded-*`? This determines whether M1 is currently exploitable or latent.
3. Should mailer host/port be treated as operator-only configuration? If yes, the cleanest fix for M2 is to remove those fields from the request entirely and have operators configure connections via env/artisan.

## Suggested priority

1. **H1** — restrict invitation roles to `assignable()` (one-line fix, clear privilege boundary).
2. **M1** — narrow trusted proxies and add trusted-host handling.
3. **M3** — set `SESSION_SECURE_COOKIE=true` and enable HSTS in the Docker example.
4. **M2** — add private/loopback/metadata IP blocking for mailer connections.
5. **L1–L5 / I1** — hardening, address opportunistically.
