# Feature: Per-site subscriptions

**From build-plan:** feature 6
**Build attempt:** 1
**Status:** verified
**Branch:** feature/per-site-subscriptions
**Archive:** blueprint/history/features/06-per-site-subscriptions.md

## Goal

Give each site its own Cashier/Stripe subscription: USD $15 per month or $150 per year, without a free trial. Payment begins when the owner starts connecting a custom domain, not after DNS or SSL finishes. Free editing and subdirectory publication remain available without a subscription.

## In scope

- Install and configure Laravel Cashier Stripe (`laravel/cashier`) for the existing Laravel 13 / Inertia 3 application. Use Site as the billable model and the owning User as the authenticated actor.
- One plan, two recurring intervals, one subscription per site. Provide owner-scoped billing access, payment status, renewal or end date, and subscription management using Stripe-hosted Checkout and Billing Portal, with a small site billing summary in the existing Vue settings page.
- Stripe-confirmed subscription synchronization, signed webhooks, and a single server-side paid-access decision for Feature 7 to consume.
- Change account deletion so a subscribed account cancels renewals and is deleted after its final paid subscription ends. This is necessary because current account deletion immediately cascades to all sites.
- Automated integration tests with mocked Stripe responses, plus a documented sandbox payment/webhook walkthrough. Real Stripe sandbox evidence must be distinguished from automated mocks.

## Out of scope

- Cloudflare onboarding, domain records, DNS instructions, hostname routing, certificates, and custom-domain serving. These belong to Feature 7.
- Charging for site creation, editing, or subdirectory publication; account-wide billing, extra tiers, trials, discounts, seats, metered charges, custom refunds, and custom tax calculations.
- A custom billing portal or new browser test runner. Do not add an interval-switching UI with an invented proration policy; changing an existing subscription's interval is deferred.
- Production activation, live charges, remote price/webhook creation, deployment, or changes to Stripe account settings without separate authorization.

## Build loop

Implement on the recorded branch with one review pause after each step (`stepReview: every`). Checkpoint commits are enabled but require explicit approval. `/complete` owns the final feature commit. Payments, migrations, and account deletion select the configured independent-review gate; obtain approval for its exact checkpoint before running the fresh reviewer. Audit, Check, and try guide otherwise remain manual.

## Build steps

- [x] **1. Establish site billing persistence and configuration.** Install a compatible Cashier release without replacing the existing Vue application. Inspect the installed package's migrations, customer-model support, webhook routes, and cancellation APIs before extending them. Adapt its customer/subscription schema to Site, register the custom customer model in AppServiceProvider, and configure the two server-owned Stripe price IDs. Do not activate new checkout yet. **Done when:** migrations preserve existing users/sites, one user can have two independently billable sites, configuration rejects a mismatched price/currency/interval before checkout, and focused persistence/configuration tests and PHP static analysis pass.
- [x] **2. Implement isolated billing lifecycle and webhook synchronization.** Resolve every billable through the authenticated owner's sites and authorize every app billing entry point before calling Cashier. Use provider checkout and verified webhook handling; derive paid eligibility from confirmed subscription/payment state. Preserve a canceled subscription's paid period. Cover duplicate requests, retries, invalid signatures, delayed events, and provider errors without duplicate subscriptions or false activation. **Done when:** focused tests show independent site subscriptions, denied cross-owner access, no activation from a return URL, idempotent webhook delivery, and correct paid/end-of-period behavior. Checkout remains unexposed to normal users until the deletion safeguard is in place.
- [x] **3. Implement end-of-subscription account deletion.** Keep current-password confirmation. Persist a deletion request before attempting cancellation, cancel renewal for every site subscription, and retain the account and sites until all paid periods end. Add a retryable scheduled finalizer and pending-deletion feedback. **Done when:** time-controlled tests prove no early deletion, cancellation of all renewals, deletion after the latest paid period, retry safety after a partial Stripe failure, denial of new subscriptions during deletion, and preservation of ordinary immediate deletion for accounts with no billable commitments.
- [x] **4. Add billing presentation and the domain-setup integration boundary.** In site settings show free, payment pending/action required, active, cancellation scheduled, ended, and temporarily unavailable states. Provide an owner-scoped management link for an existing subscription. Prepare the checkout entry for Feature 7's domain-connection start; do not add a standalone paid signup button disconnected from domain setup. Use existing UI components, route helpers, and unsaved-settings navigation guards. **Done when:** Inertia/route tests confirm site-specific safe props and reachable management, no new charge from viewing settings, clear pending/error states, and unchanged free publication. Verify frontend types and build. Document the exact checkout entry and the Feature 7 caller contract.
- [x] **5. Verify the complete lifecycle and prepare review.** Run focused billing/deletion/publication regressions, `npm run build`, and `composer ci:check`. Exercise sandbox checkout, cancellation and webhook synchronization when credentials and a running app are available, without starting a server automatically. Record any unperformed external evidence explicitly. **Done when:** all automated checks pass; every earlier criterion is evidenced; setup and sandbox walkthrough are recorded; and the configured independent review passes before final completion.

