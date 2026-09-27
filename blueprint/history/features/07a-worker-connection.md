# Feature: Worker connection

**From build-plan:** feature 7a
**Build attempt:** 1
**Status:** verified
**Branch:** feature/worker-connection
**Archive:** blueprint/history/features/07a-worker-connection.md

## Goal

Prove a Cloudflare Worker can forward an operator-owned customer hostname to the existing Plesk Laravel application over verified HTTPS, authenticate the original hostname, and keep public traffic separate from platform routes. New customer domains must not require individual Plesk aliases or origin certificates. This is the transport foundation for Feature 7b, not customer-domain onboarding.

## In scope

- A small repository-owned Cloudflare Worker and deployment configuration, with the application remaining on Plesk.
- A fixed, stateless Laravel ingress endpoint that authenticates Worker requests and carries validated public-request context without rewriting the global application host.
- An opt-in, operator-configured proof at `https://test.timjossund.com/up`; it returns only a transport-health response, never a site's content.
- Reject platform routes on customer hosts and prevent forwarded traffic from reaching authentication, editor, billing, webhook, or subdirectory-preview routes.
- Focused Worker and Laravel tests, configuration documentation, and an explicit live rollout/rollback checklist scoped initially to the test hostname.

## Out of scope

- CustomHostname persistence, domain onboarding UI, Cloudflare API provisioning/reconciliation, hostname-to-site resolution, or public church content through the Worker. These belong to 7b.
- Enabling Stripe checkout, changing prices or subscription rules, account deletion changes, or collecting payment for this infrastructure test.
- Bare-domain support, customer-managed Worker code, caching, KV/D1 storage, Plesk automation, a new server, or global default-virtual-host changes.
- Changing live infrastructure, publishing the Worker, purchasing a plan, or removing the working alias/certificate without explicit deployment authorization.
- Repairing unrelated generated files or the previously identified tracked `public/hot` deployment issue. Keep that file absent on the server; a separate fix must address its Git tracking.

## Build loop

**Approved review-order exception:** The user explicitly approved independent review of completed local Steps 1-3 before the live deployment in Step 4. Audit may prepare and review this checkpoint with Step 4 unchecked and the feature not fully verified. This exception changes review ordering only: Step 4, deployment authorization, and final completion gates remain required. A pre-deployment receipt must not be represented as live verification; changes to the code or spec require a fresh review.

Use the configured `workflow.stepReview: every` and `checkpointCommits: enabled`: implement one step, report evidence, obtain step approval, and request any exact checkpoint commit separately. Do not treat spec approval as commit, push, or deployment approval. Independent review is selected because this feature introduces a network authentication boundary. Use the installed Audit skill's fresh-reviewer workflow before completion. Regular audit/check/try-guide remain manual unless requested. `/complete` owns the final feature commit and merge review.

## Build steps

- [x] **1. Add authenticated, isolated Laravel ingress.** Add configuration disabled by default, a small request validator/middleware and controller, and an endpoint outside the stateful web middleware group. Preserve the existing platform `/up` health route. Scope platform routes to the configured APP_URL hostname, including Fortify routes and the Stripe webhook; unexpected direct hosts must not render platform pages. Confirm the appropriate middleware position against the installed Laravel/Fortify bootstrap before editing. Keep the ingress error responses stateless and generic, even for unexpected errors.
      **Done when:** Pest proves enabled/disabled behavior, missing/wrong/malformed credentials, invalid forwarding metadata, unsupported methods, and platform-route isolation. An enabled authenticated GET for the configured proof host and `/up` returns the specified health JSON; HEAD has no body. Other forwarded paths/hosts return 404. Existing platform auth, publishing, billing, and health tests remain passing.

- [x] **2. Add the forwarding Worker.** Add a module Worker under `workers/domain-proxy/`, a configuration example with no active production route or secret, and focused tests using Node's built-in test runner and a mocked fetch seam. Forward GET/HEAD to the one fixed ingress endpoint, with a freshly constructed header set and redirects disabled. Return generic failures without exposing upstream exception details, credentials, or platform HTML. No Worker bundler/runtime dependency is needed for this plain JavaScript implementation.
      **Done when:** `node --test workers/domain-proxy/*.test.mjs` proves exact origin URL, fixed destination under malicious input, metadata integrity, dropped client credentials/cookies, redirect handling, HTTP-to-HTTPS behavior, HEAD semantics, disabled/missing configuration, method rejection, and upstream fetch failures. No test contacts Cloudflare or the production server.

