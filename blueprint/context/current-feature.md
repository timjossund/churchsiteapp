# Feature: Customer domains

**From build-plan:** feature 7b
**Build attempt:** 1
**Status:** verification incomplete
**Branch:** feature/customer-domains
**Archive:** blueprint/history/features/07b-customer-domains.md

## Goal

Let a site owner connect a customer-owned `www` hostname, pay for that site's subscription, follow DNS instructions, and serve its published multi-page site over HTTPS through the proven Worker transport. Domain readiness, ownership, publication, and paid access must all hold before content is served.

## In scope

- One connected hostname per site, globally unique across sites, with owner-only setup, automatic status checks, disconnect, and replacement.
- Stripe-hosted Checkout using the existing per-site Cashier flow: USD $15/month or $150/year, no trial, payment when connection begins rather than after DNS completes.
- Cloudflare for SaaS provisioning, DNS/ownership and certificate status, retry-safe reconciliation, and removal after explicit disconnect or actual account deletion.
- Published Home, additional pages, navigation, and referenced media through authenticated Worker ingress; platform/subdirectory routing remains available independently.
- Preserve configuration when paid access ends, while denying public custom-domain content. Resubscription reuses configuration after readiness is rechecked.
- Operations documentation and a controlled end-to-end validation plan before production checkout/route activation.

## Out of scope

Bare domains, domain purchasing, multiple simultaneous hostnames per site, zero-downtime domain migration, refunds/proration or new billing rules, changes to prices or account-deletion timing, alternate hosting, per-customer Plesk aliases/certificates, new frontend frameworks, broad generated-file cleanup, and automatic production deployment.

## Build loop

Use configured `stepReview: every` and `checkpointCommits: enabled`: present each passing step for review before proceeding. These settings do not authorize commits. Independent review is required before completion because this feature crosses payment, tenant, network, secret, and persisted-data boundaries. Use the installed fresh-reviewer workflow against an approved immutable checkpoint. Regular Audit, Check, and try guide are otherwise manual. Completion owns the final work commit and separate merge/push approvals.

## Build steps

- [x] **1. Persist domain intent and ownership safely.** Add the CustomHostname model/migration, owner-scoped requests/actions, normalization, database uniqueness, and explicit connection/removal state. Reserve one hostname atomically before external work. Provide an application-specific DNS ownership challenge so pointing at the shared SaaS target cannot let another account claim a domain.
      **Done when:** Pest covers owner/guest/other-owner access, duplicate submissions, globally conflicting hostnames, one domain per site, invalid hostnames, platform-host exclusions, challenge matching/mismatch, and concurrent reservation constraints. No Cloudflare or Stripe call occurs for invalid or unauthorized input. New routes remain disabled for rollout until the complete flow is available.

- [x] **2. Integrate Cloudflare provisioning and reconciliation.** Use Laravel's installed HTTP client for a focused zone-scoped adapter. Implement create/read/remove with persisted operation identity, timeouts, error classification, uncertain-result recovery, DNS checks, and scheduled reconciliation. Store only owner-facing DNS/status fields from validated provider responses. Keep the fixed origin and Full (strict).
      **Done when:** Offline HTTP/DNS fakes cover pending/active/error status, hostname and certificate readiness independently, incorrect CNAME/challenge, provider timeout after create, malformed responses, missing credentials, rate limits, repeated removal, and stale responses after disconnect. A scheduler run is bounded/chunked and prevents overlapping work. Failed checks never manufacture readiness or overwrite a newer connection. No API token or raw provider response leaks to the browser/logs.

- [x] **3. Connect domain intent to existing billing and deletion.** Require saved valid domain intent before the existing checkout endpoint may charge. Reuse StartSiteCheckout and verified webhook-paid access rather than trusting a return URL. Preserve the existing subscription on replacement/disconnect. Retain domain configuration on subscription expiry. Integrate retryable remote-hostname cleanup into actual account deletion without deleting records needed to recover an uncertain provider response.
      **Done when:** Pest proves checkout cannot bypass domain entry, double submission resumes the same checkout, payment failure/cancellation leaves a resumable state, browser success alone grants no access, existing paid sites do not buy a second subscription, unpaid access stops immediately under `hasPaidDomainAccess()`, and deletion-pending accounts cannot begin new connections. Existing billing/cancellation/deferred-deletion tests remain passing. Provider cleanup failures remain recoverable and never leave a live tenant mapping after deletion intent is finalized.