- [x] **6. Repair F-03 terminal checkout reuse.** Reconcile the saved completed checkout against its authoritative subscription before replacing its attempt. Allow a new subscription only after Stripe confirms the old one is terminal, retaining ownership, pending-payment, duplicate-checkout and deletion guards. **Done when:** regressions prove canceled-site replacement, retry reuse and refusal for unresolved/active/mismatched/provider-failed state; final checks pass and a new independent checkpoint is reviewed.

- [x] **7. Repair F-04 delayed historical subscription selection.** Use one site-scoped selector for summary and cancellation that prioritizes nonterminal default subscriptions and deterministically orders terminal history. **Done when:** signed delayed-event regressions show the current replacement remains displayed and cancelable, focused/full checks pass, and fresh review closes the finding.

## Files / areas

- `composer.json`, `composer.lock`, new package billing configuration and migrations, `app/Providers/AppServiceProvider.php`, and a small server-owned plan configuration.
- `app/Models/Site.php`, `app/Models/User.php`, site billing actions/controllers as needed, `routes/web.php`, and the package webhook integration in the existing bootstrap configuration.
- `app/Http/Controllers/SiteController.php`, `resources/js/pages/Sites/Settings.vue`, existing shared components and generated Wayfinder helpers.
- `app/Http/Controllers/Settings/ProfileController.php`, `app/Http/Requests/Settings/ProfileDeleteRequest.php`, `resources/js/components/DeleteUser.vue`, and the profile page for pending deletion status.
- A narrowly scoped account-deletion action/command and its schedule in `routes/console.php`.
- `tests/Feature/` billing and deletion tests, the existing `Settings/ProfileUpdateTest.php`, site/publication regressions, and factories as needed.

## Data / contracts

### Prices and site ownership

- Currency is USD. Monthly unit amount is 1500 cents with interval month/count 1; annual unit amount is 15000 cents with interval year/count 1. No free trial. Stripe recurring price IDs are configuration supplied by the operator, never arbitrary client input.
- The server validates the selected interval against these two configured prices. Missing or mismatched configuration disables checkout with an actionable error; it must never silently choose another amount or price.
- Use Cashier's schema and lifecycle as the billing source of truth, with Site's existing integer ID as customer ownership. Do not add a second subscription store. Stripe customer/subscription identifiers must have the package's uniqueness guarantees; verify and adapt the actual installed migrations before applying them.
- The owner may manage only their own site. Use `$request->user()->sites()` as the initial lookup, with explicit application authorization on every billable action. Do not select a site via shared session state, which could charge the wrong site across tabs. Use explicit site-scoped application routes (`POST /sites/{site}/billing/checkout`, `POST /sites/{site}/billing/portal`, and `POST /sites/{site}/billing/cancel`) behind auth and verified middleware. Do not accept a client-supplied Stripe customer ID or return URL. Cashier supplies billing operations, not application ownership authorization. Return URLs are generated server-side for the same site.
- New customers use the site name and owner's email for billing identity; private billing details and provider identifiers are not exposed in public site snapshots or public routes.

### Payment and domain handoff

- Feature 7 starts this site's checkout as part of initiating domain connection. Stripe checkout collects payment immediately; DNS/SSL readiness does not delay the charge. Do not charge on a GET, settings view, or merely entering a hostname.
- Feature 6 prepares and tests this billing boundary; Feature 7 wires the real customer domain-setup entry. Do not release a paid domain-connection offer before that complete flow exists. This avoids moving domain work into Feature 6.
- A successful browser redirect alone never proves payment. Reconcile using authenticated server-to-provider data and signed webhooks. A pending/failed/action-required first payment is not paid eligibility. Unknown or stale payment state must not grant domain access.
- Active paid subscriptions qualify; cancellation at period end retains eligibility until that paid period expires. Other non-paid states do not qualify. Keep free publishing and editing independent of this predicate, including after a subscription ends.
- Use the package's checkout, cancellation, webhook and retry behavior first. Verify duplicate submissions cannot create multiple subscriptions for one site and delayed events cannot restore stale access. Add only the necessary locking/idempotency or authoritative reconciliation if the installed package does not already supply it. Never treat a database rollback as undoing a Stripe request.
- Approved revision: Stripe Billing Portal provides only payment-method updates and invoice history. Disable cancellation, subscription updates, customer updates and its public login page; Stripe portal cancellation also exposes renewal/resumption, so period-end cancellation is instead an owner-scoped “Cancel renewal” action in site settings. No subscription resumption route is provided. Verify the effective named portal configuration before opening a session; never use an unrestricted default. Existing sessions must use this restricted configuration. Remote portal setup requires separate authorization; local tests use mocks.
- Webhooks require valid signatures and use provider identity to locate the site. Exempt only the package webhook from CSRF as required, not the entire billing area. Unknown customers/events must not modify another site. Do not log secrets, card data, or raw sensitive payloads.