- [x] **3. Verify the cross-runtime contract and document operations.** Share literal protocol fixtures between PHP and JavaScript tests where useful, without duplicating a test framework. Document exact configuration names, secret generation/storage, safe logging, route exclusions, existing Full (strict) origin TLS, staging order, and rollback in `docs/plesk-deployment.md` or a linked focused Worker runbook. Provide a single documented local verification sequence including the Worker test command and `composer ci:check`.
      **Done when:** Worker and Laravel tests agree on successful and invalid request fixtures; all declared local checks pass. The runbook identifies origin configuration and Worker routes that prevent recursion, and explains how to verify the test without relying on a Plesk alias. No API token/secret is committed, printed, placed in URLs, or embedded in client code. Independent review has a concrete code/configuration artifact to inspect.

- [x] **4. Prove the deployed test path after explicit deployment authorization.** Present exact application/Worker changes and rollback before remote actions. Deploy the origin receiver before enabling the test Worker route. Initially target only `test.timjossund.com/*` in the SaaS zone, subject to verifying the actual Cloudflare route behavior. Keep platform hosts excluded from later wildcard routing. Run authenticated transport proof and negative routing checks; observe no new request reaching the alias as its origin host. Do not remove the alias or reissue certificates just to prove independence: validate the Worker's fixed upstream destination and record redacted origin/Worker evidence. If stronger proof requires removing the test alias, obtain authorization for that exact change and preserve restoration steps.
      **Done when:** Record actual Worker-deployment identity, route, origin hostname, Full (strict) setting, successful proof response, rejected customer `/login`, `/dashboard`, `/stripe/webhook`, and `/s/...` requests, rejected unauthenticated direct ingress, and working platform `/up`/login plus an existing non-app hosted site. Evidence must distinguish operator reports from agent observations. Local tests alone do not check this box. If live deployment is not yet authorized, stop at a ready-to-deploy handoff with this step pending; do not claim the Worker path proven or begin 7b rollout.

## Files / areas

Existing integration points: `bootstrap/app.php`, `routes/web.php`, Fortify's installed routes, `config/app.php`, `app/Http/Controllers/PublishedSiteController.php`, `tests/Feature/PublishedSiteTest.php`, and billing/auth regression tests. Public rendering is read-only context in 7a; do not refactor it for speculative 7b needs.

New focused files may include `config/domain-proxy.php`, `routes/domain-proxy.php`, ingress middleware/controller under `app/Http/`, `tests/Feature/DomainProxyTest.php`, `workers/domain-proxy/worker.mjs`, its Node tests and Wrangler configuration example, and protocol fixtures under `tests/Fixtures/`. Document environment variables without manufacturing or copying production secrets. Preserve existing dirty generated Wayfinder files and prior unrelated changes.

## Data / contracts

### Transport version 1

No migration or persisted tenant mapping is introduced.

