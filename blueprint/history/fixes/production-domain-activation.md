# Fix: Explicit production billing and customer-domain activation

**Type:** Fix
**Status:** verified
**Branch:** fix/production-domain-activation

## The problem

Production Stripe live keys are reportedly configured, but `config/customer-domains.php` and `config/site-billing.php` enable domain onboarding and checkout only in explicitly enabled local/staging sandboxes using test keys. Production stays closed regardless of its Stripe credentials.

## The fix

Keep the existing sandbox condition. Add a production condition to both gates: `APP_ENV=production`, explicit boolean `CUSTOMER_DOMAINS_PRODUCTION_ENABLED=true`, and a Stripe secret starting with `sk_live_`. The new switch defaults false. Do not allow live keys in staging or test keys in production. Retain paid-access, ownership, readiness, webhook validation, and cleanup protections.

This application switch is separate from the Worker's `CUSTOMER_DOMAINS_ENABLED` string variable. No credentials, server environment, Cloudflare settings, Stripe objects, database records, or deployed services are changed by this local work.

## Approved supporting repair

The operator approved formatter-only changes to `resources/js/pages/Welcome.vue` on 2026-09-29. Preserve their wording and styling; normalize void tags, class ordering, and source line wrapping to unblock the combined check.

## Build steps

- [x] Update both configuration gates, add focused configuration tests, and document activation and rollback in the billing and Worker operations guides. **Done when:** the production gates open only with explicit opt-in and a live-key prefix; sandbox behavior remains intact; disabled, missing-key, incorrect-key, unknown-environment, and cross-environment cases remain closed; `composer ci:check` and Worker tests pass.

Independent review is required for this billing-sensitive change before completion.

## Verify

- Use fake credentials in the configuration test matrix; never contact Stripe or Cloudflare during tests.
- Document the later operator deployment steps: deploy reviewed code, verify live prices/portal/webhook and outstanding readiness checks, set the production switch, refresh Laravel configuration cache, and coordinate customer Worker routing. Closing the switch must preserve platform billing management and existing subscription records.
- Live checkout, webhook delivery, domain readiness, and customer serving require separate deployment evidence. Existing deferred expiry, resubscription, and provider-failure checks remain production readiness prerequisites.

## Implementation evidence

Implemented on `fix/production-domain-activation`. Both configuration gates now support the approved explicit production opt-in, preserving the existing sandbox condition. Activation and rollback instructions are in the billing and Worker runbooks. No server environment or external settings were changed.

- Focused configuration matrix: 26 tests, 52 assertions passed.
- Worker tests: 119 passed.
- `npm run types:check`: passed.
- `composer test`: passed, including Pint, PHPStan, production build, and 765 Pest tests with 5,841 assertions. Pint required execution outside the sandbox for its local worker socket.
- `git diff --check`: passed.
- `composer ci:check`: passed after the operator-approved formatting-only repair in `resources/js/pages/Welcome.vue`, including frontend lint/format, TypeScript, Pint, PHPStan, production build, and 765 Pest tests with 5,841 assertions. The rerun required execution outside the sandbox for Pint's local worker socket.
- Independent review: required for billing activation, not yet requested; final gate passed; explicit checkpoint commit approval is still needed.

Local implementation and verification passed. Independent review remains pending until the approved checkpoint has a current passing receipt.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":3840,"specSha256":"b0ae01c8de4d59ef825d1b4308dc8815d26d147a284e5982c95b34832b99b4f1","branch":"refs/heads/fix/production-domain-activation","head":"05411ccbff4919ff4b7c68b3b77933b32d318554","baseRef":"refs/heads/main","baseCommit":"7e5f430349a6647ba0682082a68707dd6154f081","sourceTree":"e29eb838ae5090d886a47a84de7477170bb62261","absentOptional":[]} -->

## Independent review

# Independent Review

**Status:** passed
**Target commit:** 05411ccbff4919ff4b7c68b3b77933b32d318554
**Base commit:** 7e5f430349a6647ba0682082a68707dd6154f081
**Base ref:** main
**Spec hash:** b0ae01c8de4d59ef825d1b4308dc8815d26d147a284e5982c95b34832b99b4f1
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-29T21:44:16.779314+00:00
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-29T21:46:40.893674+00:00
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Handoff

Review the active spec and the complete `7e5f430349a6647ba0682082a68707dd6154f081..05411ccbff4919ff4b7c68b3b77933b32d318554` delta in a fresh isolated subagent without the builder conversation. Run all Audit lenses from scratch. Check is not required by the regular manual gate. Do not edit product code, accept findings, or reuse the existing findings as the review scope.

## Commands

