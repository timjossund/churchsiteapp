# Worker connection operations

Status: the operator deployed the Worker and application; controlled live transport proof passed on 2026-09-27. Final independent review and completion bookkeeping remain required. Use only `test.timjossund.com` during this rollout. Customer-domain onboarding and content routing remain Feature 7b.

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

1. Deploy the reviewed application commit to the existing Plesk application directory using its existing deployment procedure. Use the site's selected PHP binary. Confirm `public/hot` is absent on the server: it is still tracked in this repository and can reappear on checkout. Correcting its Git tracking is a separate fix.
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