### Account deletion

- Interpretation for this multi-site application: deleting the account cancels renewal on all site subscriptions and deletes the account after the latest paid period ends. Canceling a subscription alone does not delete the account or site.
- Add nullable UTC `deletion_requested_at` and `deletion_scheduled_for` timestamps to User, initially null. A request with incomplete cancellation is pending with no promised final deletion date. Derive the final date only after cancellation/paid-period reconciliation succeeds for every site.
- Record the request durably before external calls; retries resume already completed cancellations without duplicate effects. Do not mark the account deleted or log the user out as if deletion succeeded while Stripe state is unresolved. Report partial failure as pending/retryable, preserving all data and provider mappings.
- While deletion is pending, retain sign-in, editing, publication and paid access through their existing eligibility periods, and allow billing inspection. Block new checkout, subscription resumption and other renewal-enabling billing mutations server-side so the deletion request cannot create new charges. Do not implement a new undo-deletion flow in this feature.
- The scheduled finalizer runs at least hourly, handles repeated invocation safely, and rechecks authoritative subscription/cancellation state before deletion. If Stripe is unavailable or any subscription may renew, keep the account and retry; never infer cancellation solely from an expired local timestamp. Recheck the durable request under a database lock before local deletion and coordinate it with checkout/deletion mutations.
- Accounts with no current subscription or pending payment commitment retain the current immediate-deletion behavior. Resolve in-flight checkout/payment before deleting a billable account; do not orphan a customer that can still incur a charge.
- Final deletion uses the existing user/site cascade. Do not broaden this work into object-storage cleanup, Stripe customer erasure, or unrelated account-data retention changes. Document the required production scheduler; tests invoke the command directly and use a controlled clock.

### UI and failures

- The backend returns only the selected site's display status, allowed management action, interval, amount/currency, and applicable UTC renewal/end timestamp. The client displays these values; it never computes entitlement or authorizes billing actions.
- Empty state explains that subdirectory publication is free and a subscription begins with domain connection. Show payment pending, declined/action-required, cancellation scheduled and unavailable states distinctly. Disable repeated submissions while processing and provide a safe retry after known provider failures.
- Preserve light/dark styling, keyboard operation, labels, associated field errors and announced status messages. Focus the relevant error and clear stale errors on retry. Render site/provider text through escaped Vue bindings. Unexpected exceptions remain visible through the normal error/reporting path without exposing provider secrets.
- Update account-deletion confirmation to explain renewal cancellation, continued access until the displayed date, and final irreversible deletion; do not retain copy implying subscribed accounts disappear immediately.

## Testing

- Baseline `composer ci:check` passed during planning: frontend formatting/lint and types, Pint, PHPStan, and 232 Pest tests with 2,359 assertions. This is existing-app evidence, not billing verification.
- Test owner/non-owner/guest/unverified access, two sites with different intervals, malicious price/site input, zero trials, pending payment, confirmed payment, failed payment and canceled/expired subscriptions.
- Test valid/invalid and repeated webhooks, delayed/out-of-order delivery, duplicate checkout, checkout versus deletion races, and provider outage/partial cancellation failure.
- Test deletion with zero, one and multiple subscriptions and differing paid-through dates, repeat requests/finalizer runs, elapsed dates with unresolved provider state, and refusal to create/resume subscriptions while deletion is pending.
- Confirm unpaid/canceled sites can still edit and publish subdirectory pages and public snapshots contain no billing data. Run the existing profile and publishing regressions.
- Use the existing Pest suite and mock provider boundary for deterministic tests; use a frozen clock for expiry. No new browser harness. Run `npm run build` followed by `composer ci:check` for the final automated gate.
- Record a sandbox walkthrough for both billing intervals, signed webhook delivery, cancellation and deferred deletion. If remote credentials or a running app are unavailable, say so; never represent mocks as a live Stripe test.

## Notes for the AI