- [x] **4. Serve published tenant content through the Worker.** Extend the proof-only receiver/Worker contract to permit narrowly defined HTML and media responses for registered customer hosts. Reuse the published snapshot renderer and media authorization with explicit hostname-specific URL generation rather than mutating global request host or dispatching arbitrary platform routes.
      **Done when:** PHP and Node tests prove paid/ready/published owners' Home and additional pages, same-host navigation and media, HEAD, unknown/removed/unpaid/unpublished hosts, draft isolation, cross-site media rejection, malformed metadata, unsupported methods, and forged headers. Customer requests cannot reach login/dashboard/editor/billing/webhook/subdirectory routes. Browser assets load without redirecting to platform pages. Existing proof `/up`, platform routes, and free subdirectory publishing remain tested. Public responses do not create sessions or forward cookies.

- [ ] **5. Add the domain setup interface on a dedicated Go Live page, reached beside the site name in Settings.** Show a labeled full `www` hostname input with an example, plan choice, immediate-payment disclosure, DNS instructions, ownership/connection/SSL/billing/publication progress, refresh/retry, disconnect confirmation, and replacement via disconnect then connect. Reuse installed components and server errors. Poll only while needed; scheduled checks continue without an open browser.
      **Done when:** Tests cover serialized owner-only state and actions. Manual review verifies empty, submitting, invalid, unavailable, checkout-canceled, payment-processing, DNS-pending, SSL-pending, unpublished, live, unpaid-retained, and removal-pending states. Fields have associated errors, status announcements, focus recovery, and safe text rendering. Double submits are disabled. Copy controls provide feedback. Disconnect explicitly warns that billing continues and links to Cancel renewal; replacement explains the old hostname stops working first. No API IDs/secrets are shown unnecessarily.

- [ ] **6. Verify the integrated flow and prepare rollout.** Run focused domain/billing/Worker tests and `composer ci:check`; document environment names, scheduler, provider permissions, deployment order, rollback, and concrete browser/network checks. Update obsolete operational prose about the removed `public/hot` file where editing the deployment runbook. Keep checkout closed until the domain flow is tested and the operator explicitly authorizes activation.
      **Done when:** All automated gates and independent review pass. A controlled live validation, after explicit deployment authorization, records application/Worker identity, a customer-format test hostname, actual DNS/TLS statuses, published navigation/assets/media, denied platform routes, and unaffected platform/other hosted sites. Exercise billing with Stripe test mode in a separate test environment, or stop at a ready-to-deploy handoff if unavailable; do not silently charge a live card or switch production billing to test mode. Record operator reports separately from direct observations. Production route broadening, remote configuration, charges, and deployment require their own explicit approvals.

## Files / areas

Existing integration points: `app/Models/Site.php`, `app/Actions/StartSiteCheckout.php`, `app/Actions/DeleteAccountWhenBillingEnds.php`, `app/Actions/SiteBillingSummary.php`, `app/Http/Controllers/SiteBillingController.php`, `app/Http/Controllers/SiteController.php`, `app/Http/Controllers/PublishedSiteController.php`, `app/Http/Controllers/DomainProxyController.php`, `app/Support/DomainProxyResponse.php`, `routes/web.php`, `routes/domain-proxy.php`, `routes/console.php`, `config/site-billing.php`, `config/domain-proxy.php`, `resources/js/pages/Sites/Settings.vue`, `resources/views/sites/published.blade.php`, and `workers/domain-proxy/`.

