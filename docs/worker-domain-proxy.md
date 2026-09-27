# Worker connection operations

Status: Feature 7a controlled live transport proof passed on 2026-09-27 using `test.timjossund.com`. Feature 7b customer onboarding and content routing are implemented locally with rollout gates closed. Its staged checklist below remains unperformed; the earlier proof is not customer-flow acceptance.

## Configuration and local verification

The Worker module is `workers/domain-proxy/worker.mjs`. It requires no build step or runtime package. `wrangler.example.jsonc` is a configuration example with no routes and with workers.dev and preview URLs disabled. It intentionally contains no secret. No Wrangler installation or Cloudflare login has been performed for these tests.

Run from the repository root:

```sh
node --test workers/domain-proxy/*.test.mjs
composer ci:check
```

Both commands must pass. `tests/Fixtures/domain-proxy-v1.json` contains public, test-only examples exercised by both runtimes. Its repeated-letter credential must never be used outside tests. Mocked fetch tests establish the protocol contract, not Cloudflare routing, live TLS, or actual deployment.

| Location                      | Setting                   | Value                                     |
| ----------------------------- | ------------------------- | ----------------------------------------- |
| Plesk application environment | `APP_URL`                 | `https://churchsite.app`                  |
| Plesk application environment | `DOMAIN_PROXY_ENABLED`    | `true` only when deploying the receiver   |
| Plesk application environment | `DOMAIN_PROXY_PROOF_HOST` | `test.timjossund.com`                     |
| Plesk application environment | `DOMAIN_PROXY_SECRET`     | Private 64-character lowercase hex secret |
| Worker variable               | `ORIGIN_ENDPOINT`         | `https://churchsite.app/_domain/request`  |
| Worker variable               | `PROOF_HOST`              | `test.timjossund.com`                     |
| Worker secret binding         | `ORIGIN_SECRET`           | Same private secret as Laravel            |

Generate the secret privately using a cryptographically secure generator (32 random bytes, hexadecimal encoding). Save it directly into a password manager/private temporary file rather than shell history or chat; for example, run `umask 077` then `openssl rand -hex 32 > /private/operator/chosen/path/domain-proxy-secret` after substituting an existing private directory on the server. That example is not a command to paste unchanged. Provision the value through the server environment and Cloudflare's secret-binding interface. Do not put it in Wrangler `vars`, repository fixtures, URLs, screenshots, command arguments, tickets, or logs. Do not use curl verbose output with credentials. Worker-local `.dev.vars`/`.env` files and `.wrangler` output are ignored.

Secret rotation: disable the test route, replace both private values, refresh Laravel's config cache, redeploy the Worker secret, then re-enable and repeat proof checks. Requests fail closed while the values disagree.

## Review before deployment

A separate explicit approval is required for the following remote changes. Record the current application commit, Worker version (if any), DNS records, Worker routes/exclusions, and SaaS hostname/fallback status before beginning. Keep the existing Plesk alias and certificate in place; their deletion is not part of this rollout.

1. Deploy the reviewed application commit to the existing Plesk application directory using its existing deployment procedure. Use the site's selected PHP binary. Confirm `public/hot` is absent on the server. It has been removed from Git; a stale server copy must still be kept out of production.
2. Confirm `APP_URL` is the canonical host. The new global host guard deliberately rejects direct requests to other hostnames, including the old test alias and `www.churchsite.app` unless the web server redirects it before Laravel. The previous alias `/up` will return 404 once this code is deployed until the Worker route is active. Platform `churchsite.app/up` remains available. This change does not change routing on the server's other websites.
3. Set the receiver's private configuration and run `php artisan config:cache` with the application's PHP binary. If route caching is used by this deployment, rebuild it with `php artisan route:cache` so `/_domain/request` is registered. Do not generate a new APP_KEY. No migration is introduced by 7a.
4. Confirm `https://churchsite.app/up` and platform login work. An unauthenticated GET to `https://churchsite.app/_domain/request` must return 403 JSON with no cookie, not a login redirect. Disabled/misconfigured ingress returns 404.
5. Deploy the Worker source and variables through Cloudflare, with `ORIGIN_SECRET` stored as a secret binding, workers.dev/preview URLs disabled, and no route yet. The example configuration has `routes: []`; if using Wrangler, explicitly supply the example path and review the version being deployed. Do not mix dashboard-managed routes with later deployments of an empty routes list: choose one route owner and retain the actual reviewed configuration.
6. In the **churchsite.app SaaS zone**, add the narrow Worker route `test.timjossund.com/*` assigned to this Worker. Confirm Cloudflare accepts this SaaS custom-hostname route and that requests invoke this version. If the account rejects it or traffic does not invoke the Worker, stop and retain the exact error; do not silently substitute a zone-wide route.
7. Keep Full (strict). The upstream TLS connection and HTTP Host target `churchsite.app`, whose existing Plesk certificate is valid. `X-Churchsite-Original-Url` carries the test hostname only after bearer authentication. No alias certificate is used by this upstream connection. Preserve the existing fallback DNS record for the narrow test; review any later change to an originless fallback separately against the actual Cloudflare configuration.