- Fixed upstream endpoint: `https://churchsite.app/_domain/request`. The Worker configuration supplies an absolute HTTPS URL with this exact path, no credentials, fragment, or query. Never derive the upstream authority from visitor input. Route this endpoint explicitly outside platform sessions/CSRF/Inertia handling.
- Configuration: `DOMAIN_PROXY_ENABLED` defaults false; `DOMAIN_PROXY_SECRET` is unset by default; `DOMAIN_PROXY_PROOF_HOST` is unset by default and set to `test.timjossund.com` only for the operator proof. Derive the platform host from APP_URL. Worker variables are `ORIGIN_ENDPOINT` and `PROOF_HOST`; its secret binding is `ORIGIN_SECRET`. Deployment pairs ORIGIN_SECRET with DOMAIN_PROXY_SECRET.
- Generate the shared secret from 32 cryptographically random bytes encoded as 64 lowercase hexadecimal characters. Require that format on both sides, compare in constant time in Laravel, and fail closed when unset or invalid. Provision through private environment/Worker secret storage. A bearer secret over verified TLS is sufficient for these read-only requests; do not add a database nonce store or signing framework.
- Worker sends `Authorization: Bearer <secret>`, `X-Churchsite-Proxy-Version: 1`, and `X-Churchsite-Original-Url: <absolute HTTPS URL from request.url>`. Ignore/overwrite all visitor-supplied versions of these headers. Original URL retains the path and query according to the URL API serialization and excludes fragments and credentials. Laravel parses it strictly, requires HTTPS, no credentials/fragment/nonstandard port, and an exact normalized DNS hostname. Reject ambiguous, malformed, control-character, IP-literal, and platform-host values. Do not use this header as a framework trusted proxy header.
- Construct outbound headers from an allowlist: the three protocol headers and optional visitor Accept. Do not forward Cookie, visitor Authorization, Forwarded, X-Forwarded-*, or arbitrary visitor headers. No session creation, flash state, or Set-Cookie response is permitted through ingress. No request body is forwarded.
- Only GET and HEAD are supported; return 405 with Allow for other methods. For valid HTTP GET/HEAD customer requests the Worker redirects to the same hostname/path/query over HTTPS without contacting the origin. Reject unexpected schemes and malformed URLs.
- In 7a the Worker and Laravel both allow only the configured proof hostname, and Laravel serves only exact `/up` without query parameters. Do not accept alternate encoded paths or normalize traversal into a successful health route. All other valid forwarded targets return 404. In 7b, database-backed hostname resolution will replace this proof allowlist for content; this feature must not preempt its ownership/billing rules.
- Successful GET: 200 JSON `{"status":"ok","transport":"worker","hostname":"test.timjossund.com"}` using the configured proof hostname, with `Cache-Control: no-store` and `X-Robots-Tag: noindex, nofollow`. HEAD returns corresponding headers with an empty body. This response distinguishes the authenticated receiver from the pre-existing Laravel health page.
- Disabled ingress returns 404; missing/wrong credentials return 403; authenticated malformed metadata returns 400; unknown host/path returns 404. JSON errors use `{"error":"not_found"}`, `{"error":"forbidden"}`, `{"error":"invalid_request"}`, or `{"error":"method_not_allowed"}`. Do not echo request metadata or credentials in errors.
- Worker misconfiguration returns 503 `{"error":"unavailable"}`. Network errors, unexpected redirects, or unexpected upstream response types return 502 `{"error":"upstream_unavailable"}`. Handle upstream redirects manually and never forward the shared secret to a redirect destination. Relay only expected ingress responses and safe response headers; all errors are non-cacheable. Preserve HEAD's empty body on failures too.
- The endpoint's original-URL contract is internal and does not authorize any tenant access. Feature 7b must resolve a persisted unique hostname, ready provider statuses, a published snapshot, and `Site::hasPaidDomainAccess()` before serving content.

### Routing and deployment

The existing server is shared: Plesk Obsidian 18.0.81.1 on Debian 13.7, nginx proxying to Apache. Reuse the `churchsite.app` virtual host and its valid certificate. Cloudflare SaaS edge certificates still cover customer hostnames. Do not change shared server defaults or deploy Plesk credentials with Laravel.

Cloudflare documents Worker routes for SaaS traffic. Test the narrow hostname route first. A later `*/*` SaaS route needs explicit no-Worker exclusions for platform/origin hosts to avoid recursion and preserve account traffic. Record actual dashboard/API results, not assumptions. Keep `workers.dev`/preview exposure disabled in the production configuration. Do not broaden the live route in this feature without explicit approval.

## Testing

Baseline observed 2026-09-27 before this specification: `composer ci:check` passed frontend formatting/lint/types, Pint, PHPStan, and 306 Pest tests with 2,888 assertions. The sandbox initially denied Pint's parallel worker socket; the permitted rerun passed.

Local Steps 1-3 verification: 69 Node Worker tests pass; `composer ci:check` passes with 362 Pest tests and 3,121 assertions, frontend checks, Pint, and PHPStan. Live Step 4 passed on 2026-09-27; the operator deployed application checkpoint `404eb00f9be676b0b895104fa38c1d576aad36ef` and Worker `churchsite-domain-proxy` (reported short version ID `c318548b`). The narrow route is `test.timjossund.com/*` in the SaaS zone, forwarding to `https://churchsite.app/_domain/request`, with operator-confirmed Full (strict). Agent-observed GET/HEAD proof, negative routing checks, correlated operator origin logs, and operator platform/shared-site checks are recorded in `docs/worker-domain-proxy.md`. The pre-deployment independent review passed; these final evidence/spec changes require a fresh receipt before completion.