Add focused domain model/migration, request/controller/actions, Cloudflare configuration, and HTTP/DNS test seams only where needed. Extend existing Pest and Node tests and shared protocol fixtures. Operations belong in `docs/worker-domain-proxy.md`, `docs/plesk-deployment.md`, and relevant billing documentation. Preserve pre-existing generated changes and stashes.

## Data / contracts

### Hostname identity and ownership

Accept a full ASCII DNS hostname beginning with `www.`; trim outer whitespace and lowercase before validation and storage. Do not silently prepend another `www`. Reject schemes, ports, paths, queries, fragments, credentials, wildcard/IP forms, empty labels, underscores, invalid DNS label lengths, trailing dots, and platform/origin/SaaS-target hostnames and their subdomains. Follow the existing 253-character/63-character DNS limits. Do not add Unicode conversion support in this feature.

CustomHostname has an integer key; a unique site foreign key; a globally unique canonical hostname; nullable unique Cloudflare ID; hostname and SSL status strings; nullable `verified_at`; and timestamps. Required lifecycle support includes a server-generated UUID operation identity, explicit provisioning/removal state, a cryptographically random 32-byte lowercase-hex ownership challenge, last-check timestamp, validated DNS-instruction JSON, and a safe error category. Use existing database conventions. Never serialize model attributes wholesale.

Display a TXT ownership record at `_churchsite.<full-hostname>` whose expected value is the per-connection random challenge. Require an exact DNS answer before treating this account's claim as verified. The public DNS challenge is proof of DNS control, not a login credential. A new connection after removal receives a new challenge. Provider activation alone and a shared CNAME are insufficient account ownership evidence. Resolve DNS through an injectable resolver; do not fetch arbitrary customer URLs. Do not adopt an existing manually configured provider hostname merely because its name matches.

Keep the database reservation while provisioning or removal is uncertain; never expose the reserving account in conflict errors. Owner requests load the site through the authenticated user's relation. Lock/recheck the site and connection generation for transitions; use unique constraints to enforce races. Network work must not hold long database transactions. Apply provider results only to the same operation/generation and record ID.

### Payment and lifecycle

The first connection saves valid intent and starts existing Stripe Checkout immediately; DNS completion is not required to pay. Before payment, present the recurring USD $15/month or $150/year price, no trial, and that charges start now. Authoritative access remains the existing `Site::hasPaidDomainAccess()` implementation; no second interpretation of paid time or webhook state.

Cloudflare provisioning follows confirmed paid access for a new connection. Pending/canceled checkout retains resumable local intent, not public eligibility. Existing paid subscriptions reuse the same site billing record. Owners may disconnect regardless of payment status. Disconnection immediately revokes local serving, then removes the exact managed provider hostname with retries; release the reservation only after deletion/confirmed absence. Replacement follows completed disconnection and a new connection, with downtime disclosed; do not offer two live hostnames or a second subscription.

Subscription expiry retains the hostname, provider configuration, DNS instructions, and site assignment. Every public request still checks paid access. Resubscription requires rechecking ownership/readiness and does not revive explicitly disconnected names. Disconnect/replacement never cancels, refunds, or restarts billing; Cancel renewal remains the existing separate action. Account deletion keeps its paid-period policy, then disables routing and ensures recoverable provider cleanup before losing the stored IDs. No application-created provider hostname may become an untracked orphan through cascading deletion.

### Provider and status

Use one configured Cloudflare zone and private API token with only needed hostname permissions, plus the configured customer CNAME target `customers.churchsite.app`. No SDK is required. Verify current request/response shapes and token permission names against official documentation before implementation. Request TXT certificate validation and display actual returned validation records; distinguish application ownership TXT, provider hostname validation, and certificate validation instructions.

Readiness requires verified application ownership, the expected DNS target, and both provider hostname and SSL statuses `active`. Unknown values are pending/unavailable, never ready. Preserve separate provider statuses and clear UI reasons. Store only validated record type/name/value instructions. Default to scheduled checks every five minutes using the existing scheduler, without overlap and with bounded batches; this is an internal polling interval, not an issuance-time promise. Throttle owner-triggered checks. Provider failures preserve retryable state; fresh negative DNS/status evidence removes readiness. Do not make a provider call on each public page request.