[Cloudflare SaaS Worker routing](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/start/advanced-settings/worker-as-origin/) documents wildcard matching and exclusions. For the future 7b rollout, a SaaS-zone `*/*` route must have more-specific no-Worker routes for `churchsite.app/*` and its platform/origin subdomains (for example `*.churchsite.app/*`). Verify these exclusions before enabling a wildcard: a fetch to the fixed origin must never re-enter the Worker. Do not enable the wildcard during this test. DNS for `test.timjossund.com` remains DNS-only CNAME to the SaaS target; both Cloudflare hostname and certificate status must stay active.

## Required live evidence

Run the following non-secret requests after authorized deployment. These commands make HTTP requests; they do not change application data.

```sh
curl -i https://test.timjossund.com/up
curl -I https://test.timjossund.com/up
curl -i https://test.timjossund.com/login
curl -i https://test.timjossund.com/dashboard
curl -i https://test.timjossund.com/stripe/webhook
curl -i https://test.timjossund.com/s/example
curl -i https://churchsite.app/_domain/request
curl -I https://churchsite.app/up
```

Expected: the test GET returns exactly `{"status":"ok","transport":"worker","hostname":"test.timjossund.com"}` with 200. It must not show the earlier Laravel "Application up" page. HEAD returns 200 without a body. The four test platform paths return 404 JSON, no redirects and no session cookies. Direct unauthenticated ingress returns 403. Platform health returns 200. The Worker uses `no-store` and `noindex, nofollow` for all these public responses.

Also test HTTP-to-HTTPS redirection on the test host, platform login in a browser, and an existing unrelated website on the shared server. Test an unsupported method only on the test hostname: `curl -i -X POST https://test.timjossund.com/stripe/webhook` must return 405 with `Allow: GET, HEAD`, without reaching Stripe handling.

Record the time, application commit, Worker deployment/version ID, assigned route, Full (strict) setting, and each result. Label operator reports and agent observations separately. For independence from the Plesk alias, confirm the deployed source's fixed endpoint and use a redacted access-log entry for `churchsite.app/_domain/request` correlated with a test request plus Worker invocation evidence. Standard logs should record host/path/status, never Authorization or full forwarding metadata. Do not enable request-header logging or publish private diagnostics. A screenshot of an active edge certificate alone is not proof of this path.

Troubleshooting: 403 usually means mismatched secrets; 404 can mean disabled receiver, wrong host/path, or inactive Worker routing; 502 means network, redirect, or invalid upstream response; 503 means Worker configuration is missing/invalid or the receiver reports unavailable. Inspect private server diagnostics to distinguish causes; never weaken TLS or expose detailed errors to visitors.

## Rollback

Disable/remove only the narrow test Worker route first. Set `DOMAIN_PROXY_ENABLED=false` and refresh config cache. Keep secrets private and revoke them if compromised. This leaves platform routes working, and deliberately makes the test alias return 404 under the new host guard. Do not treat the old alias as an automatic fallback into platform pages.

If the application itself regresses, restore the previously recorded application release and its configuration through the existing deployment procedure, rebuild config/route caches as applicable, and check platform health/login and the unrelated hosted site. No database rollback is needed for 7a. Restoring the previous release also restores the old alias behavior; it does not prove Worker connectivity.