Final completion checks on 2026-09-27: `node --test workers/domain-proxy/*.test.mjs` passed all 69 tests; `composer ci:check` passed frontend formatting/lint/types, Pint, PHPStan, and 362 Pest tests with 3,121 assertions. The initial final-check attempt found formatting in the review receipt and runbook; formatting was corrected and the full command passed.

Required implementation gates: the focused Node Worker suite, focused Pest ingress/host-boundary tests, then `composer ci:check`. Exercise both runtimes with fixed protocol fixtures, including forged headers, wrong secrets, URL authority tricks, unknown/encoded paths, HEAD, upstream redirects, and failure responses. No live Stripe charges or Cloudflare provisioning in automated tests. No browser runner is configured; live observations remain a separate required step.

## Notes for the AI

Critique tightened three boundaries: use a fixed stateless ingress rather than forwarding into arbitrary platform routes; make the proof response distinguishable from ordinary `/up`; require live Worker evidence separately from the already successful manual alias test. Reuse native URL/fetch/crypto facilities and Laravel patterns. Do not add customer persistence, caches, queues, or API SDKs in 7a.

The operator selected the Worker architecture and approved the 7a/7b split. The user approved implementation by invoking the implement skill. Commit/push/deployment approvals remain separate. Never mark live proof complete merely because code/tests pass. Operator secrets must be configured privately, not requested in chat.

References: [Worker SaaS routing](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/start/advanced-settings/worker-as-origin/), [Cloudflare origin connection details](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/reference/connection-details/), [Full (strict)](https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/full-strict/).


<!-- blueprint:completion {"schemaVersion":1,"specBytes":16573,"specSha256":"b7e77e46025b95b745cb29081c739ea7195f9b88363ba957030c4794fb1f5fd1","branch":"refs/heads/feature/worker-connection","head":"f1cfd86cadf8dccb06a49713e7fdc486cbae4cc2","baseRef":"refs/heads/main","baseCommit":"7e481b1cdbedf15238eef14d7be0784ac4719987","sourceTree":"b7b753ae93d83ff21cf6fbdb64131dfb9e6bc903","absentOptional":[]} -->

## Independent review

# Independent Review

**Status:** passed
**Target commit:** f1cfd86cadf8dccb06a49713e7fdc486cbae4cc2
**Base commit:** 7e481b1cdbedf15238eef14d7be0784ac4719987
**Base ref:** main
**Spec hash:** b7e77e46025b95b745cb29081c739ea7195f9b88363ba957030c4794fb1f5fd1
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-27T15:44:07.115347+00:00
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-27T15:46:45.923624+00:00
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Commands

- `git status --short`, `git rev-parse HEAD`, `git merge-base main HEAD`, and `shasum -a 256 blueprint/context/current-feature.md`: passed freshness checks; only the review evidence file differed before this pass.
- `git diff --stat main...HEAD`, `git diff main...HEAD`, and targeted complete source reads: reviewed all 18 changed paths across the complete recorded base-to-target delta.
- `git diff --check main...HEAD`: passed.
- `node --test workers/domain-proxy/*.test.mjs`: passed in this reviewer session, 69 tests, no skipped or todo tests.
- `php artisan test --compact tests/Feature/DomainProxyTest.php`: passed in this reviewer session, 56 tests and 233 assertions.
- `php artisan route:list --path=_domain -vv`: passed; ingress has no stateful route middleware.
- `npm run build`: passed in the builder's integration verification log `/tmp/churchsite-worker-build.log`, inspected by this reviewer; not independently rerun because it mutates generated output outside the review write scope.
- `composer ci:check`: passed in the builder's integration verification log `/tmp/churchsite-worker-built-ci.log`, inspected by this reviewer; frontend formatting/lint/types, Pint, PHPStan, and 362 Pest tests with 3,121 assertions passed after the fresh build. The broad suite was not independently rerun against the restored stale generated assets.
- `git diff 404eb00f9be676b0b895104fa38c1d576aad36ef HEAD -- app bootstrap config routes workers tests`: empty, confirming the reviewed application and Worker sources match the documented deployed application checkpoint.
- Targeted test-marker search: no skipped, focused, or placeholder tests found in the new Worker and Laravel tests.