Create retries must reconcile an uncertain remote result before issuing another create; do not assume undocumented API idempotency. Use the persisted operation identity with supported provider correlation only after verifying its availability, or reconcile the reserved exact hostname and confirm ownership of the operation. Ambiguous pre-existing objects require operator resolution, never adoption or deletion. Delete only IDs managed by the site's current operation. Credentials and raw provider errors stay private; never log Authorization headers.

### Public transport and rendering

Preserve the fixed HTTPS origin endpoint, bearer authentication, metadata validation, Full (strict), manual redirect handling, visitor-header stripping, platform-host guard, and stateless response boundary from 7a. Extend protocol validation explicitly for content; the current Worker only accepts proof JSON and must not simply pass arbitrary upstream responses through. Allow only intended HTML, published-media types, and safe status/header combinations. Keep cache disabled for this release so paid-access revocation is not hidden by cached pages.

Resolve a unique hostname to its site before serving only a valid published snapshot. Home is `/`; additional published pages are `/<published-path>`. Reserve platform/control paths and a dedicated public media namespace such as `/_media/<id>`; apply equivalent page-path validation so publication cannot create a path that domain routing denies. Serve only media referenced by that site's frozen publication, with storage keys constrained to that site. Do not read draft blocks for public content.

Generate customer page/navigation/social URLs using the resolved hostname, without changing the application-wide host. Use the fixed platform asset origin for versioned frontend bundles/fonts, verifying cross-origin font/module behavior, or add narrowly scoped static-asset handling if the installed build requires it. Never proxy arbitrary platform paths as an asset workaround. Preserve safe external links and escaped Blade text. Subdirectory previews retain noindex; live custom-domain pages omit the preview-only noindex instruction. Error/proof responses remain non-indexable. HEAD has the same status/headers with an empty body. Customer platform/control paths return generic denial, not account HTML.

Keep production checkout/configuration disabled until rollout. Broader SaaS Worker routing must explicitly exclude platform/origin hosts to prevent recursion. Keep the narrow proof available during staged rollout; do not require per-customer Plesk configuration.

## Testing

Baseline `composer ci:check` passed during this planning run on 2026-09-27: frontend formatting/lint/types, Pint, PHPStan, production build, and 362 Pest tests with 3,121 assertions. It rebuilt assets successfully after the verification fix. This is local evidence only.

Use Pest with Cloudflare/Stripe HTTP fakes and deterministic DNS/clock seams, plus the existing Node Worker suite and shared fixtures. Cover authorization, duplicate/concurrent intent, operation-generation races, payment processing, cancellation/deletion, provisioning uncertainty, readiness loss, retained configuration, cross-tenant rendering/media, and unchanged subdirectory behavior. Validate database uniqueness on the deployment database type when available; SQLite-only tests must not be claimed as MySQL concurrency proof. No live provider calls or charges in automated tests. No browser runner is configured; record manual UI/browser evidence separately.

## Notes for the AI

The user approved disconnect/replacement with billing kept separate, and retaining configuration after subscription expiry. Existing 7a live proof and the repaired asset-build baseline satisfy the transport and local-check prerequisites. Older plan/overview text still describing Worker proof as pending is historical; do not rewrite user-owned plans as an incidental implementation change.

Critique tightened account-specific DNS ownership, uncertain provider operations, reservation release, disconnect versus cancellation, retained unpaid configuration, published-only media, reserved paths, and the Worker HTML/asset contract. These are reachable trust/lifecycle boundaries, not speculative infrastructure. Use small repository-native actions and installed tools; do not build a generic provider platform.

Provider references: [Cloudflare common API calls and readiness](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/start/common-api-calls/) and [hostname validation](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/domain-support/hostname-validation/). API readiness uses separate hostname/certificate statuses; validate DNS as well. Live account permissions, provider provisioning, and production checkout activation remain rollout evidence, not claims made by this spec.