- Preserve existing generated-file working changes and the saved stash. Do not include unrelated generated output in the feature commit.
- The user replaced Spark with Laravel Cashier because no active Spark license is available. Cashier requires no Spark Composer credentials. Stripe sandbox configuration still needs verification; request setup through local secure configuration, not secrets pasted into chat. Never create live prices automatically.
- The approved price/trial/deletion decisions above supersede older planning TODOs for this work. No build-plan edits were made during this spec command.
- Follow official Cashier documentation and the installed package rather than guessing APIs: https://laravel.com/framework/docs/13.x/billing. Use Stripe-hosted Checkout and Billing Portal rather than rebuilding payment forms or invoice management.
- Keep package installation separate from remote activation. Stop on an incompatible dependency or materially different package money behavior instead of adding an unreviewed replacement or inventing policy.
- This spec is for review only. Do not implement until approved.

## Implementation progress

- Steps 1 through 5 implementation and automated verification complete on `feature/per-site-subscriptions`. Independent review is selected and remains pending checkpoint approval; this is not final completion.
- Installed Cashier 16.8.0 with three required dependencies; no existing dependency versions changed. Ran package discovery without broad Composer update scripts.
- Site now uses Cashier's Billable trait and owner email. Cashier resolves customers and subscriptions through Site; billing attributes are hidden from model serialization.
- Added additive site billing migration, subscription and item tables, unique case-sensitive Stripe identifiers, and foreign keys. SQLite tests verify existing site/Home preservation and migration reversal. Local application/production migrations have not been run; no MySQL migration claim is made.
- Configured `STRIPE_SITE_MONTHLY_PRICE_ID` and `STRIPE_SITE_ANNUAL_PRICE_ID` as the only operator-supplied price choices. The validator fetches the configured Stripe price and rejects wrong identity, amount, currency, interval, trial, inactive, tiered, metered or transformed-quantity prices. No live Stripe calls were made; tests mock the SDK boundary.
- Cashier's default payment and webhook routes are deliberately disabled until Step 2 supplies application authorization and signed webhook handling. No checkout or billing UI is exposed.
- Focused command: `php artisan test --compact tests/Feature/SiteBillingFoundationTest.php` passed 19 tests / 62 assertions.
- Regression command: `composer test` passed Pint, PHPStan (zero errors), and all 251 Pest tests / 2,421 assertions. `git diff --check` passed for implementation files.
- Existing generated-file changes and saved stash remain untouched. No commits or remote billing changes.

### Step 2 evidence

- Added owner-scoped `POST /sites/{site}/billing/checkout` behind auth/verified middleware, with checkout disabled by default in configuration until the deletion safeguard and domain entry are integrated. Invalid interval is a validation error; arbitrary Stripe IDs and return URLs are not used.
- Added durable checkout attempt, start time, price and session fields on Site, hidden from serialization. User/site row locks serialize checkout work; the attempt commits before remote writes, and the customer mapping commits before session creation. Retries reuse the same Stripe idempotency keys/session. Uncertain attempts older than 23 hours stop for reconciliation rather than replaying after Stripe's retention guarantee. Confirmed expired sessions may start a new attempt; existing provider/local subscriptions block new checkout.
- Cashier's Checkout helper does not accept request idempotency options. The action therefore uses Cashier's configured Stripe client for hosted session creation, retaining Cashier customer and subscription models. No separate billing system was added.
- Added `POST /stripe/webhook` with mandatory secret/signature validation and an exact CSRF exception. Unknown customers/events are acknowledged without writes. Handled events retrieve current Stripe subscription/invoice state under the site lock before invoking Cashier synchronization, so repeated and delayed snapshots cannot restore stale access. Stripe read errors return 503 for retry.
- Added `subscriptions.paid_until` and `Site::hasPaidDomainAccess()`: only an active configured-price subscription with a paid invoice and matching invoice-line period qualifies. Period-end cancellation retains paid access until its end; browser return parameters never activate billing.
- Focused command: `php artisan test --compact tests/Feature/SiteBillingLifecycleTest.php tests/Feature/SiteBillingFoundationTest.php` passed 32 tests / 111 assertions. Coverage includes ownership/authentication, disabled checkout, persisted-session retry, lost-response idempotency and customer retention, expired idempotency retention, signature rejection, duplicate/out-of-order events, unpaid states, paid-period expiry, cross-customer mismatch, and Stripe outage.
- `composer test` passed Pint, PHPStan (zero errors), and all 264 Pest tests / 2,470 assertions. `git diff --check` passed for implementation files.
- Provider responses were mocked; no live Stripe calls, application-database migration, or actual concurrent MySQL execution was performed. Current locks are exercised sequentially under SQLite; later lifecycle review must retain this evidence limitation.
- Next: Step 3 adds deferred account deletion and coordinates its locks and pending checkout reconciliation. No payment UI or portal is exposed yet. No commits made.

### Step 3 evidence