The controlled Step 4 proof passed as recorded below. Do not enable Stripe checkout, broaden host eligibility, or begin customer rollout as part of this test.

## Live proof recorded 2026-09-27

The operator deployed application checkpoint `404eb00f9be676b0b895104fa38c1d576aad36ef` and Worker `churchsite-domain-proxy`, reporting short version ID `c318548b`. The full Worker version UUID was not supplied. Operator-reported configuration: route `test.timjossund.com/*` in the `churchsite.app` SaaS zone, fixed endpoint `https://churchsite.app/_domain/request`, proof host `test.timjossund.com`, and Full (strict). The existing alias/certificate were retained; no shared-server default was changed.

Agent HTTP observations at 15:29:24-26 UTC, using unauthenticated curl requests without following redirects:

| Request on test.timjossund.com           | Observed result                                                                         |
| ---------------------------------------- | --------------------------------------------------------------------------------------- |
| HTTPS GET /up                            | 200, exact JSON `{"status":"ok","transport":"worker","hostname":"test.timjossund.com"}` |
| HTTPS HEAD /up                           | 200, empty body                                                                         |
| HTTP GET /up                             | 308 to https://test.timjossund.com/up                                                   |
| HTTPS GET /stripe/webhook and /s/example | 404, `{"error":"not_found"}`                                                            |
| HTTPS POST /stripe/webhook               | 405, `{"error":"method_not_allowed"}`, Allow GET, HEAD                                  |
| HTTPS GET /%75p                          | 404, `{"error":"not_found"}`                                                            |
| HTTPS GET /x/../up with --path-as-is     | 400, `{"error":"invalid_request"}`                                                      |

Successful HTTPS proof responses had JSON content type, no-store, noindex/nofollow, and no Set-Cookie. The operator supplied a correlated `churchsite.app` Plesk access-log entry at 15:29:24 UTC: `200 GET /_domain/request HTTP/1.1`. Together with the distinctive authenticated proof response and fixed destination in the reviewed source, this supports the Worker-to-platform ingress path without deleting the alias.

Operator-reported browser checks: customer `/login` and `/dashboard` returned 404; direct unauthenticated platform `/_domain/request` returned 403; platform `/up` and login worked; an existing unrelated hosted site also worked. These are operator reports, not agent browser observations. The unrelated site's hostname was not recorded.

Deployment troubleshooting resolved stale route/config caches and restored the Plesk document root to `/var/www/vhosts/churchsite.app/httpdocs/public`. No production secrets were recorded here. This proof establishes the controlled transport only; paid customer content and onboarding remain Feature 7b.

## Customer hostname reconciliation (7b, local implementation only)

The domain flow remains disabled by `customer-domains.enabled = false`, and Stripe checkout remains closed. The new scheduler and owner check route honor that gate. Account-deletion cleanup can still reconcile records already marked for removal while rollout is disabled; it cannot provision new hostnames. The following configuration describes the future reviewed rollout; it does not authorize activation, deployment, or remote changes.

- `CLOUDFLARE_SAAS_ZONE_ID`: the single SaaS zone's 32-character identifier.
- `CLOUDFLARE_SAAS_API_TOKEN`: a private API token restricted to that zone, with **SSL and Certificates Write**, the permission documented for hostname creation and deletion. Do not log request headers or provider response bodies.
- `CUSTOMER_DOMAINS_ORIGIN_HOST`: an additional hostname excluded from customer registration, if the deployment uses a separate origin. This does not change the Worker's fixed `ORIGIN_ENDPOINT`.
- The customer CNAME target remains `customers.churchsite.app`. The existing fallback origin, Worker origin endpoint, and Full (strict) configuration remain unchanged. Creating a hostname requests `ssl.method=txt` and `ssl.type=dv` without custom origin overrides.

After rollout authorization, the existing Laravel scheduler runs `domains:reconcile` every five minutes with overlap protection. A run selects at most 50 oldest-checked records, processes groups of ten, and stops starting work after four minutes. A single in-flight check may finish after that deadline. HTTP connections time out after three seconds and requests after ten; DNS uses the server's resolver. Per-record database leases also serialize scheduler and owner-triggered checks; an abandoned lease can be reclaimed after two minutes. Lease identities prevent older results from overwriting newer checks. The owner refresh endpoint is limited to six requests per minute.

