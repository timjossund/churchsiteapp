# Fix: Generated-file formatting failures

**Type:** Fix
**Status:** verified
**Branch:** fix/generated-file-formatting-failures

## The problem

`composer ci:check` currently exits during `npm run check`: Vite Plus reports formatting failures in 78 already-modified generated files, so subsequent TypeScript and PHP checks do not run.

`vite.config.ts` excludes Wayfinder output from linting, but its separate formatter exclusions omit `resources/js/actions/**`, `resources/js/routes/**`, and `resources/js/wayfinder/**`. Wayfinder regenerates these files using its own formatting. The Laravel Vite font plugin also writes `public/fonts-manifest.dev.json` with two-space JSON indentation, while the project formatter expects four spaces. Reformatting these outputs alone would leave the check vulnerable to the next regeneration.

## The fix

Add narrowly scoped formatter exclusions for those three generated Wayfinder directories and `public/fonts-manifest.dev.json` in the existing `fmt.ignorePatterns` configuration. Keep authored source formatting, existing lint rules, TypeScript checks, and PHP checks enabled. Keep generators and application behavior unchanged; add no dependencies or new test runner.

Preserve the existing generated-file working changes. They predate this fix and must not be reset, reformatted wholesale, or silently included in the repair commit. Inspect and account for them separately before completion. Do not change Git tracking policy or broaden the exclusion to all public assets.

## Build steps

- [x] Update the four formatter exclusions in `vite.config.ts` and verify the normal generation/check cycle. **Done when:** `npm run check` accepts the existing generator output, `npm run build` succeeds, and `composer ci:check` passes after the build regenerates Wayfinder output. Authored source remains covered by formatting and all existing checks remain enabled. Report unrelated failures separately instead of expanding this fix.

## Build loop

Use the configured per-step review cadence. Present the one-step diff and verification results for review. Checkpoint commits are enabled but optional and require approval. `/complete` creates the final fix commit; this spec does not authorize committing or merging.

## Verify

- Baseline evidence already observed: `composer ci:check` exited with code 1 on formatting in 78 generated files; later checks were not reached.
- Run `npm run check` after changing the formatter configuration.
- Run `npm run build`, then `composer ci:check` to verify regeneration does not reintroduce failures and to exercise frontend lint/type checks plus PHP format, static analysis, and Pest tests.
- Inspect the final diff to confirm the repair is limited to formatter configuration and workflow records, with existing generated changes preserved and accounted for separately.
- No new unit or browser test is required for this configuration-only fix. Build and existing checks are the acceptance evidence; no live browser or billing behavior is claimed.

## Verification results

- `npm run check`: passed; 126 files formatted correctly and 69 files passed lint.
- `npm run build`: passed and regenerated Wayfinder output. Existing optional Fontaine font-fallback warning remains.
- `composer ci:check`: passed after rerunning with permission for local worker sockets; frontend checks, Pint, PHPStan, and all 232 Pest tests (2,359 assertions) passed. The initial sandboxed run stopped at Pint with EPERM.
- Product diff: four formatter exclusions in `vite.config.ts`. Pre-existing generated changes remain outside the repair scope.
- Configured audit, Check, and try-guide gates are manual. Independent review is not selected for this narrow, non-sensitive configuration change; no request is pending. Existing F-02 is an unrelated open P2 contrast finding; no P0/P1 blockers are recorded.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":3862,"specSha256":"e6de6196b493f911b080b1c759ed6acbde7e38f5824feb5af241eaa25746eb91","branch":"refs/heads/fix/generated-file-formatting-failures","head":"dbedb5ece77ab8bbe79533ebbc04aeebc31870c8","baseRef":"refs/heads/main","baseCommit":"dbedb5ece77ab8bbe79533ebbc04aeebc31870c8","sourceTree":"eaa45c11a97f4a8c8ac2c19923d1a6dfcbfcf7a8","absentOptional":[]} -->