- `git rev-parse HEAD`, `git merge-base main HEAD`, `git status --short`, `git ls-files --stage -- blueprint/context/current-feature.md`, and raw-byte SHA-256/target-spec comparison: passed before review and freshness revalidated before receipt. Only the permitted review evidence differs from the target.
- `git diff --check main...HEAD`: passed.
- `php artisan test --compact tests/Feature/LocalDomainTestingConfigurationTest.php tests/Feature/SiteBillingManagementTest.php tests/Feature/SiteBillingLifecycleTest.php tests/Feature/CustomHostnameTest.php tests/Feature/CustomHostnameReconciliationTest.php tests/Feature/CustomerDomainTransportTest.php tests/Feature/SiteDomainSummaryTest.php`: passed, 217 tests and 1,391 assertions.
- `node --test workers/domain-proxy/*.test.mjs`: passed, 119 tests; zero failures, skips, or todos.
- `vendor/bin/pint --test config/customer-domains.php config/site-billing.php tests/Feature/LocalDomainTestingConfigurationTest.php`: passed.
- `npm run check`: passed; 142 files correctly formatted and no warnings or lint errors in 78 files.

## Evidence

- Reviewed the complete seven-file delta from `7e5f430349a6647ba0682082a68707dd6154f081` to `05411ccbff4919ff4b7c68b3b77933b32d318554`: active spec, both configuration gates, both operations guides, Welcome.vue formatting, and the complete configuration test matrix.
- Quality: the two existing configuration expressions retain the repository pattern, add no dependencies or abstraction, and use identical environment/mode conditions. Welcome.vue changes normalize formatting while retaining content, classes, and rendered structure. Checked AGENTS.md, coding standards, approved spec, configuration, and interaction policy.
- Security: inspected billing/domain controllers and their authenticated, verified, owner-scoped routes; StartSiteCheckout, CancelSiteRenewal, OpenSiteBillingPortal, signed webhook handling, DomainProxyController, CustomHostname readiness, and SiteDomainSummary. Production requires the explicit boolean switch, exact production environment, and live-key prefix. Sandbox remains separately restricted. Paid access, saved domain intent, provider readiness, signature validation, and public route isolation remain enforced by existing callers.
- Rollback: billing portal/cancellation and webhook reconciliation do not depend on the new gate. Customer content and provisioning do. Site/account deletion callers retain access to removal reconciliation while disabled. No configuration expression mutates subscriptions or provider records.
- Performance: gate evaluation adds only constant-time environment checks with no provider/database calls. Relevant scheduled reconciliation remains bounded by record and time limits with existing overlap/lease protection; the change adds no loop or query.
- Tests: the 26-case configuration matrix loads both actual configuration files, covers production success, default/disabled/missing/nonboolean activation, missing/invalid/wrong-mode secrets, sandbox compatibility, and environment isolation, and restores process and superglobal environment values in finally. Existing targeted tests exercise authorization, checkout idempotency, signed billing reconciliation, paid-access expiry, provider failures, domain readiness, disabled serving, and tenant isolation using SQLite and provider fakes. No focused, skipped, or placeholder tests were found in the changed test file or selected feature test declarations.
- Scope exclusions: unrelated application areas, dependencies, generated Wayfinder/build output, caches, and the request/findings records were not treated as product delta. Existing assets were retained for rendering tests. This is an independent current-work review, not a full-project audit or live production acceptance.

## Findings

- No new findings. No open or fixed P0/P1 ledger entry exists.
- Existing open P2 entries F-02 and F-06 are unchanged; this pass does not close, accept, or claim to repair them. No ledger changes were needed.

## Remaining risk

- No production deployment, secret/configuration-cache change, live payment, portal session, signed production webhook delivery, Cloudflare provisioning, DNS/TLS, or deployed Worker/browser flow was performed. These remain separately authorized rollout evidence; this receipt approves the local checkpoint only.
- Tests use SQLite and mocked provider responses; production MySQL locking/concurrency and provider failure/expiry/resubscription scenarios remain subject to the runbook's readiness requirements.
- Browser automation is not configured. The Welcome.vue change was checked as formatting-only and linted, without a fresh browser visual comparison.
- The reviewer ran the targeted checks listed above; the full combined build/typecheck/Pest gate reported in the spec was not rerun. No attempted verification command was unavailable.

## Completion verification

- `composer ci:check`: passed on 2026-09-29, including frontend lint/format, TypeScript, Pint, PHPStan, production build, and 765 Pest tests with 5,841 assertions.
- `node --test workers/domain-proxy/*.test.mjs`: 119 passed.
- Independent current review passed for the original checkpoint above. Existing unresolved P2 findings remain in the live ledger.
- Production deployment, webhook delivery, payments, and Cloudflare routing remain unperformed.

## How to try it

After deployment and production readiness approval, configure `APP_ENV=production`, a live Stripe secret, and `CUSTOMER_DOMAINS_PRODUCTION_ENABLED=true`, then refresh Laravel configuration cache. Check both application gate booleans without exposing secrets. Follow `docs/billing-operations.md` and `docs/worker-domain-proxy.md` for the separate live billing and routing steps. Closing the switch denies onboarding and customer content while retaining platform billing management.
