# Feature: Worker connection

**From build-plan:** feature 7a
**Build attempt:** 1
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

- [ ] **4. Prove the deployed test path after explicit deployment authorization.** Present exact application/Worker changes and rollback before remote actions. Deploy the origin receiver before enabling the test Worker route. Initially target only `test.timjossund.com/*` in the SaaS zone, subject to verifying the actual Cloudflare route behavior. Keep platform hosts excluded from later wildcard routing. Run authenticated transport proof and negative routing checks; observe no new request reaching the alias as its origin host. Do not remove the alias or reissue certificates just to prove independence: validate the Worker's fixed upstream destination and record redacted origin/Worker evidence. If stronger proof requires removing the test alias, obtain authorization for that exact change and preserve restoration steps.
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

Baseline observed 2026-09-27 before this specification: `composer ci:check` passed frontend formatting/lint/types, Pint, PHPStan, and 306 Pest tests with 2,888 assertions. The sandbox initially denied Pint's parallel worker socket; the permitted rerun passed. No implementation has occurred since that check.

Local Steps 1-3 verification: 69 Node Worker tests pass; `composer ci:check` passes with 362 Pest tests and 3,121 assertions, frontend checks, Pint, and PHPStan. Live Step 4 and independent review remain pending. Deployment instructions are in `docs/worker-domain-proxy.md`.

Required implementation gates: the focused Node Worker suite, focused Pest ingress/host-boundary tests, then `composer ci:check`. Exercise both runtimes with fixed protocol fixtures, including forged headers, wrong secrets, URL authority tricks, unknown/encoded paths, HEAD, upstream redirects, and failure responses. No live Stripe charges or Cloudflare provisioning in automated tests. No browser runner is configured; live observations remain a separate required step.

## Notes for the AI

Critique tightened three boundaries: use a fixed stateless ingress rather than forwarding into arbitrary platform routes; make the proof response distinguishable from ordinary `/up`; require live Worker evidence separately from the already successful manual alias test. Reuse native URL/fetch/crypto facilities and Laravel patterns. Do not add customer persistence, caches, queues, or API SDKs in 7a.

The operator selected the Worker architecture and approved the 7a/7b split. The user approved implementation by invoking the implement skill. Commit/push/deployment approvals remain separate. Never mark live proof complete merely because code/tests pass. Operator secrets must be configured privately, not requested in chat.

References: [Worker SaaS routing](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/start/advanced-settings/worker-as-origin/), [Cloudflare origin connection details](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/reference/connection-details/), [Full (strict)](https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/full-strict/).
