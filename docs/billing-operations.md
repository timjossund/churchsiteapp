# Billing operations

Checkout and customer-domain onboarding default to disabled. Local/staging sandbox testing and production activation use separate explicit switches. Adding Stripe keys alone does not enable checkout; the entry, payment, and deletion safeguards remain enforced.

## Production activation

After the reviewed application change is deployed and production readiness is approved, both application gates require:

```dotenv
APP_ENV=production
CUSTOMER_DOMAINS_PRODUCTION_ENABLED=true
CUSTOMER_DOMAINS_LOCAL_TESTING=false
```

Production also requires `STRIPE_SECRET` starting with `sk_live_` or `rk_live_`. A restricted live key must have the permissions required for the application's customer, Checkout, subscription, invoice, price, and billing-portal API operations. Verify those permissions in Stripe; a matching prefix alone does not prove access. The production switch defaults false and cannot enable local/staging live payments or production test payments. Key prefixes identify the intended mode; they do not verify credentials or account configuration.

Before enabling the switch, verify the live publishable/secret keys, live monthly and annual price IDs, the restricted live portal configuration, and the production webhook's own signing secret and signed delivery. Complete the outstanding sandbox expiry, resubscription, and provider-failure checks. Confirm the domain-provider configuration, scheduler, current Worker source, matching production ingress secret, and intended customer routes in [Worker operations](worker-domain-proxy.md). Keep the Worker's customer flag false while preparing the deployment.

Run `php artisan config:cache` with the production application's PHP binary after changing its environment. Check the effective `customer-domains.enabled` and `site-billing.checkout_enabled` booleans without printing credentials. The Worker's separate string variable `CUSTOMER_DOMAINS_ENABLED` must then be enabled with the reviewed routes before customer content can be served. This local change does not establish production acceptance or perform activation.

To close onboarding and new checkout, set `CUSTOMER_DOMAINS_PRODUCTION_ENABLED=false` and refresh the configuration cache. This also closes customer content serving and provisioning; coordinate Worker routing rollback. Existing platform billing management, period-end cancellation, subscription records, and cleanup remain available. Closing the switch does not cancel or refund subscriptions or delete Cloudflare hostnames.

## Deferred account deletion

Production must invoke Laravel's scheduler every minute (`php artisan schedule:run`) through the hosting scheduler. The application schedules `accounts:finalize-deletions` hourly with overlap protection. Use the configured persistent cache store for the scheduler lock.

The command reconciles Stripe for every pending account deletion. It expires open checkout sessions, cancels subscription renewals, and retains the account until subscriptions have ended and confirmed paid time has elapsed. The user remains signed in while deletion is pending. On a provider outage or unresolved payment, no deletion date is promised and later runs retry.

After paid time ends, the finalizer also revokes domain routing and waits for confirmed remote-hostname cleanup. Provider uncertainty retains the account and operation IDs for retry or support review.

Do not run the finalizer casually against real data: it permanently deletes eligible accounts and their sites using the existing database cascade. Automated tests use isolated databases, controlled clocks and mocked Stripe responses.

A checkout/customer creation with a lost response older than Stripe's guaranteed idempotency window requires operator reconciliation. The application retains the account rather than guessing or issuing a potentially new charge. Establish the matching Stripe customer/session and whether any payment remains pending before repairing local state; do not clear deletion requests or retry tokens merely to bypass this guard.

Stripe secrets and price IDs belong in local/deployment secret configuration, never in this document or source control. No production scheduler, prices, webhooks, or portal settings have been created by this implementation.

## Site management and Feature 7 handoff

The site’s Go Live page displays local webhook-confirmed billing status without calling Stripe or charging. A free site stays free when viewed, edited, or published. Existing subscriptions expose two owner-scoped POST actions behind authentication and verified email:

- `sites.billing.portal` (`/sites/{site}/billing/portal`) opens invoices and payment methods for that site's customer, with a server-owned return URL.
- `sites.billing.cancel` (`/sites/{site}/billing/cancel`) schedules cancellation at period end and preserves confirmed paid access. Repeating the request confirms the same cancellation. This does not delete the site/account. There is no subscription-resumption endpoint.

Configure `STRIPE_SITE_PORTAL_CONFIGURATION_ID` to a dedicated Stripe portal configuration (`bpc_...`). It must be active, with payment-method updates and invoice history enabled. Disable subscription cancellation, subscription updates, customer updates and the public portal login page. The app retrieves and checks this configuration before issuing each session and fails safely if it is missing or unsafe. Do not enable cancellation later on this configuration: Stripe's cancellation portal also allows renewal, which conflicts with pending account deletion. Keep previously issued sessions restricted too. The application handles cancellation instead. Pending-deletion owners may still inspect invoices and payment methods.

Feature 7 must explicitly enable `site-billing.checkout_enabled` only after its complete domain-connection entry exists. The Go Live domain panel submits `POST /sites/{site}/domain` with a full `www` hostname and `interval: monthly|annual`, reserves validated intent, then starts checkout. The existing `POST /sites/{site}/billing/checkout` endpoint also requires that saved valid intent and cannot bypass domain entry. Use the exact owned site ID and the existing unsaved-editor guard; disable duplicate submissions and show the returned `billing` validation error. The server selects `STRIPE_SITE_MONTHLY_PRICE_ID` or `STRIPE_SITE_ANNUAL_PRICE_ID`, validates USD 1500/month or USD 15000/year with no trial, and generates all customer and return details. Never submit Stripe IDs or a return URL.

Checkout redirects to Stripe through Inertia's external-location response. Returning with a query parameter does not activate access; show the server billing summary while signed webhooks reconcile payment. Feature 7 must use `Site::hasPaidDomainAccess()` for custom-domain serving and preserve free shareable publication independently. Do not delay the initial payment until DNS/SSL is ready.

## Stripe sandbox walkthrough (not yet performed)