- Added nullable UTC deletion request/scheduled timestamps on User. Password confirmation is unchanged; a request is durably recorded before any Stripe calls. Checkout's existing user-lock guard now checks the persisted deletion request.
- `DeleteAccountWhenBillingEnds` uses the same owner-then-site lock order as checkout, expires open sessions, cancels every site's renewals, retains confirmed paid periods, and refuses deletion while provider state or a payment remains unresolved. A failure on one site still permits cancellation attempts for the others. Partial failures leave no promised deletion date and retry safely. No subscription-resumption endpoint exists; Step 4 must also enforce the portal restriction.
- Added hourly `accounts:finalize-deletions` with overlap protection and production scheduler instructions in `docs/billing-operations.md`. The command is exercised only against isolated test data; no real account deletion or production schedule was run.
- Profile deletion keeps subscribed users signed in, redirects to profile settings, and shows either the scheduled date or reconciliation-pending copy. The confirmation explains period-end deletion and immediate deletion for accounts without billing commitments. Existing free-account deletion/password tests still pass.
- Focused deletion/profile tests passed: 12 tests / 72 assertions. Includes two different paid-through dates, retained paid access, repeated finalizer execution, partial failures with other-site cancellation, outage after a local date elapsed, open session expiration, unresolved payment retention, checkout denial, and no deletion without a request.
- `npm run check`, `npm run build`, and `composer ci:check` passed. Combined gate includes frontend format/lint/types, Pint, PHPStan (zero errors), and 271 Pest tests / 2,521 assertions. The existing optional Fontaine warning remains. `git diff --check` passed for implementation files.
- Tests use SQLite and mocked Stripe; no live visual/browser verification, live Stripe test, MySQL concurrency run, or application-database migration was performed. Checkout remains disabled until the complete domain entry exists. No commits made.

### Step 4 evidence

- Added the selected site's safe billing summary to site settings: free, pending, action required, active, cancellation scheduled, ended and unavailable. Viewing settings does not call Stripe or create a charge. Prices display USD $15/month or $150/year from server-owned configuration.
- Approved portal revision implemented: a named Stripe portal configuration must permit only invoices and payment methods, with subscription cancellation/update, customer updates and public login disabled. A separate owner-scoped Cancel renewal action confirms period-end cancellation and preserves paid access. Repeat cancellation is safe; pending-deletion owners may inspect billing, and no resumption endpoint exists.
- Settings use generated Wayfinder actions, submission disabling, announced/focused errors, and the existing unsaved-settings guard before leaving for Stripe. Cancellation refreshes only billing/errors, preserving unsaved settings.
- Expected setup/provider failures return safe retryable billing errors; unexpected backend exceptions remain on the normal error path. Price-validation setup errors now have a specific exception type.
- Documented portal setup and the exact Feature 7 checkout caller contract in `docs/billing-operations.md`. Checkout remains disabled, with no standalone paid signup button. No remote configuration changed.
- Focused `php artisan test --compact tests/Feature/SiteBillingManagementTest.php` passed 21 tests / 274 assertions, covering authentication/ownership, site-specific props, safe portal configuration, server-selected customer/return URL, failure feedback, repeated cancellation and retained access/data.
- `npm run build` passed (existing optional Fontaine warning). `composer ci:check` passed frontend format/lint/types, Pint, PHPStan with zero errors, and 292 tests / 2,795 assertions including existing publication/deletion regressions. The command required local worker socket permission. Initial static-analysis errors were corrected using Cashier's typed subscription accessor, then focused and full checks passed.
- `git diff --check` passed for implementation files. No live Stripe, visual/browser, MySQL concurrency, or application-database migration evidence is claimed. No commit, merge or push performed.

### Step 5 evidence and review handoff

- Compared the implementation with the ownership, price, payment, deletion, presentation and domain-handoff contracts. Billing configuration, actions, locking/idempotency fields, signed webhook handling and the finalizer each support an approved requirement. No extra dependency or browser harness was added for verification.
- Added explicit regressions proving unpaid/canceled sites and pending-deletion owners retain free editing/publication, with no billing identifiers in the public response or saved publication snapshot. Focused management/publication tests passed 48 tests / 676 assertions.
- Final verification exposed an existing random-name HTML-escaping assertion failure in `MultiPagePublishingTest.php`. Corrected the expected title to use HTML escaping and made that fixture contain an apostrophe, proving the observed failure deterministically. No publishing behavior changed.
- Final `npm run build` passed. After the test correction, `composer ci:check` passed frontend format/lint/types, Pint, PHPStan (zero errors), and 295 Pest tests / 2,816 assertions. `git diff --check` passed. No skipped/focused tests were added.
- `docs/billing-operations.md` now includes local secret names, event subscriptions, portal setup, scheduler requirements, the Feature 7 caller contract, and a sandbox walkthrough for both intervals, webhook signatures/replays, failures, cancellation and deferred deletion. Stripe test clocks and application time are explicitly distinguished.
- Local Stripe key/secret, webhook secret, price IDs and portal configuration are missing (presence checked without printing values). Live payments, portal, signed external deliveries and browser visual/keyboard behavior were not exercised. No application database migrations or MySQL concurrency test was run. Automated tests use isolated SQLite and mocked Stripe, with controlled time. Checkout remains disabled pending Feature 7.
- Preserved unrelated tracked generated output in named stash `Preserve generated output before feature 6 review`; the earlier formatter-fix stash remains intact. New billing route/action helpers and related Composer installation metadata remain in the checkpoint candidate. No commits or remote actions performed.
- Current ledger has only the pre-existing open P2 contrast finding F-02; no P0/P1 findings. Independent review is required for payments/deletion and pending explicit approval of the immutable candidate. The Step 5 automated work is checked; final completion still requires a passing fresh-reviewer receipt. Audit, runtime Check and try guide are manual under the current configuration and were not separately invoked.