Provisioning begins only with confirmed paid access. Readiness requires exact application TXT ownership, the configured CNAME target, and both provider hostname and certificate statuses `active`. Provider failures and fresh negative evidence revoke readiness. Only validated DNS instruction fields and separate statuses are persisted for display; provider error bodies are discarded. The application ownership TXT, provider hostname TXT, and certificate-validation records are distinct.

### Uncertain create recovery

Cloudflare's standard SaaS plans do not provide custom metadata; it is an Enterprise paid add-on. This integration does not assume that add-on or undocumented API idempotency. Each connection has a persisted operation UUID; the application records the attempt and zone before sending a create request, and stores the returned provider ID before interpreting readiness. A matching hostname from a list response alone is never adopted or deleted.

If a create response is lost or its identity is malformed, subsequent reconciliation lists the exact hostname but leaves the reservation in `operator_required`. It does not issue another create. Even an empty list does not prove an earlier uncertain request cannot still complete. A disconnect in this state retains the reservation and operation record. This is an intentional manual recovery case; successful creations, reads, and removals reconcile automatically.

For `operator_required`, inspect the private operation ID, site, hostname, attempt timestamp, stored zone, and any stored provider ID. Compare them with the provider's administrative/audit evidence to establish which operation created the object. Preserve the record while attribution is uncertain. Do not reset the attempt marker, delete an unmatched provider object, attach an ID based only on its hostname, or cascade-delete the site. Once attribution or definitive non-creation has been established, prepare a separately reviewed targeted repair to the exact operation and provider ID. No public endpoint bypasses this requirement.

A known managed ID is removed only when an exact hostname lookup still returns that ID. A successful delete acknowledgement or a validated exact lookup proving absence releases its reservation. A different ID, malformed response, timeout, or changed zone preserves the cleanup record for retry or operator resolution. Disconnect and replacement preserve the site subscription. Account deletion first follows the existing paid-period policy, then commits domain routing revocation and attempts provider cleanup outside its database transaction. Cleanup failures retain the account and domain IDs for later retries; unresolved create attribution requires operator resolution. A final locked check prevents deletion while domain records or newly confirmed paid time remain.

