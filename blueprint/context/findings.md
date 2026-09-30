# Findings

> **Generated file.** The findings ledger: review findings raised by `/audit`
> against the work in progress, each with a durable ID, severity (P0-P3), and
> status. `/implement` marks repaired findings `fixed`, a later `/audit` pass
> moves them to `closed`, and `/complete` refuses to merge while any P0 or P1
> finding is `open` or `fixed`, then archives resolved findings with the work
> and resets this file.

### F-06 [P2] open - Validate the ownership record length before checkout

**File:** app/Rules/CustomerHostname.php:12
**Found:** 2026-09-27 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The rule accepts full 253-character hostnames, but `CustomHostname::ownershipRecordName()` prepends `_churchsite.`, making the required ownership TXT name 265 characters. DNS cannot represent that name. Hosts longer than 241 characters can therefore be reserved and sent to paid checkout even though this connection can never satisfy its mandatory ownership check. An offline PHP reproduction using the exact 253-character boundary fixture confirmed `CustomerHostname` accepts it and native `FILTER_VALIDATE_DOMAIN` accepts the hostname but rejects its generated ownership name. The existing boundary test verifies reservation only, so it misses this integration constraint. This is a rare boundary input, not a general onboarding failure.
**Suggested fix:** Validate the generated ownership DNS name before reservation and checkout, using the existing validation rule/native domain validation, and show a hostname error when it cannot fit. Update the boundary regression to cover the generated record and rejection before payment. Reconcile the spec's stated 253-character acceptance with the required ownership prefix when making the repair; do not introduce an alternate ownership protocol solely for this edge case.
**Resolution:** Confirmed at `1bd9ea5c94d10e6d573435038f468a8b370da442`. Reproduction built `www.` plus labels of 63, 63, 63, and 57 characters; hostname length 253, application acceptance true, native hostname validation true, ownership length 265, native ownership validation false. No DNS request or provider call was made. Remains open P2.
