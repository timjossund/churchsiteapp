# Fix: Accept live restricted Stripe keys

**Type:** Fix
**Status:** verified
**Branch:** fix/live-restricted-stripe-keys

## The problem

Production activation checks only accept `sk_live_`, blocking the operator's live restricted `rk_live_` credential even when its permissions are sufficient.

## The fix

Accept `sk_live_` or `rk_live_` in the production condition in both existing configuration gates. Preserve the production environment and explicit opt-in requirements. Keep the existing local/staging sandbox policy unchanged; neither `sk_test_` nor `rk_test_` may enable production. Key-prefix acceptance does not establish actual Stripe permissions. No provider calls, credentials, remote configuration, or billing records change.

The operator approved this exact correction in chat with “sounds good” after the proposed production-only prefix adjustment was explained.

## Build steps

- [x] Update both production key checks, extend the existing configuration matrix to cover restricted live success and disabled, missing-opt-in, incorrect-mode, and cross-environment denial, and update the two operations runbooks. **Done when:** focused tests and `composer ci:check` pass and all pre-existing mode boundaries remain enforced.

## Verify

Use fake keys only. Run configured independent review before completion. Actual restricted-key permissions, production signed webhook delivery, and live payment behavior remain operator deployment checks; the prefix change does not verify them.

## Implementation evidence

- Focused configuration matrix: 38 tests, 76 assertions passed.
- `composer ci:check`: passed, including frontend lint/format, TypeScript, Pint, PHPStan, production build, and 777 Pest tests with 5,865 assertions.
- `git diff --check`: passed.
- Independent review pending approval of a local checkpoint commit. No deployment or provider permission verification performed.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":1904,"specSha256":"2ea3473958068510877afc84d37f2744b57b4d7e0497ec7d7ccd7993491f07ad","branch":"refs/heads/fix/live-restricted-stripe-keys","head":"aa788ad0eb91dacdfb68b019dba4e0b7e78def3b","baseRef":"refs/heads/main","baseCommit":"097bd4954a0796fbae797072df077b0f85ccca8c","sourceTree":"7e73dba61b8900a4d484d95d66c36b43b73e8f37","absentOptional":[]} -->

## Independent review

# Independent Review

**Status:** passed
**Target commit:** aa788ad0eb91dacdfb68b019dba4e0b7e78def3b
**Base commit:** 097bd4954a0796fbae797072df077b0f85ccca8c
**Base ref:** main
**Spec hash:** 2ea3473958068510877afc84d37f2744b57b4d7e0497ec7d7ccd7993491f07ad
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-29T22:14:27.457344+00:00
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-29T22:16:27.637981+00:00
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Handoff

Review the active spec and complete `097bd4954a0796fbae797072df077b0f85ccca8c..aa788ad0eb91dacdfb68b019dba4e0b7e78def3b` delta in a fresh isolated subagent without the builder conversation. Run all Audit lenses from scratch. Check is not required by the regular manual gate. Do not edit product code, accept findings, or reuse existing findings as the review scope.

## Commands

- `git rev-parse HEAD`, `git merge-base main HEAD`, `git branch --show-current`, `git status --porcelain=v1`, and SHA-256 verification of the exact active spec bytes: passed; target, permitted local base, spec hash, non-default branch, and allowed dirty evidence matched the request before and after review.
- `git diff main...HEAD` and targeted source/test searches: complete six-file delta reviewed, with relevant callers and contracts followed.
- `php artisan test --compact tests/Feature/LocalDomainTestingConfigurationTest.php tests/Feature/SiteBillingFoundationTest.php tests/Feature/SiteBillingLifecycleTest.php tests/Feature/CustomHostnameTest.php tests/Feature/CustomHostnameReconciliationTest.php tests/Feature/CustomerDomainTransportTest.php tests/Feature/SiteDomainSummaryTest.php`: passed, 224 tests and 1,182 assertions.
- `composer lint:check`: unavailable in this sandbox; its parallel Pint process could not bind a localhost socket (EPERM).
- `vendor/bin/pint --test config/customer-domains.php config/site-billing.php tests/Feature/LocalDomainTestingConfigurationTest.php`: passed.
- `vendor/bin/pint --test`: passed for the repository using nonparallel execution.
- `composer types:check`: passed; PHPStan reported zero errors.
- `git diff --check main...HEAD`: passed.