### F-03 repair verification

- Independent Codex / gpt-6-astra review of `f97ad29df9df9415f0d4d669c1263e59fd0cd878` requested changes for F-03: a persisted completed checkout prevented replacement subscriptions after cancellation. The receipt remains historical evidence, not approval of this repair.
- Reconciled the completed session's customer and subscription using current Stripe state before rotating the attempt. Only matching canceled or incomplete-expired subscriptions permit replacement. Existing local/remote commitment checks, durable attempt/idempotency, owner locks and deletion guards are unchanged.
- Added nine regression cases covering replacement, retry reuse, unresolved/active/incomplete/past-due state, cross-customer mismatches and provider failures. Focused lifecycle tests passed 22 tests / 105 assertions.
- Final `composer ci:check` passed all frontend checks, Pint, PHPStan and 304 tests / 2,872 assertions. The previous passing build still applies because this repair changes only PHP checkout logic and its tests. F-03 is fixed, awaiting closure by fresh independent review of a new approved checkpoint. No live Stripe/browser/MySQL evidence was added.

### F-04 repair verification

- Fresh independent review of `0c24099611d440093b9a65715ef75e4d410cb539` closed F-03 and confirmed F-04: an older canceled subscription first received after its replacement could displace the active subscription in summary/cancellation because Cashier selected by local creation time.
- Added one site-scoped `billingSubscription()` selector shared by summary and cancellation. It prioritizes nonterminal default subscriptions, then orders ended history by end date and ID, independently of webhook arrival time. Ownership and provider-customer checks remain unchanged.
- Added a signed historical-webhook regression proving that the active replacement remains displayed, cancellation updates its Stripe ID, and repeat old events retain its cancellation-scheduled state. Added site/type isolation and deterministic terminal-history selection coverage.
- Focused lifecycle/management tests passed 48 tests / 416 assertions. Final `composer ci:check` passed frontend checks, Pint, PHPStan (zero errors), and 306 tests / 2,888 assertions. No frontend source changed; the previously passing build remains applicable. `git diff --check` passed.
- F-04 is fixed, awaiting a fresh review against a new approved checkpoint. F-03 is independently closed; F-02 remains the pre-existing open P2. Live Stripe, browser and MySQL concurrency evidence remain unavailable. No merge or push performed.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":32581,"specSha256":"6756d95844a6487a8bcb1e62b48227d2e8a3d96eb2ab4f05b90d2b0d56c6e52f","branch":"refs/heads/feature/per-site-subscriptions","head":"4b3db10326129a77ed6f5f65b04f1aa2a19f3750","baseRef":"refs/heads/main","baseCommit":"f910a9b82f3f102ea7a9135f8414f8f1ba713691","sourceTree":"43d6f15fc627fd3c6cac0e9e605b17cfa0684316","absentOptional":[]} -->

## Findings

### 6/F-03 [P1] closed - Reconcile completed checkout before starting a replacement subscription

**File:** app/Actions/StartSiteCheckout.php:59
**Found:** 2026-09-27 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** After a successful checkout, `checkout_session_id` remains set to its completed session. Once that subscription ends, the local guard correctly permits a new subscription, but lines 59-62 reject the old completed session before querying current Stripe subscriptions. Only an `expired` session clears the reservation, and neither webhook synchronization nor cancellation clears a completed one. A site therefore cannot buy another subscription after its first one ends; the same trap applies after a completed checkout's subscription reaches a terminal failed state. Retrying or delivering more webhooks cannot resolve the retained session. An isolated SQLite reproduction at f97ad29df9df9415f0d4d669c1263e59fd0cd878 used a canceled local subscription, an old completed session, and a mocked terminal Stripe subscription: `StartSiteCheckout::handle()` returned the billing validation error, made only price/session GET requests, never queried subscriptions, and retained the completed session ID. The existing tests cover open-session retries but omit this terminal lifecycle.
**Suggested fix:** Under the existing owner/site locks, reconcile a completed session's subscription and current customer commitments. When provider state confirms the previous attempt is terminal and no charge or subscription remains pending, rotate the checkout reservation and create a new session with a new idempotency key. Retain the current refusal for active or uncertain commitments and pending account deletion. Add regressions for an ended paid subscription, a terminal failed subscription, and an unresolved completed session. This starts a new subscription after the old one ends; it does not add subscription resumption.
**Resolution:**

