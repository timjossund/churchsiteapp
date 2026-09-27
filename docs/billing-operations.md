# Billing operations

Billing is in development. Checkout is disabled in `config/site-billing.php` until the account-deletion safeguards and domain-connection entry are integrated. Do not enable live checkout for this intermediate feature step.

## Deferred account deletion

Production must invoke Laravel's scheduler every minute (`php artisan schedule:run`) through the hosting scheduler. The application schedules `accounts:finalize-deletions` hourly with overlap protection. Use the configured persistent cache store for the scheduler lock.

The command reconciles Stripe for every pending account deletion. It expires open checkout sessions, cancels subscription renewals, and retains the account until subscriptions have ended and confirmed paid time has elapsed. The user remains signed in while deletion is pending. On a provider outage or unresolved payment, no deletion date is promised and later runs retry.

Do not run the finalizer casually against real data: it permanently deletes eligible accounts and their sites using the existing database cascade. Automated tests use isolated databases, controlled clocks and mocked Stripe responses.

A checkout/customer creation with a lost response older than Stripe's guaranteed idempotency window requires operator reconciliation. The application retains the account rather than guessing or issuing a potentially new charge. Establish the matching Stripe customer/session and whether any payment remains pending before repairing local state; do not clear deletion requests or retry tokens merely to bypass this guard.

Stripe secrets and price IDs belong in local/deployment secret configuration, never in this document or source control. No production scheduler, prices, webhooks, or portal settings have been created by this implementation.

## Site management and Feature 7 handoff

Settings display local webhook-confirmed billing status without calling Stripe or charging. A free site stays free when viewed, edited, or published. Existing subscriptions expose two owner-scoped POST actions behind authentication and verified email:

- `sites.billing.portal` (`/sites/{site}/billing/portal`) opens invoices and payment methods for that site's customer, with a server-owned return URL.
- `sites.billing.cancel` (`/sites/{site}/billing/cancel`) schedules cancellation at period end and preserves confirmed paid access. Repeating the request confirms the same cancellation. This does not delete the site/account. There is no subscription-resumption endpoint.

Configure `STRIPE_SITE_PORTAL_CONFIGURATION_ID` to a dedicated Stripe portal configuration (`bpc_...`). It must be active, with payment-method updates and invoice history enabled. Disable subscription cancellation, subscription updates, customer updates and the public portal login page. The app retrieves and checks this configuration before issuing each session and fails safely if it is missing or unsafe. Do not enable cancellation later on this configuration: Stripe's cancellation portal also allows renewal, which conflicts with pending account deletion. Keep previously issued sessions restricted too. The application handles cancellation instead. Pending-deletion owners may still inspect invoices and payment methods.

Feature 7 must explicitly enable `site-billing.checkout_enabled` only after its complete domain-connection entry exists. At the start of connection, submit `POST /sites/{site}/billing/checkout` (Wayfinder `sites.billing.checkout`) with `interval: monthly|annual`. Use the exact owned site ID and the existing unsaved-editor guard; disable duplicate submissions and show the returned `billing` validation error. The server selects `STRIPE_SITE_MONTHLY_PRICE_ID` or `STRIPE_SITE_ANNUAL_PRICE_ID`, validates USD 1500/month or USD 15000/year with no trial, and generates all customer and return details. Never submit Stripe IDs or a return URL.

Checkout redirects to Stripe through Inertia's external-location response. Returning with a query parameter does not activate access; show the server billing summary while signed webhooks reconcile payment. Feature 7 must use `Site::hasPaidDomainAccess()` for custom-domain serving and preserve free shareable publication independently. Do not delay the initial payment until DNS/SSL is ready.

## Stripe sandbox walkthrough (not yet performed)

Use a disposable application database and Stripe sandbox. Configure `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, the two approved recurring price IDs, and the restricted portal configuration locally. Never use live keys for this walkthrough. Install from `composer.lock`, run `php artisan migrate` against that disposable database, and have the operator start the app. Set the webhook endpoint to `/stripe/webhook` and use the Stripe API version selected by the installed Cashier client. Configure these implemented events:

- `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`, `customer.deleted`
- `invoice.paid`, `invoice.payment_succeeded`, `invoice.payment_failed`, `invoice.payment_action_required`
- `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`

For local delivery, an operator can use Stripe CLI forwarding to the running app's `/stripe/webhook`; use that listener's signing secret. Do not disable signature checks. Record delivery status and event IDs, without copying secrets or sensitive payloads into the evidence.

1. Create a verified test owner with two disposable sites. Open settings and publish both: neither action should create a Stripe customer, subscription or charge. With unsaved settings, attempting to leave for billing must offer the existing discard confirmation.
2. Feature 6 has no domain-setup button. In a disposable test checkout only, temporarily enable `site-billing.checkout_enabled` and submit an authenticated, CSRF-protected POST to the documented checkout route for site A with `monthly`, then site B with `annual`. Do not commit that temporary change or enable customer-facing checkout before Feature 7. Confirm the hosted page shows USD $15/month or $150/year, no trial, and payment due now.
3. Use Stripe's [documented sandbox card](https://docs.stripe.com/testing), such as `4242 4242 4242 4242` with a future expiry and test CVC. Return before webhook delivery: the app must remain pending. After signed delivery and refresh, each site should show its own active interval, price and paid-through date. Record the two distinct customer/subscription identities privately. Repeat a checkout request to verify it cannot create another active subscription.
4. Repeat on another disposable site using a decline or authentication-required scenario from Stripe's testing documentation. It must not grant paid access until payment is confirmed. Replay a previously delivered signed event through Stripe's resend facility: subscription state must remain consistent with current provider state. A request with an invalid signature must receive 400 without changing billing.
5. Open Payment methods and invoices for each site and verify the correct customer, server-generated return link, and absence of subscription cancellation, renewal or plan-switching controls. Missing/unsafe portal configuration must show an error in settings. Retry after fixing only the sandbox configuration.
6. Select Cancel renewal for site A. Confirm Stripe records period-end cancellation, the app shows the end date, and paid access remains true before that date. Repeat the action: no immediate cancellation or extra charge should occur. Site B remains unchanged. Both sites remain editable and publicly shareable.
7. For an owner with two paid subscriptions, request account deletion with the current password. Both renewals must cancel; the account remains signed in and displays the latest end date. Checkout remains denied. Simulate a provider outage: retain the account with a pending message, then retry reconciliation after recovery.
8. Verify final deletion only after provider subscriptions have ended and the application's clock has passed the last paid period. Stripe [test clocks](https://docs.stripe.com/api/test_clocks) advance Stripe state, not the application's clock; do not change a shared machine's time or force a real account's paid date to bypass this safeguard. The time-controlled automated deletion tests provide this boundary evidence until an isolated synchronized clock scenario is available. Run `php artisan accounts:finalize-deletions` only against the disposable database; a second run must be harmless. Verify the deleted owner's sites are gone while other owners remain.
9. Restore checkout to disabled and record what was actually exercised: runtime/database, test intervals, events, cancellation, deletion, browser/light/dark/keyboard checks, and any missing evidence. Do not label this document itself as acceptance evidence.

Current evidence: automated Pest tests use SQLite, frozen application time where needed, and mocked Stripe HTTP responses. Local Stripe configuration is absent, so no live Stripe payment/webhook/portal or browser walkthrough has been performed. Concurrent database locking has not been tested against MySQL. These checks remain required before production activation.