## Evidence

- Fresh isolated review used the project-local Audit skill and independent-review contract without the builder transcript. The request's codex/gpt-6-astra identity and automatic execution match this reviewer. HEAD is `f1cfd86cadf8dccb06a49713e7fdc486cbae4cc2`; merge base with local `main` is `7e481b1cdbedf15238eef14d7be0784ac4719987`; the tracked verified spec hashes to `b7e77e46025b95b745cb29081c739ea7195f9b88363ba957030c4794fb1f5fd1`. No ignored-spec snapshot is required.
- Reviewed the complete Worker handler, example configuration and ignore rules; Laravel controller, response helper, host middleware, bootstrap, route and configuration; both test suites and shared fixtures; all changed planning/specification and deployment documents. Followed existing web/Fortify/Cashier routing and installed Laravel middleware composition to confirm the host guard runs before trusted-proxy and session handling. Dependency code was read only to confirm this integration, not audited broadly.
- Quality: implementation uses existing Laravel mechanisms and native Worker URL/fetch APIs without new runtime dependencies, persistence, or speculative customer-domain abstractions. Confirmed alignment with the approved 7a scope and local coding standards. One stale runbook statement is recorded as F-03.
- Security: reviewed constant-time bearer verification, disabled/misconfigured fail-closed behavior, strict original URL and hostname validation, proof-only access, actual Host isolation, stateless generic exceptions, fresh outbound header allowlisting, fixed upstream destination, manual redirect handling, response reconstruction, cookie suppression, and HEAD behavior. No source finding requires changes.
- Performance: the controlled proof performs one fixed upstream fetch and constant-size response work, with no database queries, loops over customer data, persistence, cache, or new dependencies. No concrete performance defect found; no live profiling claim is made.
- Tests: fresh focused suites exercise credentials, malformed metadata, host isolation including forwarded authority, platform-path rejection, shared protocol fixtures, upstream redirects/errors, safe response headers, and HEAD. Inspected the broader successful integration log as regression evidence. Generated build/dependency/cache files are excluded from the feature review; main's already integrated removal of `public/hot` is not a Worker source change.
- Live Step 4 evidence was inspected in `docs/worker-domain-proxy.md`, not rerun by this reviewer. It records earlier agent HTTP observations at 15:29:24-26 UTC for the distinctive GET/HEAD proof, redirect, rejected platform/encoded/traversal paths and unsupported method, plus an operator-supplied correlated platform ingress log. Operator-reported route, Full (strict), Worker name/short version, platform login/health, direct-ingress rejection and shared-site checks are clearly distinguished from agent HTTP observations. This supports the controlled transport proof without alias deletion, not customer-content readiness.

## Findings

- F-03 [P3] open: correct the runbook's obsolete claim that `public/hot` remains tracked after integration. Added this pass; does not block completion.
- F-02 [P2] remains open and unchanged. Its existing workspace contrast issue is unrelated to this delta and was not re-reviewed or accepted.
- No P0 or P1 finding is open or fixed. No security, performance, or tests defect was confirmed in this feature.

## Remaining risk

- `npm run build` and the complete `composer ci:check` were inspected as builder-run evidence, not independently repeated here. The clean reviewed target still contains older tracked generated assets; production must build from source as the deployment guide requires. The focused review tests do not depend on those Vite assets.
- Live curl/browser/Cloudflare/Plesk checks were not performed by this reviewer. Recorded proof uses application checkpoint `404eb00f9be676b0b895104fa38c1d576aad36ef`; relevant application/Worker source is unchanged at this target. The full Worker version UUID and unrelated hosted site's identity are not recorded, so live identity detail is limited to the documented operator reports and short version `c318548b`.
- No browser runner, dedicated security scanner, or performance benchmark is configured for this work. No current vulnerability-scan or load-test result is claimed. Check is not required by the request.
- F-03 is a minor operational documentation cleanup; F-02 remains a separate known accessibility issue. Neither is accepted by this review. Customer hostname resolution, provider/billing eligibility and published content remain outside 7a and require Feature 7b.