## Evidence

- Reviewed the entire `097bd4954a0796fbae797072df077b0f85ccca8c..aa788ad0eb91dacdfb68b019dba4e0b7e78def3b` delta: both configuration files, the configuration matrix, both operations runbooks, and the active spec. Applied AGENTS.md and the project coding, testing, security-boundary, and proportionality standards.
- Both configuration gates add `rk_live_` only inside the existing production environment and strict boolean production opt-in conjunction. Existing sandbox restrictions remain intact. The 38-case configuration matrix asserts both gate values, uses fake key fixtures, restores environment state in a finally block, and covers disabled/missing/invalid opt-ins, test/live mismatch, and nonproduction environments. No skipped, focused, or placeholder tests were introduced.
- Followed gate consumers in SiteBillingController, CustomHostnameController, DomainProxyController, SiteDomainSummary, ReconcileCustomHostname, and routes/console.php; followed billing operations through StartSiteCheckout, ValidateSiteBillingPrice, OpenSiteBillingPortal, and CancelSiteRenewal, plus authentication/ownership middleware in routes/web.php. The prefix change adds no user-controlled credential path, network operation, query, persistence, or unbounded work.
- Confirmed the installed Cashier configuration reads the same STRIPE_SECRET environment variable. Billing/domain regression coverage uses mocked Stripe, Cloudflare, and DNS responses and the test database; it is not evidence of live restricted-key permissions.
- Request/findings files were excluded from the product delta. Generated build output, caches, dependencies, and unrelated source were excluded from broad review; the installed Cashier configuration was read only to verify the credential contract. Existing local build assets were preserved.
- The spec records a builder `composer ci:check` pass with 777 tests and 5,865 assertions. This reviewer independently ran the relevant PHP checks and 224 tests above; it did not claim a fresh full frontend/build/suite run. Check is not required by this request.

## Findings

- None added, updated, or closed. No new quality, security, performance, or test finding in the complete target delta.
- Existing F-02 and F-06 remain open P2 and unchanged in findings.md; their unrelated repairs were not part of this review. No P0/P1 finding is open or fixed. No repair order is needed for this change.

## Remaining risk

- `composer lint:check` could not run its parallel worker in this sandbox because localhost socket binding was denied. The full nonparallel `vendor/bin/pint --test` fallback passed.
- A matching restricted-live prefix establishes mode only. Actual Stripe permissions for customer, Checkout, subscription, invoice, price, and billing-portal operations were not verified. Production signed webhook delivery, live payment behavior, refreshed configuration caches, Worker deployment/routing, and provider setup remain operator deployment checks.
- No production credentials, external services, deployment, or browser walkthrough were used. Existing provider-failure handling was covered locally with mocks; this receipt does not establish production readiness or authorize activation.

## Completion verification

- Final `composer ci:check`: passed; 777 Pest tests and 5,865 assertions, plus frontend checks, TypeScript, Pint, PHPStan, and production build.
- Independent review passed at the original checkpoint; no new findings. Existing open P2 findings remain in the live ledger.
- No deployment or provider permission verification performed.

## How to try it

After deployment, retain the live Stripe key, use `APP_ENV=production`, `CUSTOMER_DOMAINS_PRODUCTION_ENABLED=true`, and `CUSTOMER_DOMAINS_LOCAL_TESTING=false`, then refresh configuration with `php artisan config:cache`. Confirm restricted-key permissions and signed webhook delivery before checking production checkout.
