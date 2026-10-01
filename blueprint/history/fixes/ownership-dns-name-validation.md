# Fix: Validate ownership DNS name before domain checkout

**Type:** Fix
**Status:** verified
**Branch:** fix/ownership-dns-name-validation
**Fixes:** F-06

## The problem

`CustomerHostname` accepts a 253-character hostname, but ownership verification
adds `_churchsite.` to that hostname. The resulting 265-character TXT record
name exceeds the DNS name limit. The connection can be reserved and reach
checkout even though ownership verification can never succeed.

## The fix

- Validate the full generated ownership record name as well as the hostname
  in the existing `CustomerHostname` rule, before reservation and checkout.
  Reuse native DNS-name validation and the existing ownership naming behavior;
  add no alternate verification protocol or dependencies.
- Account for the 12-character `_churchsite.` prefix: the current ownership
  scheme supports hostnames up to 241 ASCII characters, with existing
  63-character label limits. Reject longer hostnames with a clear hostname
  validation message explaining that the name is too long for verification.
- Preserve trimming, lowercase normalization, www-only syntax, excluded-host
  checks, ownership controls, and normal domain connection and billing behavior.
- Replace the old 253-character acceptance regression with the supported
  boundary and cover rejection before any reservation, checkout attempt, or
  Stripe/provider call. Keep historical completed specs immutable; this fix
  explicitly supersedes Feature 7b's unrestricted 253-character acceptance.
- This fixes validation of new connection requests. It does not delete or
  automatically modify existing domain reservations, subscriptions, or data.

## Build steps

- [x] Update the existing hostname validation rule and focused domain/billing
      regressions for the full ownership DNS name.
      **Done when:** a valid 241-character www hostname produces a valid
      253-character ownership record name; 242-character and 253-character
      hostnames fail with a hostname error before reservation or checkout; normal
      domains still connect through the existing workflow.
      Mark F-06 fixed after passing checks, retaining it for review closure.

## Verify

- Run focused Pest domain and billing tests. Assert the generated DNS name is
  valid at the supported boundary, rejected submissions leave no hostname or
  checkout state, and no Stripe/provider call occurs on rejection.
- Run `composer ci:check` for all configured automated checks and the full Pest
  suite. No real checkout, charge, DNS change, or provider provisioning is used.
- In Go Live, submit an overlong www hostname and confirm the hostname error
  keeps the owner on the setup page without opening Stripe Checkout.
- Apply configured sensitive-work independent review because this change
  validates domain setup before payment. The reviewer must re-examine the full
  fix and close F-06 after confirming the repair. Checkpoint commit permission
  remains a separate approval after the verified candidate is shown.

## Implementation evidence

- `php artisan test --compact tests/Feature/CustomHostnameTest.php tests/Feature/SiteBillingLifecycleTest.php`: 84 tests passed with 425 assertions.
- `composer ci:check`: frontend lint/format and typecheck, Pint, PHPStan,
  production build, and all 980 Pest tests passed.
- The 241-character boundary produces a native-valid 253-character ownership
  record. Both 242- and 253-character hostnames fail before reservation,
  checkout state, Stripe customer/subscription creation, or any Stripe request.
- Existing overlong reservations fail the separate checkout endpoint's existing
  validation guard without changing the reservation.
- No new dependency, service, protocol, or persistence change. Normal-domain
  connection and checkout regressions pass in the focused suite.
- Browser interaction remains unverified. The HTTP regression confirms a
  hostname error and redirect back to Go Live without opening Stripe.
- F-06 is fixed pending independent review. Independent review is selected
  under the regular sensitive-work gate; an approved immutable checkpoint is
  required before the automatic reviewer can run. Other regular gates are manual.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":4214,"specSha256":"a557dba1233978138fe603a78cc4f6a4637d8215f320843d44fa90aeb41b9337","branch":"refs/heads/fix/ownership-dns-name-validation","head":"599c43c74c671e71f3a8e40b9d835512a22b6aee","baseRef":"refs/heads/main","baseCommit":"b75e91ee8870897415b5554e51b8840749e5082a","sourceTree":"3106e91cc8e2579a925f07a8bfedecf4cd5ab025","absentOptional":[]} -->

## Findings

### ownership-dns-name-validation/F-06 [P2] closed - Validate the ownership record length before checkout

**File:** app/Rules/CustomerHostname.php:12
**Found:** 2026-09-27 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The rule accepts full 253-character hostnames, but `CustomHostname::ownershipRecordName()` prepends `_churchsite.`, making the required ownership TXT name 265 characters. DNS cannot represent that name. Hosts longer than 241 characters can therefore be reserved and sent to paid checkout even though this connection can never satisfy its mandatory ownership check. An offline PHP reproduction using the exact 253-character boundary fixture confirmed `CustomerHostname` accepts it and native `FILTER_VALIDATE_DOMAIN` accepts the hostname but rejects its generated ownership name. The existing boundary test verifies reservation only, so it misses this integration constraint. This is a rare boundary input, not a general onboarding failure.
**Suggested fix:** Validate the generated ownership DNS name before reservation and checkout, using the existing validation rule/native domain validation, and show a hostname error when it cannot fit. Update the boundary regression to cover the generated record and rejection before payment. Reconcile the spec's stated 253-character acceptance with the required ownership prefix when making the repair; do not introduce an alternate ownership protocol solely for this edge case.
**Resolution:** Confirmed at `1bd9ea5c94d10e6d573435038f468a8b370da442`. Reproduction built `www.` plus labels of 63, 63, 63, and 57 characters; hostname length 253, application acceptance true, native hostname validation true, ownership length 265, native ownership validation false. No DNS request or provider call was made. Remains open P2.