## Implementation evidence

### Step 1: local domain intent (2026-09-27)

- Added owner-scoped reservation and disconnect actions, canonical hostname validation, unique site/hostname/provider/operation constraints, restricted deletion, and per-connection random TXT challenges.
- DNS verification uses an injectable resolver outside database locks, then rechecks the record and operation before applying evidence. Disconnect clears ownership and retains cleanup identity/reservation.
- New routes are disabled by default. No provider or billing integration is activated in this step.
- `php artisan test --compact tests/Feature/CustomHostnameTest.php tests/Feature/SiteBillingFoundationTest.php tests/Feature/SiteBillingLifecycleTest.php tests/Feature/SiteBillingManagementTest.php`: passed, 110 tests, 657 assertions (43 domain tests plus 67 existing billing tests).
- `composer types:check`: passed after removing a redundant list conversion. PHPStan required sandbox escalation for its local worker socket.
- Targeted `vendor/bin/pint --test` on all 12 changed/new PHP files: passed. `git diff --check`: passed.
- Constraint evidence is SQLite-only, not MySQL concurrency proof. No live DNS/provider calls, browser validation, production migration, or full-feature verification performed.
- Awaiting step 1 approval before step 2. No commit created. Existing generated-file changes preserved. Independent review remains required after the complete feature checkpoint.

### Step 2: provider reconciliation (2026-09-27)

- Step 1 approved by the user before starting this step; no checkpoint/work commit was authorized or created.
- Added the zone-scoped Laravel HTTP adapter, TXT certificate requests, strict provider identity/instruction validation, safe failure categories, and DNS CNAME checks. No SDK or dependency added. Existing fallback origin, fixed Worker origin, and Full (strict) remain unchanged.
- Added persisted create-attempt/zone identity, expiring per-record check leases, generation-checked result application, immediate readiness loss on negative checks, and retryable managed-ID removal. Network work runs outside application database transactions.
- Scheduled `domains:reconcile` every five minutes with overlap protection, at most 50 records per run in groups of ten, and a four-minute deadline before starting further work. Owner checks are authenticated, scoped, and throttled. Both entry points honor the disabled rollout flag.
- Official Cloudflare documentation confirms custom metadata is an Enterprise paid add-on. The spec's conservative operator-resolution fallback is used: after an uncertain create, exact-host lookup never establishes operation ownership by itself, so no second create, adoption, or deletion occurs. The retained record becomes `operator_required`; the runbook documents attribution and reviewed repair requirements. No extra paid capability is assumed.
- `php artisan test --compact tests/Feature/CustomHostnameTest.php tests/Feature/CustomHostnameReconciliationTest.php tests/Feature/SiteBillingFoundationTest.php tests/Feature/SiteBillingLifecycleTest.php tests/Feature/SiteBillingManagementTest.php`: passed, 159 tests, 815 assertions.
- `php artisan test --compact`: passed, 454 tests, 3,458 assertions, using existing assets and offline HTTP/DNS fakes.
- `composer types:check`, targeted `vendor/bin/pint --test`, and `git diff --check`: passed. Full `composer ci:check` and independent review remain due at the complete feature checkpoint.
- Updated the obsolete `public/hot` runbook statement while documenting provider operations; F-03 is fixed, awaiting Audit closure. F-02 remains an unrelated open P2. No P0/P1 finding is recorded.
- No live credentials, DNS/TLS/provisioning evidence, deployment, production migration, or checkout activation. Awaiting step 2 approval before billing/deletion integration in step 3.

### Step 3: billing and account deletion (2026-09-27)