Repair evidence for F-03: `StartSiteCheckout` now verifies the completed session belongs to this site, retrieves its subscription, and rotates the checkout attempt only for a matching canceled or incomplete-expired subscription. The existing remote commitment and account-deletion guards remain in force. Focused lifecycle tests pass (22 tests / 105 assertions), including replacement and repeat reuse, active/incomplete/past-due refusal, unresolved identity, cross-customer mismatch and provider outage. Awaiting a new independent review; not closed.

Independent closure of F-03 on 2026-09-27 at `0c24099611d440093b9a65715ef75e4d410cb539`: reviewed the complete checkout action and its callers from scratch. Completed sessions now rotate only after matching customer/subscription identity and authoritative canceled or incomplete-expired status, with local/remote commitment checks and durable retry identity retained. The full passing suite includes both terminal replacement cases, repeat-session reuse, and all unresolved/mismatched/outage refusal cases. The original retained-session trap is removed; F-03 is closed. The separate subscription-selection defect below has its own finding.

### 6/F-04 [P1] closed - Select the current commitment when canceling a replacement subscription

**File:** app/Actions/CancelSiteRenewal.php:18
**Found:** 2026-09-27 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** Both this action and `SiteBillingSummary.php:13` use Cashier's `subscription()`, which selects by descending local `created_at`. The webhook's call to `handleCustomerSubscriptionUpdated` at `SiteBillingWebhookController.php:78` creates a missing historical subscription with the delivery time as its local creation time. If a site's older subscription first synchronizes after its replacement active subscription, the old canceled row becomes the selected subscription. Settings then claim the subscription ended and hide Cancel renewal. Even a direct authorized cancellation POST retrieves the old canceled subscription and returns without canceling the current renewal. This is reachable when old subscription events are delayed until after a terminal subscription has been replaced; authoritative reconciliation of the old event does not fix selection across subscriptions. An isolated in-memory SQLite reproduction at `0c24099611d440093b9a65715ef75e4d410cb539` delivered a valid signed old canceled event after a current active subscription, then ran summary and cancellation: webhook 200, selected `sub_old`, status `ended`, `can_cancel=false`, paid access still true, and only GET requests for `sub_old`; the current subscription stayed active. Existing out-of-order tests cover changes to one subscription, not delivery ordering between old and replacement subscriptions.
**Suggested fix:** Make the summary and cancellation action consistently select the site's current nonterminal default subscription, falling back to terminal history only when no current commitment exists; use a deterministic history order. Keep ownership and provider-customer verification. Add a regression delivering an older canceled subscription after the replacement and verify settings show the replacement and cancellation updates its Stripe ID. Do not infer current subscription identity from webhook arrival time.
**Resolution:**

Repair evidence for F-04: summary and cancellation now share the site-scoped `billingSubscription()` selector, prioritizing nonterminal default subscriptions and ordering terminal history by end date and ID. Signed old-event and site/type/history regressions pass; final combined checks pass 306 tests / 2,888 assertions with zero static-analysis errors. Fixed, awaiting independent closure.

Independent closure of F-04 on 2026-09-27 at `4b3db10326129a77ed6f5f65b04f1aa2a19f3750`: reviewed the entire feature delta across all four lenses, including Site, summary, cancellation, webhook synchronization, checkout replacement and their callers. Both consumers now use the same site-scoped default-subscription selector, which prioritizes nonterminal commitments independently of delivery time and orders terminal history deterministically. The signed historical-event regression proves the replacement stays visible and its Stripe subscription is canceled, including repeated historical delivery after cancellation. The site/type/history regression covers selector isolation. `composer ci:check` passed 306 tests / 2,888 assertions, frontend checks, Pint and PHPStan with zero errors. The original defect is removed and no new defect was found in its repair; F-04 is closed. F-03 remains closed.

## Independent review

# Independent Review