Use a disposable application database and Stripe sandbox. Configure `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, the two approved recurring price IDs, and the restricted portal configuration locally. Never use live keys for this walkthrough. Install from `composer.lock`, run `php artisan migrate` against that disposable database, and have the operator start the app. Set the webhook endpoint to `/stripe/webhook` and use the Stripe API version selected by the installed Cashier client. Configure these implemented events:

- `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`, `customer.deleted`
- `invoice.paid`, `invoice.payment_succeeded`, `invoice.payment_failed`, `invoice.payment_action_required`
- `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`

For local delivery, an operator can use Stripe CLI forwarding to the running app's `/stripe/webhook`; use that listener's signing secret. Do not disable signature checks. Record delivery status and event IDs, without copying secrets or sensitive payloads into the evidence.

1. Create a verified test owner with two disposable sites. Open settings and publish both: neither action should create a Stripe customer, subscription or charge. With unsaved settings, attempting to leave for billing must offer the existing discard confirmation.
2. In the separate disposable environment, set `CUSTOMER_DOMAINS_LOCAL_TESTING=true` with `APP_ENV=local` and the sandbox `sk_test_` secret. This enables `customer-domains.enabled` and `site-billing.checkout_enabled` only for local sandbox testing; production remains closed. Use the Custom domain panel to enter separate operator-owned `www` hostnames for sites A and B and select monthly and annual respectively. Keep production gates closed. Provider operations require the separately approved SaaS setup in the Worker runbook. Confirm the hosted page shows USD $15/month or $150/year, no trial, and payment due now.
3. Use Stripe's [documented sandbox card](https://docs.stripe.com/testing), such as `4242 4242 4242 4242` with a future expiry and test CVC. Return before webhook delivery: the app must remain pending. After signed delivery and refresh, each site should show its own active interval, price and paid-through date. Record the two distinct customer/subscription identities privately. Repeat a checkout request to verify it cannot create another active subscription.
4. Repeat on another disposable site using a decline or authentication-required scenario from Stripe's testing documentation. It must not grant paid access until payment is confirmed. Replay a previously delivered signed event through Stripe's resend facility: subscription state must remain consistent with current provider state. A request with an invalid signature must receive 400 without changing billing.
5. Open Payment methods and invoices for each site and verify the correct customer, server-generated return link, and absence of subscription cancellation, renewal or plan-switching controls. Missing/unsafe portal configuration must show an error on Go Live. Retry after fixing only the sandbox configuration.
6. Select Cancel renewal for site A. Confirm Stripe records period-end cancellation, the app shows the end date, and paid access remains true before that date. Repeat the action: no immediate cancellation or extra charge should occur. Site B remains unchanged. Both sites remain editable and publicly shareable.
7. For an owner with two paid subscriptions, request account deletion with the current password. Both renewals must cancel; the account remains signed in and displays the latest end date. Checkout remains denied. Simulate a provider outage: retain the account with a pending message, then retry reconciliation after recovery.
8. Verify final deletion only after provider subscriptions have ended and the application's clock has passed the last paid period. Stripe [test clocks](https://docs.stripe.com/api/test_clocks) advance Stripe state, not the application's clock; do not change a shared machine's time or force a real account's paid date to bypass this safeguard. The time-controlled automated deletion tests provide this boundary evidence until an isolated synchronized clock scenario is available. Run `php artisan accounts:finalize-deletions` only against the disposable database; a second run must be harmless. Verify the deleted owner's sites are gone while other owners remain.
9. Restore both test setup gates to disabled and record what was actually exercised: runtime/database, test intervals, events, cancellation, deletion, browser/light/dark/keyboard checks, and any missing evidence. Do not label this document itself as acceptance evidence.

Current evidence: automated Pest tests use SQLite, frozen application time where needed, and mocked Stripe HTTP responses. Local Stripe configuration is absent, so no live Stripe payment/webhook/portal or browser walkthrough has been performed. Concurrent database locking has not been tested against MySQL. These checks remain required before production activation.

## Local sandbox evidence, 2026-09-27

The operator completed monthly Stripe test checkout for local site `test` (ID 16). Agent read-only API checks confirmed the matching checkout was complete/paid, its subscription active, and the latest USD invoice paid for 1500 cents. Initially the app had no subscription record: the operator's CLI OAuth context was a different account from the sandbox key configured in the app.

A local listener was then started with the app's sandbox key supplied through `STRIPE_API_KEY`, without printing the key or placing it in command arguments. Its signing secret was saved directly to the ignored local `.env` and the configuration cache cleared. This listener uses a separate temporary CLI configuration. The older OAuth authorization is not needed for this listener.

A diagnostic metadata marker on the existing sandbox subscription generated `customer.subscription.updated` event `evt_1UKN0ZLntMCHDF3KQnGA3jr7`. At 13:41:08 America/Chicago, direct listener output recorded HTTP 200 from the local webhook endpoint. A subsequent application database check confirmed active subscription status and `hasPaidDomainAccess() = true`, with paid time ending 2026-10-27 18:14:16 UTC. No additional payment, price change, cancellation, or billing-date change was made.

This proves a real signed subscription-update notification reached and reconciled the selected local site. The original checkout event was missed by the wrong-account listener and was not represented as replayed. Annual checkout, failure/cancellation/expiry scenarios, browser portal behavior, DNS/TLS, and deployed customer content remain unverified. The listener must remain running during further local tests; restarting a terminal listener without the app's sandbox key could select the older mismatched OAuth context again.

Checkout explicitly sets `managed_payments.enabled=false` so a new sandbox default cannot silently select Managed Payments or introduce its product-tax-code requirement. This preserves the existing standard subscription integration. A staging request failed on the Managed Payments tax-code requirement before this repair; verify checkout again after deployment.