- Step 2 approved by the user before starting this step. No commit, rollout activation, live provider call, or charge performed.
- New unpaid connections save validated intent and use existing StartSiteCheckout immediately. Its guard requires valid retained domain intent at every checkout transaction. Existing paid connections and replacements reuse billing without a second subscription; canceled/failed checkout retains intent and retry identity. Both rollout gates remain disabled.
- Verified billing webhooks invalidate readiness when paid access ends or resumes after a lapse. Serving eligibility delegates paid access to Site::hasPaidDomainAccess() on every call, so expiry denies eligibility without waiting for scheduled reconciliation. Publication and transport enforcement remain step 4.
- A late successful Cloudflare create response retains its managed ID for the same record/operation even when billing invalidates its readiness lease; it cannot restore stale readiness or overwrite a newer connection.
- Account deletion still waits for the existing paid-period policy. It commits domain removal state before network cleanup, keeps account/site/provider identity on uncertainty, and deletes only after cleanup plus a final locked check for domains/newly confirmed paid time. Removal reconciliation remains available to account deletion when rollout is disabled, without enabling provisioning.
- Existing billing lifecycle tests now seed the required domain intent for checkout scenarios; step 1 reservation route tests use paid sites. Their billing and authorization assertions remain intact.
- Focused domain/billing/deletion tests passed (137 tests, 590 assertions before the final additional cleanup/payment race test).
- Final `php artisan test --compact`: passed, 469 tests, 3,542 assertions. `composer types:check`, targeted `vendor/bin/pint --test` on all 11 changed PHP files, and `git diff --check` passed.
- Evidence is local/offline and SQLite-based. No browser acceptance or live Stripe/Cloudflare validation is claimed. Full `composer ci:check` and independent review remain due at the complete feature checkpoint. Findings remain F-02 open P2 and F-03 fixed P3 awaiting Audit closure; no P0/P1 ledger blocker.
- Awaiting step 3 approval before published content transport in step 4. Existing generated changes remain separate from source work.

### Step 4: published customer transport (2026-09-27)

- Step 3 approved before implementation. Added opt-in protocol version 2 for customer content while preserving version 1 proof transport. Worker CUSTOMER_DOMAINS_ENABLED remains false in the example; the application domain gate and checkout gate remain closed.
- Registered hosts must have current paid access, ownership/DNS/provider readiness, and a valid frozen publication before any page, referenced media, or asset response. Renderer URLs receive the hostname explicitly without rewriting global request state or dispatching platform routes.
- Reused the published snapshot renderer for Home/additional pages, same-host navigation/social/media URLs, escaped content, and safe external links. Live HTML omits preview noindex; free subdirectory previews remain independent and non-indexable. Media requires a frozen reference plus site-scoped storage keys without traversal.
- The installed build uses same-origin module imports and font URLs. Added a narrow manifest allowlist for published entry dependencies, shared CSS, and generated fonts. Custom content bypasses development hot-file output; arbitrary build files, the application bundle, and platform/control paths remain denied. Reserved paths are rejected during page settings/publication and avoided by path generation.
- Worker accepts only matching response version/hostname/content-kind, intended MIME/status combinations, and no cookies or redirects. It constructs safe response headers, strips visitor credentials, disables caching, and handles HEAD without bodies. Customer sharing queries are ignored for rendering/canonical URLs; proof queries remain denied.
- `php artisan test --compact`: passed, 487 tests and 3,797 assertions. The 18 customer-transport tests cover frozen content, eligibility failures, media isolation, all current published asset responses, escaping, queries, and reserved paths. Existing platform, subdirectory, billing, and proof tests pass.
- `node --test --test-reporter=dot workers/domain-proxy/*.test.mjs`: passed, 119 tests, including shared version 2 denial fixtures and unchanged version 1 fixtures.
- `npm run build`, `composer types:check`, targeted Pint, Worker `vp lint`, targeted `vp fmt`, and `git diff --check` passed. Build emitted its existing optional fontaine fallback notice; no dependency installed. Generated output remains separate from source work.
- Evidence is local request/response and offline Worker testing with rebuilt assets. No browser visual acceptance, deployed Worker, DNS/TLS, deployment, remote routing change, or charge is claimed. The controlled rollout browser/network checks and final composer ci:check/independent review remain pending. F-02 remains open P2; F-03 remains fixed P3 awaiting Audit closure; no P0/P1 ledger blocker.
- Awaiting step 4 review before the owner setup interface in step 5. No commit created.