Implementation repair: the existing hostname rule validates `CustomHostname::ownershipRecordName()` with native `FILTER_VALIDATE_DOMAIN` before reservation and checkout. Focused tests cover the accepted 241-character hostname/253-character ownership record and rejected 242/253-character hostnames. Rejected submissions leave no reservation, checkout state, Stripe customer, or subscription and make zero Stripe requests. Existing overlong reservations cannot start checkout and remain unchanged. Marked fixed pending independent re-review.

Independent re-review (2026-09-30, codex / gpt-6-astra, fresh subagent): closed F-06 against `599c43c74c671e71f3a8e40b9d835512a22b6aee` after reviewing the complete base-to-target product/test delta across quality, security, performance, and tests. The rule validates the actual generated ownership name before reservation and before every checkout operation. Offline wire-length verification confirms 241-character hostnames produce a 255-byte DNS wire name, while 242/253-character hostnames exceed that limit and are rejected. Focused domain/billing tests passed (84 tests, 425 assertions), including no reservation, checkout state, or Stripe request on rejection and unchanged retained overlong reservations. Public domain/transport regressions passed (79 tests, 577 assertions); PHP formatting and static analysis passed. The original defect is repaired, no new defect was found, and no finding was accepted. Browser interaction and live DNS/provider behavior were not exercised.

## Independent review

# Independent Review

**Status:** passed
**Target commit:** 599c43c74c671e71f3a8e40b9d835512a22b6aee
**Base commit:** b75e91ee8870897415b5554e51b8840749e5082a
**Base ref:** refs/heads/main
**Spec hash:** a557dba1233978138fe603a78cc4f6a4637d8215f320843d44fa90aeb41b9337
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-30T23:24:53.923324+00:00
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-30T23:27:46.100510+00:00
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Handoff

Review the active spec and the complete `b75e91ee8870897415b5554e51b8840749e5082a..599c43c74c671e71f3a8e40b9d835512a22b6aee` delta in a fresh
isolated subagent without the builder conversation. Run all Audit lenses from scratch.
Do not edit product code, accept findings, or reuse the existing findings as the
review scope. Re-examine F-06 after reviewing the full change and close it only
if the repair is confirmed. Check is not required by the configured gate.

## Commands

- `git status --short`, `git rev-parse HEAD`, `git merge-base refs/heads/main 599c43c74c671e71f3a8e40b9d835512a22b6aee`, and spec SHA-256 verification against the working file and target blob: passed; checkpoint and spec are current.
- `git diff --check b75e91ee8870897415b5554e51b8840749e5082a 599c43c74c671e71f3a8e40b9d835512a22b6aee` and `git diff --check`: passed.
- `php artisan test --compact tests/Feature/CustomHostnameTest.php tests/Feature/SiteBillingLifecycleTest.php`: passed, 84 tests and 425 assertions.
- `php artisan test --compact tests/Feature/CustomerDomainTransportTest.php tests/Feature/DomainProxyTest.php`: passed, 79 tests and 577 assertions.
- `composer lint:check`: passed after retry with permission for its local parallel-worker socket; the initial sandbox attempt was blocked by EPERM.
- `composer types:check`: passed, zero PHPStan errors.
- Offline PHP boundary probe using the real rule and independent DNS wire-length calculation: passed; lengths 240/241 accepted, lengths 242/253 rejected, no network calls.
- Focused searches for skipped, focused, or placeholder tests in the changed test files: none found.

## Evidence

- Reviewed the entire `b75e91ee8870897415b5554e51b8840749e5082a..599c43c74c671e71f3a8e40b9d835512a22b6aee` delta. Product/test scope is `app/Rules/CustomerHostname.php`, `tests/Feature/CustomHostnameTest.php`, and `tests/Feature/SiteBillingLifecycleTest.php`; the active verified spec was checked separately and findings/request files excluded from code scope.
- Followed all rule callers in `ReserveCustomHostname`, `StartSiteCheckout`, and `DomainProxyController`, plus `CustomHostname::ownershipRecordName()`, ownership DNS resolution, domain controller/summary, and the existing form error handling. Normalization, ownership, excluded-host checks, checkout ordering, and normal public routing remain intact.
- The 12-character ownership prefix leaves a 241-character hostname allowance. The accepted boundary generates a 253-character presentation name and a 255-byte wire name. The rejected fixtures remain valid hostnames but cannot form a valid ownership record.
- HTTP regressions prove the owner returns to Go Live with the hostname error before reservation, checkout state, Stripe customer/subscription creation, or Stripe requests. The retained-reservation checkout guard rejects overlong names without rewriting persisted intent.
- Checked repository PHP/Laravel validation, ownership, minimal-change, test-isolation, and formatting standards. The additional validation is bounded, local, and introduces no dependency, database query, external call, or persistence change.
- Parent-reported `composer ci:check` success (980 tests) is recorded in the verified spec. This reviewer independently ran the relevant tests, Pint, and PHPStan; it did not rerun the unchanged frontend build/checks or full suite.
- Excluded dependencies, generated assets/Wayfinder output, caches, and unrelated source from review. The selected current scope and all four lenses were fully reviewed.

## Findings

- F-06 closed after independent confirmation of the repair. No new findings; no open or fixed P0/P1 findings remain. No repair order is needed.

## Remaining risk

- Browser interaction and live DNS, Stripe, or Cloudflare integration were not exercised. HTTP tests and offline DNS-name checks cover the changed behavior; the request does not require a Check runtime gate.
