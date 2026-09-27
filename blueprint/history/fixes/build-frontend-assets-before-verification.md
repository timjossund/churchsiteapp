# Fix: Build frontend assets before verification

**Type:** Fix
**Status:** verified
**Branch:** fix/build-frontend-assets-before-verification
**Archive:** blueprint/history/fixes/build-frontend-assets-before-verification.md

## The problem

Local verification depends on an existing Vite manifest even though the repository's tracked build output is stale. After main removed the development-only `public/hot` file, `composer ci:check` passed formatting, lint, and type checks but failed 50 of 362 Pest tests because manifest entries including `resources/js/published.ts` and `resources/js/pages/Sites/Settings.vue` were missing. A fresh `npm run build` followed by the same checks passed all 362 tests with 3,121 assertions. The live application had the same missing Settings entry; the operator rebuilt its assets and confirmed site creation/navigation now work.

The current Composer scripts run frontend checks and PHP tests without generating current frontend assets. Feature 7b planning is waiting for a repeatable passing baseline.

## The fix

Add the existing `npm run build` command to the shared `composer test` sequence before Pest runs. Keep `composer ci:check` delegating to `@test`, so each entry point builds once and neither relies on the previous manifest. Preserve config clearing, Pint, PHPStan, frontend formatting/lint, and TypeScript checks; any build failure must stop verification with a nonzero exit status.

Use native Composer script sequencing. No new dependencies, test framework, shell wrapper, or dependency-install step is needed. Keep focused `php artisan test` available when assets are already built. Document that the combined check and `composer test` require installed Node dependencies and generate build/Wayfinder output.

Do not disable Vite in tests, manufacture a manifest, restore `public/hot`, or weaken assertions to conceal the missing assets. Do not change application behavior, billing, Worker routing, deployment infrastructure, GitHub Actions, or build-plan scope. Removing all tracked generated output is a separate repository cleanup, not part of this fix.

## Build steps

- [x] **1. Build current assets in the shared verification path and document it.** Update `composer.json` so `composer test` performs one frontend build before Pest, while `composer ci:check` retains its existing checks and delegates to that sequence. Update the Commands descriptions in `AGENTS.md` and directly affected testing guidance in `blueprint/context/coding-standards.md` to reflect the build prerequisite and generated output.
      **Done when:** With installed dependencies, no development server, and no `public/hot`, `composer ci:check` rebuilds the stale/missing manifest and passes frontend checks, Pint, PHPStan, and the full Pest suite. Its log shows one successful production build before Pest. Confirm the generated manifest includes the published entry and Settings page. Inspect the Composer script chain to verify `composer test` shares that same build and failed build commands cannot fall through into Pest. No dependency installation or application data changes are introduced by verification.

Follow configured per-step review and checkpoint approval rules. Preserve pre-existing dirty generated Wayfinder files and stashes. Build-generated output is verification evidence, not source changes for this fix: preserve or isolate it explicitly and keep it out of the eventual source commit. Do not overwrite unrelated work to obtain a clean status. The implement handoff reports verification and generated-file handling; completion owns the final commit and separate merge/push approvals.

## Verify

- Reuse the recorded failing baseline rather than rerunning it before the change.
- Inspect `composer.json` script delegation for one build on each supported entry point and fail-fast command ordering.
- Run `composer ci:check` after implementation, without a Vite dev server or hot-file shortcut. Confirm manifest entries and all existing tests pass.
- Use a temporary backup if missing-output verification needs existing generated assets moved; preserve exact pre-existing contents. Do not delete user work or introduce a test that only mirrors the script configuration.
- Check the final source diff contains only the script/documentation fix and its workflow evidence. No production deployment or live browser test is needed for this local command change.
- After completion, resume `$feature 7b`.

## Implementation evidence

- `composer ci:check` passed on 2026-09-27: frontend formatting/lint/types, Pint, PHPStan, one production build, and 362 Pest tests with 3,121 assertions. The run began with stale build output and no `public/hot`; the build finished before Pest.
- Generated manifest contains `resources/js/published.ts` and `resources/js/pages/Sites/Settings.vue`. Native Composer sequencing propagates a failing build command before reaching Pest; both entry points share the one build in `test`.
- Existing generated files were backed up before verification. Fresh output was preserved separately and pre-existing generated contents restored exactly, keeping build churn out of this fix. Baseline and output locations are recorded in `/tmp/churchsite-build-verification-backup-path`; check log is `/tmp/churchsite-build-verification-ci.log`.
- This local script/documentation change does not select the sensitive-work independent-review gate. No review request exists; unrelated F-02 (P2) and F-03 (P3) remain unchanged. No automatic browser/check/try-guide gate is configured for this change.

Completion verification: `composer ci:check` passed again on 2026-09-27 with one frontend build and 362 tests / 3,121 assertions. Generated changes were preserved in a named Git stash and a filesystem backup; only source/documentation changes enter this fix.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":5827,"specSha256":"df695851f6d0bc01b95871e467b21ca8d089a950152a3385d567267024b0ee8a","branch":"refs/heads/fix/build-frontend-assets-before-verification","head":"a9ab854733d63971e05e418ffddf99d0df29b8a2","baseRef":"refs/heads/main","baseCommit":"a9ab854733d63971e05e418ffddf99d0df29b8a2","sourceTree":"09e226367a7c98f9d3b48b03e72e16190dec01c3","absentOptional":[]} -->