### Step 5: owner interface implementation (2026-09-27)

- Step 4 approved before implementation. Added a focused domain panel above Billing with full www input, monthly/annual choice, immediate-payment/no-trial disclosure, retained checkout plan, DNS records/copy feedback, independent progress, refresh/retry, and explicit disconnect/replacement downtime and ongoing billing warnings.
- Added an explicit owner-scoped domain summary without provider/operation/session IDs. Disabled rollout cannot report live; missing provider identity cannot report ready. Unconfirmed payment continues polling without permitting a second subscription; expired paid access retains configuration and stops settled-state polling.
- Status-only polling runs every 20 seconds for at most 30 attempts while needed and visible, pauses for edits/requests, and never provisions. Request guards cover other settings writes, field errors are associated, expected request failures have safe text/focus recovery, and the Cancel renewal anchor targets the focusable Billing heading.
- Checkout cancellation now returns with a display-only canceled marker. Neither canceled nor processing browser parameters grant access. Billing mutations refresh the domain summary too.
- Focused domain/state/billing tests passed (90 tests, 507 assertions before the final additional payment-polling regression). The final state-summary suite passed all 14 tests, 162 assertions. Full PHP suite passed 500 tests, 3,947 assertions before that final additional regression; that regression passed in the focused suite.
- Frontend build, Vue typecheck, targeted lint/format, and PHP typecheck passed; one redundant nullsafe access reported by PHPStan was corrected. Build retains the existing optional fontaine notice. Existing generated changes remain separate from source work. No commit or remote action occurred.
- Manual visual/keyboard/state verification is still pending: no configured browser runner or observed browser evidence. `docs/domain-setup-review.md` records the exact state checklist and try path. Step 5 remains unchecked until this explicit done-when is verified; code/tests alone are not manual UI evidence. Step 6 final composer ci:check, rollout documentation, and independent review remain pending.

### Step 6: automated verification and rollout handoff (2026-09-27)

- User approved the equal-width desktop Custom domain/Billing layout with stacked smaller-screen behavior, then explicitly confirmed that only the layout was reviewed. Checkout, DNS/SSL, copy controls, disconnect, keyboard focus, and other specified states remain untested in the browser. Step 5 remains unchecked; approval is not recorded as evidence for unobserved states.
- Applied the two pending additive migrations successfully to the application's confirmed local environment after the user requested them. No production migration occurred.
- Updated Worker, Plesk, and billing runbooks with current domain entry, exact configuration names, literal closed Laravel gates versus the Worker variable, migrations, scheduler, provider permissions, staged deployment order, separate Stripe sandbox validation, browser/network acceptance, evidence provenance, and rollback that preserves domain/operation records. Corrected the older billing walkthrough that bypassed saved hostname intent.
- First combined check found Markdown formatting issues; those were fixed. The next run found unawaited new Worker test promises under the full lint configuration. Updated those tests to await registration/execution like the existing suite; the narrow Worker and frontend checks then passed.
- Final `composer ci:check` passed: frontend formatting/lint, Vue typecheck, Pint, PHPStan, asset build, and 501 Pest tests with 3,959 assertions. `node --test --test-reporter=dot workers/domain-proxy/*.test.mjs` passed all 119 tests. `git diff --check` passed. The build retains the existing optional fontaine notice; no dependency added.
- Scope/proportionality review: domain actions, adapter, DTO, DNS seam, and asset allowlist implement the approved payment, ownership, provider uncertainty, tenant routing, and owner-interface contracts. No SDK, browser runner, deployment system, speculative provider abstraction, or remote configuration added.
- This is a local ready-to-deploy preparation handoff, not a verified/completed feature. Browser-state acceptance, actual target-MySQL behavior, separate Stripe sandbox exercise, live DNS/TLS/customer content, and independent review remain unavailable/pending. No immutable checkpoint has been approved or created; no fresh-review receipt exists. Step 6 remains unchecked until its gates are met. No commit, merge, push, deployment, charge, or rollout activation occurred.
- Findings: F-02 remains unrelated open P2; F-03 is fixed P3 awaiting Audit closure. No open/fixed P0/P1 is recorded. Resume `/implement 7b` with the remaining browser/test-environment evidence; final independent review requires the skill's separately approved exact checkpoint after verification.