**Status:** passed
**Target commit:** 4b3db10326129a77ed6f5f65b04f1aa2a19f3750
**Base commit:** f910a9b82f3f102ea7a9135f8414f8f1ba713691
**Base ref:** main
**Spec hash:** 6756d95844a6487a8bcb1e62b48227d2e8a3d96eb2ab4f05b90d2b0d56c6e52f
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-27T12:00:26.150158+00:00
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-27T12:03:26.948792+00:00
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Handoff

Review the active spec and the complete `f910a9b82f3f102ea7a9135f8414f8f1ba713691..4b3db10326129a77ed6f5f65b04f1aa2a19f3750` delta in an isolated subagent without the builder conversation. Run all Audit lenses from scratch. Do not edit product code, accept findings, or reuse the existing findings as the review scope. Follow the project-local Audit skill and its independent-review contract. Runtime Check is not required by configuration.

## Commands

- `git status --short`, `git status --porcelain=v1 --untracked-files=all`, `git rev-parse HEAD`, `git merge-base main HEAD`, `git ls-files --stage -- blueprint/context/current-feature.md`, and SHA-256 checks of the worktree and committed spec: passed. Target, main merge base, tracked spec and clean checkpoint matched the request.
- `git diff main...HEAD` with bounded path reads and call-site searches: passed; reviewed the complete project-owned feature delta and relevant callers across all four lenses.
- `composer ci:check`: passed after rerunning with local worker socket permission. Frontend format/lint and TypeScript, Pint, PHPStan (zero errors), and Pest (306 tests / 2,888 assertions) passed. The initial sandbox attempt was unavailable at Pint's loopback worker socket (EPERM); the permitted retry completed the exact command.
- `git diff --check main...HEAD` and `git diff --check`: passed.
- Targeted searches for skipped, focused and placeholder billing/deletion tests: none found.

## Evidence

- Fresh isolated Codex reviewer with no builder transcript; selected runtime model gpt-6-astra. Reviewed `f910a9b82f3f102ea7a9135f8414f8f1ba713691..4b3db10326129a77ed6f5f65b04f1aa2a19f3750` against the exact verified active spec. Request identity and snapshot conditions were checked; the spec is tracked, so no local snapshot applies.
- Reviewed all new billing/deletion actions and exceptions; Site/User models; billing, webhook, profile and site controllers; provider/bootstrap integration; routes and schedule; billing configuration and all three migrations; Composer manifest/lock changes; Vue billing/deletion/profile/settings integration; all new billing/deletion tests and the changed publishing assertion; operations documentation and plan/spec changes.
- Checked route helper URLs and signatures against the actual routes and consumers. Generated Wayfinder boilerplate and Composer autoload/installed metadata were excluded from hand-authored code review. Third-party package internals were excluded as review subjects; relevant installed Cashier and Stripe contracts were inspected to verify integration. No dependency vulnerability scan is claimed.
- Applied repository requirements for owner-scoped authorization, validation, safe error handling, price/currency/interval checks, signed authoritative webhook reconciliation, hidden billing details, shared lock order, retry identity, retained paid periods, pending deletion and restricted portal access. Settings reads make no Stripe call; free editing/publication remain independent of payment.
- Reviewed F-03 from scratch with the complete checkout path: matching terminal completed sessions permit replacement while unresolved commitments and pending deletion remain blocked. It remains closed.
- Closed F-04 after reviewing `Site::billingSubscription()`, both consumers, webhook synchronization and the signed historical-event/site/type/history regressions. The latest full suite independently passed these tests and the remaining lifecycle, ownership, failure, deletion and publication cases.
- Final freshness check found no product, test, config, spec or other Git differences. Only this receipt and the findings ledger were changed by the reviewer.

## Findings

- No new findings across quality, security, performance or tests.
- F-04 [P1]: closed this pass. F-03 [P1]: remains closed after fresh re-examination.
- F-02 [P2]: pre-existing open muted-text contrast finding remains unchanged and outside this feature's repair scope. No open or fixed P0/P1 remains.

## Remaining risk

- Live Stripe sandbox checkout, external signed webhook delivery and the restricted portal were not exercised. Existing evidence uses mocked Stripe HTTP responses and isolated SQLite; the operations document records absent local Stripe configuration and the remaining sandbox walkthrough. Checkout remains disabled pending Feature 7.
- No browser visual, keyboard or unsaved-navigation runtime walkthrough was performed. No MySQL migration/concurrent-locking execution was performed. These remain required before production activation.
- `npm run build` was not rerun because generation can dirty the immutable checkpoint. The active spec records passing build evidence for the unchanged frontend; this reviewer independently ran frontend lint, formatting and TypeScript checks through `composer ci:check`.
- The first `composer ci:check` attempt was unavailable under sandbox loopback restrictions; its permitted retry passed in full, so no local automated check remains unavailable. Runtime Check was not required by the prepared request.
