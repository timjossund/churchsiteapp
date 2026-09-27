# Plesk deployment and Cloudflare preparation

Status as of 2026-09-27: the operator reports that `churchsite.app` is live on Plesk Obsidian 18.0.81.1, Debian 13.7 x86_64. This server hosts other websites. Administrator and SSH sudo/root access are available. The agent has not performed remote changes; setup observations below are operator reports and screenshots; the linked Worker runbook separately labels agent HTTP observations.

## Confirmed setup and selected next architecture

- Cloudflare for SaaS is enabled for `churchsite.app`; `origin.churchsite.app` is the active fallback origin. The instructed customer CNAME target is `customers.churchsite.app`; verify its actual DNS record during rollout.
- The controlled test `test.timjossund.com` has active Cloudflare hostname and edge-certificate statuses. The operator added it as a Plesk alias for `churchsite.app`, then included it in the Let's Encrypt origin certificate.
- After switching the `churchsite.app` zone to Full (strict), the operator reports that both the platform and `https://test.timjossund.com/up` work without errors. This proves the manually configured test path, not automated tenant routing or database/mail/storage readiness.
- Plesk settings supplied by the operator show nginx proxy mode enabled, nginx PHP/static serving disabled, nginx cache disabled, and no additional Apache/nginx directives.
- The operator selected a Cloudflare Worker for automatic customer-domain forwarding. Planned transport: Worker forwards to a fixed HTTPS application origin and authenticates the original customer hostname for Laravel. The application must reject forged forwarding headers, isolate public content from account routes, and enforce hostname ownership and paid access. The operator deployed the Worker and Laravel endpoint; the controlled live Worker proof passed on 2026-09-27. See [Worker connection operations](worker-domain-proxy.md).
- Prove Worker transport with the controlled test before customer rollout. The manual alias/certificate must not be required by the final Worker path. Keep the existing working setup until the replacement is verified.

The remaining sections are preparation guidance; their proposed settings are not additional claims of completed deployment.

## 1. Establish the deployment target

Record the Plesk server OS/version, PHP handler, application directory, deployment user and Git access method. Confirm whether the server already hosts other websites before changing any default virtual host. Confirm that `churchsite.app` uses Cloudflare nameservers and identify a separate test domain controlled by the operator for the later custom-hostname proof.

Use the existing repository and its lockfiles. Do not scaffold another Laravel application over this project. Plesk's [Laravel Toolkit](https://docs.plesk.com/en-US/obsidian/administrator-guide/website-management/laravel-toolkit.80010/) can register an existing application and run Composer, Node and Artisan commands. Its document root must point to the application's `public/` directory, not the repository root.

Requirements observed locally:

- PHP 8.3 or newer, subject to the locked packages' platform requirements. Ensure Plesk's website PHP and command-line PHP use a compatible version. Run `composer check-platform-reqs --no-dev` on the target.
- Composer and the existing `composer.lock`.
- Node compatible with both Vite and Vite Plus; Node 22.18 or later in the 22.x series satisfies their installed engine constraints. Use `npm ci` with `package-lock.json` for the build.
- A dedicated application database/user. The project targets MySQL; do not assume the server's installed database version or copy the development SQLite database.
- Writable `storage/` and `bootstrap/cache/` for the application user. Avoid world-writable permissions.

## 2. Configure the application privately

This repository currently has no `.env.example`; `composer setup` expects that file and also performs migrations and key generation. Do not use it as a production deployment command. Prepare the server `.env` explicitly, outside Git, using the actual configuration keys:

- `APP_NAME`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://churchsite.app`, and `APP_KEY`.
- `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `SESSION_SECURE_COOKIE=true`. Leave `SESSION_DOMAIN` unset for host-only cookies.
- Mail transport/from-address settings supported by `config/mail.php`, required for verification and password recovery.
- IONOS storage through `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT`, and the bucket's required `AWS_USE_PATH_STYLE_ENDPOINT`. Keep objects private; the application serves authorized images.
- Stripe configuration from `billing-operations.md` when running sandbox billing validation. Checkout remains disabled until Feature 7's complete domain entry exists.

Generate an application key only for a genuinely new environment; preserve an existing production key. Configure secrets on the server, not in chat or deployment logs.

## 3. Deploy the platform application first

The following is an ordered checklist, not an unattended deployment script. Resolve the PHP binary and application path for the actual Plesk host before running it.

1. Deploy a reviewed Git commit to the application directory. The repository contains tracked dependency/build artifacts; install dependencies and rebuild instead of trusting those generated files as current production output. Keep `public/hot` absent on the server.
2. Run `composer install --no-dev --prefer-dist --optimize-autoloader` and `composer check-platform-reqs --no-dev` using the selected PHP runtime.
3. Run `npm ci` and `npm run build`. The build invokes Artisan for Wayfinder, so PHP dependencies and the environment must already be available. Do not use a Vite development server for production.
4. Back up any existing database, inspect pending migrations and run `php artisan migrate --force` only on the intended application database. This initial deployment adds billing and deferred-deletion tables as well as earlier application schema.
5. Run `php artisan config:cache` and `php artisan view:cache`. Restart existing queue workers after a deployment if workers are configured.
6. Configure the scheduler under the application user to run `php artisan schedule:run` every minute, using the absolute PHP/application paths. Its hourly deletion finalizer permanently removes eligible accounts; use only disposable users for the initial test. See `billing-operations.md`.
7. Establish HTTPS to the platform origin. Configure Cloudflare's platform DNS record with the actual origin address and validate strict origin TLS before public cutover. Preserve existing mail/DNS records when changing DNS.
8. Verify `/up`, signup, email verification, login, site editing, IONOS upload, publication, public media and multi-page links. `/up` alone does not prove database, mail or storage readiness. Confirm public responses never expose `.env` or application source, and that HTTPS URLs/cookies work through the proxy.

No queue-dependent domain job is implemented yet. Confirm existing queued work before deciding whether a long-running worker is needed; do not add a worker system speculatively.

## 4. Prepare Cloudflare for SaaS

Follow [Cloudflare's setup guide](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/start/getting-started/) for the `churchsite.app` zone. Enable SaaS, select a fallback-origin hostname, and confirm its proxied DNS record points to the intended Plesk origin. Choose the customer CNAME target only after reviewing existing DNS names. The operator reports `origin.churchsite.app` active as the fallback. The instructed customer target is `customers.churchsite.app`; verify the saved DNS values during Worker rollout.

Before onboarding customers, prove one operator-owned test hostname end to end. Cloudflare checks hostname readiness and certificate readiness separately; both must be active. Its [connection details](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/reference/connection-details/) also state that the default fallback-origin request preserves the customer's Host header and uses that hostname for SNI. A working `churchsite.app` certificate alone does not prove the custom-hostname origin connection works.

Inspect the real Plesk virtual-host and TLS configuration before selecting the origin strategy. Do not turn a shared server's default virtual host into this app blindly or lower TLS verification to hide a mismatch. Plesk [domain aliases](https://docs.plesk.com/en-US/obsidian/administrator-guide/website-management/websites-and-domains/domains-and-dns/adding-domain-aliases.65286/) can help with a controlled single-host proof, but manual aliases do not establish an automated many-customer solution. Confirm that an alias does not redirect the test hostname to the platform hostname.

Record the test hostname, origin selection, incoming Host/SNI behavior, valid origin TLS, edge certificate status and observed HTTP results. The origin must reach the intended Laravel application without relying on a client-spoofable tenant header. Do not expose platform login/dashboard routes broadly on customer hosts as the final design; Feature 7 must explicitly separate public customer-host routing from platform routes.

## 5. Continue Feature 7

The local 7a implementation provides authenticated Worker ingress and host isolation, but no custom-hostname persistence or published customer-site routing. The controlled transport proof cannot demonstrate a paid church site served at a customer domain.

The completed controlled Worker rollout and proof are recorded in [Worker connection operations](worker-domain-proxy.md) for Feature 7a. Carry forward exact origin configuration, account/API capability, CNAME target and deployment observations into the later 7b spec. Feature 7b owns hostname ownership, billing handoff, provider reconciliation, DNS/status UI, published-site routing and paid-access enforcement. Keep checkout disabled until that complete customer flow exists.

Confirmed application root: `/var/www/vhosts/churchsite.app/httpdocs`, with document root `httpdocs/public`. Remaining operational details for customer rollout include database arrangement, exact PHP binary and repeatable deployment commands, and Cloudflare API access configuration. The controlled Worker proof does not establish these remaining details.