### Authorized local sandbox setup (2026-09-27)

- User configured the Stripe sandbox key, monthly/annual prices, and webhook secret in the ignored local environment. Read-only Stripe API checks confirmed both price contracts and the saved portal configuration with invoice/payment-method access only. Actual checkout/webhook delivery is still pending; secrets were not printed.
- User configured Cloudflare zone/token. Read-only requests through the application adapter confirmed access and active hostname/SSL status for the existing proof host. The user selected local site `test` (ID 16), initially unpublished with no domain, subscription, or checkout attempt.
- Corrected the suggested test hostname: descendants of the reserved proof host are rejected by the existing validator. The user explicitly selected `www.churchsite-test.timjossund.com` instead. It passes the application validator and was absent from the configured SaaS zone on read-only lookup.
- Added `CUSTOMER_DOMAINS_LOCAL_TESTING` as an explicit local-only override in both gate configurations. Gates open only with `APP_ENV=local`, flag true, and an `sk_test_` key; production, automated-test environments, missing keys, and live keys remain closed. Enabled it in the ignored local `.env` and cleared the local config cache. Updated runbooks to describe this override; production activation still needs a separate reviewed change.
- Six configuration regression cases passed with existing domain/billing foundation tests (68 tests, 253 assertions); PHPStan passed. A running Vite server exposed an SSR request in the domain-summary HTTP-fake suite; explicitly disabling SSR in that focused suite preserves offline response tests. The corrected summary/configuration suites passed 20 tests, 174 assertions. Final `composer ci:check` passed after this repair, including 507 tests and 3,971 assertions, formatting/lint, both typechecks, and build.
- No site domain was reserved from the console, no checkout/session/payment was created by the agent, and no Cloudflare hostname/DNS/Worker route was changed. The next browser step uses the actual site settings form and Stripe test card with the user's running webhook listener. Full browser acceptance, independent review, and remote customer-content rollout remain pending.

### Approved Go Live navigation change (2026-09-27)

- User requested moving Custom domain and Billing to a dedicated page with a Go Live button opposite the Settings site name. The new owner-only, verified-email page is `/sites/{site}/go-live`; its cards retain equal-width desktop columns and stacked mobile layout. Settings no longer loads their summaries.
- Checkout success/cancel, portal return, cancellation, and domain action redirects lead back to Go Live. Settings retains its unsaved-editor warning; Go Live protects pending requests and unsaved hostname input.
- Validation passed: focused 117 tests / 853 assertions, then full `composer ci:check` including frontend checks, Pint, PHPStan, production build, and 509 Pest tests / 4,010 assertions. User approved the new Go Live layout ("looks great!"). Keyboard and remaining interaction checks are still unverified. This change does not deploy or enable production domains.

### Staging sandbox continuation (2026-09-27)

- User established `https://staging.churchsite.app` with a separate SQLite database and set `APP_ENV=staging`, then reported saving new sandbox keys, prices, restricted portal configuration, and staging webhook secret. Extend the existing explicit sandbox test flag to staging for the controlled walkthrough; production and live-key access remain closed. No remote deployment or Worker routing change is included.
- Agent read-only checks verified local sandbox price/portal settings. Staging homepage and health endpoint returned 200 with valid TLS; an unsigned empty webhook POST returned 400. Signed delivery and remote configuration are still unverified.
- Staging sandbox support is implemented locally in both gates. All 11 configuration cases passed, followed by full `composer ci:check`: 514 tests / 4,020 assertions, frontend checks, Pint, PHPStan, and build. The Stripe API confirmed one enabled test webhook destination for staging with the expected API version and all 11 events. Signed delivery still requires testing; no remote deployment was performed.