API contracts checked against official documentation on 2026-09-27: [create and permissions](https://developers.cloudflare.com/api/resources/custom_hostnames/methods/create/), [exact hostname lookup](https://developers.cloudflare.com/api/resources/custom_hostnames/methods/list/), [delete and permissions](https://developers.cloudflare.com/api/resources/custom_hostnames/methods/delete/), and [custom metadata plan limits](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/plans/). Automated evidence uses offline HTTP/DNS fakes only; no live provider permissions, provisioning, DNS, or TLS have been verified for 7b.

### Billing integration (7b, local implementation only)

A new unpaid connection saves validated hostname intent and opens the existing Stripe Checkout flow immediately, before DNS completion. The billing endpoint also checks saved intent, so posting to it directly cannot bypass domain entry. Paid sites reuse their subscription on connection or replacement. Failed or canceled checkout leaves intent and existing checkout identity available for retry. Domain and checkout rollout flags both remain closed.

Only verified webhook reconciliation grants paid access. When paid access lapses or resumes after a lapse, the webhook invalidates domain readiness and any in-flight readiness check. A successfully returned Cloudflare create ID is still retained for the same connection operation, even when billing invalidates its check. Fresh scheduled or owner-triggered DNS/provider checks must restore readiness before serving can resume. A browser success URL grants no access, and disconnected names stay disconnected.

### Published customer content transport (7b, local implementation only)

The Worker retains version 1 for the narrow proof host. Customer content uses request version 2, and requires both the Worker string variable `CUSTOMER_DOMAINS_ENABLED=true` and the application domain gate. The example Worker configuration keeps this variable false and has no routes. No production routing or gate has been changed.

Version 2 resolves the registered hostname before checking ownership, DNS/provider readiness, current paid access, and a valid frozen publication. It serves only Home, published page paths, referenced media under `/_media/<id>`, and allowlisted compiled assets under `/build/assets/`. Control paths, encoded aliases, arbitrary build files, and the platform application bundle are denied. Platform/subdirectory publication remains independent and retains preview noindex behavior. Custom-domain HTML omits preview-only noindex. Customer query strings are ignored without reflection; page and social URLs remain canonical, so sharing/tracking parameters do not break pages. The version 1 proof still rejects queries.

The renderer receives the hostname explicitly; it never rewrites global request/application host state or dispatches platform routes. The installed build uses same-origin module imports and font URLs, so custom-domain pages use a narrow manifest allowlist for the published entry, its static dependencies, the shared stylesheet, and generated fonts. This avoids requiring cross-origin font/module configuration. Asset requests still require an eligible published tenant, and never redirect to the platform. The template bypasses the development hot-file mechanism for customer content.

Successful upstream content requires `X-Churchsite-Response-Version: 2`, the exact `X-Churchsite-Hostname`, and `X-Churchsite-Content` equal to `html`, `media`, or `asset` for the requested path. HTML, PNG/JPEG, CSS/JavaScript, and generated font content types are explicitly allowed; asset MIME must match its extension. The Worker rejects content carrying redirects, cookies, incorrect metadata, or unexpected types/statuses. It constructs public headers itself and does not forward protocol headers. Responses remain no-store and nosniff; HEAD retains status/type with no body. Error responses remain generic and non-indexable.

Local PHP tests exercise frozen Home/additional pages, links/social metadata, referenced media, tenant and storage-key isolation, access revocation, every compiled published asset, and reserved-path publication validation. Node tests exercise content contracts, stripped visitor headers, malformed upstream responses, HEAD, and shared version 2 denied-path fixtures. These are offline tests, not live browser, DNS/TLS, or deployed Worker observations. The controlled rollout still needs browser navigation, menu operation, stylesheet/font/module loads, media, and denied-route checks against the actual deployed versions.

## Feature 7b staged rollout checklist

This checklist is a ready-to-deploy handoff, not deployment or activation evidence. Feature 7a's recorded proof does not verify customer onboarding. Use a separate operator-owned hostname beginning with `www.`, for example `www.<operator-test-domain>`; the existing `test.timjossund.com` remains the version 1 proof host and is not a valid customer-format connection. Do not reuse a manually provisioned hostname as an application-managed connection.

### Configuration and deployment order

1. Complete the application verification and independent review, then record the approved application commit and Worker source/version. Obtain separate approval for the intended test deployment and exact routing changes. Record database engine/version, runtime, scheduler user/PHP path, rollback release, and the existing routes and exclusions. Back up the target database using the established hosting procedure.
2. Deploy the reviewed application and build its assets with the existing lockfiles. Keep `public/hot` absent. Inspect `php artisan migrate:status` and apply the two additive 7b migrations with the target PHP runtime: `2026_09_27_000001_create_custom_hostnames_table` and `2026_09_27_000002_add_reconciliation_to_custom_hostnames`. These create the domain table and add reconciliation fields. Verify its foreign key and unique constraints on the actual deployment database; local SQLite evidence is not MySQL locking evidence.
3. Keep the application gates closed during installation. `customer-domains.enabled` in `config/customer-domains.php` and `site-billing.checkout_enabled` in `config/site-billing.php` remain false outside explicitly enabled local or staging sandbox testing. For the authorized sandbox walkthrough, `CUSTOMER_DOMAINS_LOCAL_TESTING=true` opens both gates when `APP_ENV=local` or `APP_ENV=staging` and `STRIPE_SECRET` starts with `sk_test_`. It cannot enable production or live-key checkout. Production activation still requires a separately reviewed change. Adding `CUSTOMER_DOMAINS_ENABLED` to Laravel's `.env` alone does not open either gate. That name is a **Worker** string variable only.
4. Configure the application zone-scoped token and environment names listed above, along with the existing `DOMAIN_PROXY_ENABLED`, `DOMAIN_PROXY_PROOF_HOST`, and private `DOMAIN_PROXY_SECRET`. Confirm the configured SaaS zone, existing fallback, CNAME target, and fixed endpoint all describe the same intended setup. Refresh config, route, and view caches through the existing deployment procedure. Never print token or secret values as a diagnostic.
5. Ensure the hosting scheduler runs `php artisan schedule:run` every minute with the application's persistent cache. `domains:reconcile` is scheduled every five minutes; it makes no provisioning calls while its gate is closed. Inspect scheduling with `php artisan schedule:list`. Account-deletion cleanup remains able to finish already requested removals while the domain gate is closed. Do not run the account finalizer against real users just to smoke-test scheduling.
6. Deploy the reviewed Worker while keeping `CUSTOMER_DOMAINS_ENABLED=false`. Preserve the version 1 proof route and repeat its health/denial checks. Choose one route owner (dashboard or reviewed Wrangler configuration), and retain that actual configuration; the repository example intentionally has no routes.
7. In a separate application environment with Stripe test credentials, authorize only the chosen customer test route and its provider operations. Apply reviewed local/test gate changes and configure Worker `CUSTOMER_DOMAINS_ENABLED=true`. Keep the origin/platform no-Worker exclusions intact. Begin with the exact customer hostname route; broader routing needs its own later approval. Maintain Full (strict). Do not switch production Stripe credentials into test mode or charge a live card to satisfy this checklist.
8. Perform the sandbox billing walkthrough and browser evidence below. If the separate environment, provider permissions, or DNS control is unavailable, stop at this handoff with those items explicitly unverified. Only after evidence passes should the operator approve production gate changes and any wider SaaS routing. No customer-specific Plesk alias or certificate should be added for the Worker content path.

### Browser and network acceptance

Use the owner interface checklist in [Domain setup review](domain-setup-review.md) and the signed-event steps in [Billing operations](billing-operations.md). Record the browser, viewport, time, exact application commit, full Worker deployment/version ID, route/exclusions, test hostname, separate test environment, and whether each observation is direct or operator-reported.

- Enter the valid `www` hostname and select each billing interval on separate disposable sites. Verify Stripe test checkout displays the intended recurring price and immediate payment. Cancel once and return; retry must retain the same intent. Return before a signed webhook arrives; content must remain unavailable. Confirm repeated submissions cannot create a second subscription.
- Copy and install the application's ownership TXT, connection CNAME, and the actual provider hostname/certificate records shown by the interface. Record DNS answers, application ownership result, provider hostname status, and SSL status independently. Neither paid access nor a certificate alone permits content. A new hostname must have an attributable application-created provider ID; uncertain create operations require the documented operator recovery.
- With the site unpublished, customer content must be denied. Publish Home plus a second page with navigation and an image. Open the customer root and second-page URL, use navigation and the mobile menu, and refresh the second page directly. Inspect stylesheet, JavaScript module, font, and `/_media/` requests: successful content stays on the customer hostname with no platform redirects, mixed content, cookies, or console errors. Confirm HTTPS and canonical/social URLs use the customer hostname. Editing a draft must not change that frozen publication.
- Check GET and HEAD for the root, second page, a referenced image, and compiled assets. HEAD has matching status/type and no body. Check `/login`, `/dashboard`, `/editor`, `/checkout`, `/stripe/webhook`, `/s/example`, an arbitrary asset, and another site's media ID: no account HTML, redirect, or session cookie may appear. POST to a customer path must be rejected without reaching a platform action. Inspect no-store and nosniff headers.
- Remove or mismatch the ownership TXT/CNAME, then request a check: readiness and serving must stop. Restore records and verify recovery. Exercise pending certificate status with controlled provider evidence; the panel must distinguish it from ownership and connection. Keep provider IDs, tokens, and raw responses out of browser evidence.
- Exercise canceled renewal, actual paid expiry, and resubscription using the separate Stripe test environment and controlled clock strategy from the billing runbook. Expiry must deny customer content while retaining configuration and free shareable publishing. Resubscription needs a fresh readiness check. Browser query parameters cannot restore access.
- Disconnect an active test hostname. It must stop serving immediately, retain billing, and prevent a replacement until exact managed-host removal completes. Verify the separate Cancel renewal action. Connect the replacement only after cleanup and confirm the old hostname stays denied. Use offline fakes for uncertain provider failures unless a separately approved sandbox fault scenario exists.
- Recheck `churchsite.app/up`, login, editor, free subdirectory publication, the old proof hostname, and an identified unrelated hosted site. Record that site's identity privately if it should not appear in public evidence. No shared-server default, per-customer virtual host, or weaker TLS setting should be needed.

### Feature 7b rollback

Record each change before activation so it can be reversed precisely. For a customer-routing regression, disable only the new customer route(s) and set Worker `CUSTOMER_DOMAINS_ENABLED=false`, retaining the version 1 proof route if healthy. Close the application's customer-domain and new-checkout gates through the reviewed configuration and refresh caches. This prevents new onboarding; it does **not** cancel subscriptions, refund payments, or delete provider hostnames. Existing owners must retain access to billing management on the platform.

Keep the domain table and all operation/provider IDs. Do not run these migrations down after provider operations or customer intent exist: removing the records would lose cleanup attribution. Prefer a forward repair with serving disabled. Restoring a prior application release requires checking that it preserves domain cleanup and account-deletion restrictions; a release predating 7b cannot safely finalize domain-bearing accounts without an explicit recovery plan. Retain the database foreign key and pause automated deletion if the chosen prior release cannot perform that cleanup.

Recheck platform health/login, independent shareable publishing, the proof route, and unrelated hosted sites after rollback. Communicate any interruption and billing handling through the operator's approved process. No automatic refund, cancellation, remote deletion, or schema rollback is authorized by this runbook.

### Evidence still required

Local automated tests and a user-approved two-column settings layout do not establish live provider permissions, Stripe sandbox completion, every browser state, target-MySQL concurrency, DNS/TLS, or deployed customer content. Record those observations here after an authorized rollout; retain failures and unavailable items explicitly. Independent review and the final feature completion workflow remain separate gates.

### Local test hostname evidence, 2026-09-27

The operator selected local site `test` (ID 16) and `www.churchsite-test.timjossund.com`, then added the application ownership TXT, DNS-only connection CNAME, and both returned certificate-validation TXT values. Agent DNS reads confirmed the expected CNAME and ownership challenge. Subsequent direct Cloudflare API reads confirmed hostname and SSL statuses both `active`. Local state at 18:50:20 UTC recorded confirmed paid access, verified ownership, matching CNAME, and domain state `ready`; the site was still unpublished.

A direct HTTPS request to the customer root verified the edge TLS certificate successfully but returned HTTP 526. This does not establish working origin TLS or Worker content routing. The local tenant record and sandbox payment have not been deployed to the fixed Worker origin, and no new customer Worker route or production gate was enabled by the agent. The earlier narrow proof configuration cannot be treated as customer-content acceptance. Publication, reviewed test-environment deployment, correct Worker routing, navigation/assets/media, and platform isolation checks remain pending. Preserve Full (strict); resolve the intended Worker path rather than weakening TLS or adding a customer-specific Plesk alias.

### Staging sandbox setup, 2026-09-27

The operator supplied `https://staging.churchsite.app`, reports a separate SQLite database, and reports saving new Stripe sandbox credentials, prices, portal configuration, and a staging webhook signing secret. Agent HTTPS checks found the application homepage and `/up` returning 200 with valid TLS. An unsigned empty POST to `/stripe/webhook` returned 400, confirming rejection, not signed-event delivery. Read-only Stripe checks using the local key verified the two sandbox prices and restricted portal configuration. Remote credentials and signed delivery are not yet verified.

The existing `CUSTOMER_DOMAINS_LOCAL_TESTING` flag now supports both `local` and `staging`, still requiring a test secret key. Deploy this configuration change before enabling staging testing; retain `APP_ENV=staging`, `APP_DEBUG=false`, and `APP_URL=https://staging.churchsite.app`. Refresh the staging configuration cache after environment changes. This local code change does not deploy the app, change Worker routes, or establish working customer-domain content. Use a fresh disposable site after changing Stripe sandboxes; old customer/subscription IDs belong to the previous sandbox.

The Stripe API subsequently confirmed an enabled sandbox webhook destination at the staging URL, API version `2026-08-26.dahlia`, with all 11 documented events. This verifies the destination configuration but not its saved server-side signing secret or successful signed delivery.
