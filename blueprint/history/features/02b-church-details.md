# Feature: Church details

**From build-plan:** feature 2b
**Build attempt:** 1
**Branch:** `feature/church-details`
**Status:** verified

## Goal

Let a site owner add and edit Hero, Service times, and Contact blocks in the existing private, single-page editor. The blocks save with the site's other blocks, move freely in page order, and show useful editor previews.

## In scope

- Hero heading, supporting text, and at most one optional button to another block on the same site or an absolute HTTP(S) URL.
- Ordered, recurring weekly Service times entries with weekday, local time, and optional label.
- Contact email and phone details that produce `mailto:` and `tel:` links.
- Existing explicit Save, unsaved-change protection, ownership checks, block persistence, ordering, and editor layout for every new type.
- Accessible labels, field errors, pending state, empty state, and usable desktop and narrow layouts.

## Out of scope

- Hero image and image uploads (Feature 4).
- Image, text-and-image, and video blocks (Feature 2c).
- Visitor contact forms, dated events, timezone conversion, themes, public Blade rendering, publishing, domains, and billing.

## Build loop

The project uses Guided review: implement one small step, verify it, and pause for approval before the next step. After an approved step, offer its optional checkpoint commit. `/complete` creates the final feature commit. Do not implement this spec until it is approved.

## Build steps

- [x] **1. Persist the new shapes.** Extend the existing store/update type choices, defaults, and server validation for Hero, Service times, and Contact. Keep owner-scoped site and block checks. Clear a Hero section link atomically when its destination block is deleted. Update the block content model annotation and add focused feature tests. **Done when:** valid shapes save and reload; malformed, unsafe, or cross-site values fail with field errors; deleting a target clears its link; existing block tests pass.
- [x] **2. Edit Hero.** Add Hero to the block picker and side panel. Support heading, body, one optional button, section target selection, external URL, saved preview, and stable section anchors. Preserve explicit Save, pending and error states, and unsaved-change protection. **Done when:** an owner can add, edit, save, reload, reorder, and delete a Hero; valid buttons navigate to the chosen block or URL; invalid inputs retain the draft; focused tests and build checks pass.
- [x] **3. Edit Service times.** Add controls for creating, editing, moving, and removing weekly entries, with readable local times in the preview and field-level errors. **Done when:** entry order and values survive save/reload; empty entries are supported; invalid day/time inputs retain the draft and show errors; focused tests and build checks pass.
- [x] **4. Edit Contact and regress the editor.** Add email and phone fields with clickable preview links and validation feedback. Review all six block types together, including keyboard use and narrow widths. **Done when:** valid links work, empty values show no links, invalid values show errors, existing editor actions still work, and project checks plus the live walkthrough pass.

- [x] **5. Repair F-04 and F-05.** Reject incomplete Service times rows, normalize validated Hero section target IDs, and clear links to deleted targets even when older content stores an ID as a string. **Done when:** endpoint regressions prove invalid rows preserve saved content, accepted string IDs reload as integers, and integer/string stored targets are cleared on deletion; existing PHP checks, TypeScript, and build pass.

## Files / areas

- `app/Http/Requests/StoreSiteBlockRequest.php` and `app/Http/Requests/UpdateSiteBlockRequest.php` for type-specific validation.
- `app/Http/Controllers/SiteBlockController.php` for defaults and deletion cleanup; `app/Models/SiteBlock.php` for nested content annotation.
- `resources/js/pages/Sites/Show.vue` and a small component only if the editor needs one for clear, reusable controls.
- `tests/Feature/SiteBlockEditorTest.php`, `SiteBlockPersistenceTest.php`, and `SiteBlockOrderingTest.php`, adding focused cases as needed.
- No migration, new route, or new dependency is expected: use the existing JSON content and block routes.

## Data / contracts

The `site_blocks.content` JSON is type-specific. Store and update accept only fields for the requested or persisted block type, reject unknown keys and changes to `site_id`, `type`, or `position`, and retain the current owner-scoped site/block boundary. Server validation is authoritative; failed saves preserve editor input and give actionable errors.

| Type | Content shape | Rules |
| --- | --- | --- |
| `hero` | `heading`, `body`, `button_label`, `link_type`, `target_block_id`, `external_url` | Text fields are strings. `link_type` is `none`, `section`, or `external`. For `none`, button fields are empty/null. For `section`, a nonempty button label and a different existing block ID on the same site are required; external URL is empty. For `external`, a nonempty label and absolute HTTP(S) URL are required; target ID is null. |
| `service_times` | `heading`, `entries` | Heading is a string. Entries are an ordered array of objects with exactly `day`, `time`, and `label`. Day is Monday through Sunday; time is a valid local `HH:MM` 24-hour value; label is a string that may be empty. An empty entries array is valid. |
| `contact` | `heading`, `email`, `phone` | Fields are strings and may be empty drafts. A nonempty email must be a valid address. A nonempty phone must contain digits and may use a leading `+` and common display separators; reject characters that cannot form a safe `tel:` URI. |

New blocks start with empty text, Hero `link_type: none` and null target, and Service times `entries: []`. Hero section links use `#block-{id}` anchors on block previews so reordering does not change the destination. If a target block is deleted, clear referencing Hero buttons in the same transaction, leaving their text intact. Contact links render only for valid nonempty values. Its phone link removes display separators while preserving a leading plus; the visible phone text keeps the user's formatting. Render all user text through Vue's escaped text binding and allow only validated link destinations.

## Testing

- Focused Pest tests cover defaults, exact content shapes, invalid fields, Hero target ownership and deletion cleanup, Service times entries, Contact input, and existing block regressions.
- Run `composer test`, `npm run types:check`, and `npm run build`; apply the project's formatter/linter to changed files as appropriate.
- In the running editor, walk through add, edit, save, reload, reorder, delete, validation errors, links, keyboard controls, and desktop/narrow layouts. Record observed results during implementation; do not assume the local server is running.
- Live walkthrough: the user reported that the requested editor checks worked well at `http://churchsiteappnew.test/` on 2026-09-23.

### F-04 / F-05 repair verification

- Regression tests failed before the repairs and passed afterward: incomplete row combinations preserve saved content, accepted string Hero IDs reload as integers, and deletion clears integer and legacy string targets. Focused suite: 17 tests / 137 assertions.
- `composer test` passed outside the sandbox after its local parallel-worker socket was blocked: Pint, PHPStan, 93 tests / 508 assertions. `npm run types:check`, `npm run build`, and `git diff --check` passed. Build refreshed tracked output and Wayfinder source references.
- No fresh live browser walkthrough was performed for these backend repairs. Existing repository-wide formatting failures reported by Audit remain outside this repair.
- F-04 and F-05 are fixed, awaiting an approved checkpoint and fresh independent review.

## Notes for the AI

- The user chose one Hero button now and a Hero image after image uploads arrive in Feature 4. The user chose recurring weekly Service times entries.
- Reuse the Feature 2a editor and routes. Keep implementation proportional and add no abstraction or dependency without a current need.
- Empty text and zero Service times entries are valid draft states. An incomplete selected Hero link or malformed Service times row cannot be saved.
- Show errors next to their labeled controls, announce validation feedback accessibly, and clear stale errors after correction. Keep the user's draft on unexpected request failures.
- The public renderer is planned later; do not claim published-site behavior in this feature.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":8610,"specSha256":"dcce56942bca7e83746ce45a363efa5703ae089d3d8b52c042d2841e49add94a","branch":"refs/heads/feature/church-details","head":"1aba0674cb4aa856d2ea6a3b66e2e8e669553b74","baseRef":"refs/heads/main","baseCommit":"aac0992916b82c42595dec364b710eaeda8afce4","sourceTree":"b2e43b415648f1ac5db535d907332dc6131e8b1e","absentOptional":[]} -->

## Findings

### 2b/F-03 [P2] closed - Keep invalid draft email addresses out of contact links

**File:** resources/js/pages/Sites/Show.vue:281
**Found:** 2026-09-23 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The contact preview turns a draft email into a `mailto:` link using a simple pattern that accepts addresses the server rejects. For example, `a..b@example.org` passes the JavaScript pattern but fails the `email:rfc` rule in `UpdateSiteBlockRequest.php:90`. A user editing a contact block therefore sees an active link for a value that cannot be saved, contrary to the spec's valid-links-only rule.
**Suggested fix:** Align the preview's validity check with the server rule, or show draft email as plain text until the server has accepted it. Keep the saved address linkable.
**Resolution:** The preview now links only the last server-accepted email and shows edited draft addresses as plain text. Added a regression case for an RFC-invalid address. `composer test`, targeted frontend check, TypeScript, and build passed. Independently re-reviewed on 2026-09-23 at `40c2b7f70dbf0e7070d03e4d89446267c128f3d1`: the actual `emailHref` function passed five isolated assertions for saved, edited, RFC-invalid, empty, and unselected saved addresses. The edited draft cannot become a link before server acceptance; the RFC-invalid regression also passed in the 91-test Pest suite. Original defect is gone; F-03 closed.

### 2b/F-04 [P1] closed - Reject incomplete Service times rows

**File:** app/Http/Requests/UpdateSiteBlockRequest.php:82
**Found:** 2026-09-23 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The day and time fields use `present` instead of `required`. Laravel skips their non-implicit enum and regex rules for empty strings, and the block PATCH middleware preserves those strings. The normal Add a gathering action creates exactly such a row. An isolated in-memory HTTP reproduction saved rows with both fields empty, only day empty, and only time empty: every PATCH returned 302 and persisted the incomplete row. This violates the explicit contract that an incomplete Service times row cannot be saved, and prevents actionable validation errors from reaching the editor.
**Suggested fix:** Require nonempty day and time for every existing entry while continuing to allow an empty entries array and an empty optional label. Add endpoint regression cases for each incomplete-row combination and verify field errors leave saved content unchanged.
**Resolution:** Fixed by `/implement` on 2026-09-23. Day and time now use required validation. Endpoint regressions cover both empty, empty day, and empty time and assert field errors preserve the saved content; empty entries and labels remain supported. Tests failed before the fix and passed afterward; full verification passed (93 tests / 508 assertions, Pint, PHPStan, TypeScript, build). Independently re-reviewed on 2026-09-23 at `1aba0674cb4aa856d2ea6a3b66e2e8e669553b74`: the complete feature delta was reviewed across all four lenses. The actual endpoint regressions for both-empty, empty-day, and empty-time rows passed and assert the saved content remains unchanged; valid empty lists and empty labels also passed. Full Pest suite passed with 93 tests / 508 assertions. The required day/time rules remove the original defect without removing valid empty drafts; F-04 closed.

### 2b/F-05 [P1] closed - Normalize accepted Hero section IDs before persistence

**File:** app/Http/Controllers/SiteBlockController.php:70
**Found:** 2026-09-23 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The request's `integer` rule accepts numeric strings, and update stores the validated content without casting the target. Deletion then compares the JSON target strictly against the integer route argument. In an isolated in-memory HTTP reproduction, a section target sent as a numeric string was accepted and persisted as a string; deleting that destination returned 302 but left the Hero's label, section mode, and deleted target ID intact. The frontend also requires a numeric target to render its section button, so this accepted representation fails before deletion as well. This breaks the promised valid-target persistence and atomic link cleanup contract.
**Suggested fix:** Normalize a validated section target to an integer before saving, and make deletion cleanup handle already-persisted numeric strings consistently. Alternatively reject non-integer JSON types explicitly if that is the intended request contract. Add a request-to-delete regression using a string ID, alongside the existing integer-ID case.
**Resolution:** Fixed by `/implement` on 2026-09-23. Validated section targets are cast to integers before persistence. Deletion compares normalized IDs so legacy string targets are cleared too. Regression tests cover accepted numeric-string update, integer ID in reloaded Inertia props, request-to-delete cleanup, and both integer/string stored targets. Tests failed before the fix and passed afterward; full verification passed (93 tests / 508 assertions, Pint, PHPStan, TypeScript, build). Independently re-reviewed on 2026-09-23 at `1aba0674cb4aa856d2ea6a3b66e2e8e669553b74`: update casts validated section targets before persistence and rechecks existence under the same site lock used by deletion. Endpoint tests proved a numeric-string request reloads as an integer and clears after deletion, and both integer and legacy string stored targets clear while preserving heading/body. Full Pest suite passed with 93 tests / 508 assertions. The original defect is gone and no new repair defect was found; F-05 closed.

## Independent review

# Independent Review

**Status:** passed
**Target commit:** 1aba0674cb4aa856d2ea6a3b66e2e8e669553b74
**Base commit:** aac0992916b82c42595dec364b710eaeda8afce4
**Base ref:** main
**Spec hash:** dcce56942bca7e83746ce45a363efa5703ae089d3d8b52c042d2841e49add94a
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-23T22:36:15.394877+00:00
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-23T22:39:12.599774+00:00
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Handoff

Review the active spec and the complete `aac0992916b82c42595dec364b710eaeda8afce4..1aba0674cb4aa856d2ea6a3b66e2e8e669553b74` delta in a fresh
session or isolated subagent without the builder conversation. Run all Audit lenses from scratch.
Run Check when required above. Do not edit product code, accept findings, or
reuse the existing findings as the review scope.

## Commands

- `git status --short`, `git status --porcelain --untracked-files=all`, `git branch --show-current`, `git rev-parse HEAD`, `git merge-base main HEAD`, and raw-byte SHA-256 verification: pass; checkpoint, permitted base, spec, and allowed dirty paths match the request before and after review.
- `git diff --stat`, `git diff --name-status`, and full source/test delta from `aac0992916b82c42595dec364b710eaeda8afce4..1aba0674cb4aa856d2ea6a3b66e2e8e669553b74`: inspected.
- `git diff --check aac0992916b82c42595dec364b710eaeda8afce4..HEAD`: pass.
- `composer test`: unavailable as a complete command; its parallel Pint worker could not bind a loopback socket in this sandbox (EPERM). Serial equivalents below passed.
- `vendor/bin/pint --test`: pass.
- `vendor/bin/phpstan analyse --debug --no-progress`: pass, zero errors.
- `php artisan test`: pass, 93 tests / 508 assertions using the configured in-memory SQLite database.
- `npm run types:check`: pass.
- `npm run check`: fail, formatting issues in 90 workflow, documentation, generated helper, and other files. The changed authored Vue component is not reported.
- `node_modules/.bin/vp check resources/js/pages/Sites/Show.vue`: pass, formatting and lint.
- In-memory Node/TypeScript extraction of actual `emailHref`, `phoneHref`, `heroHref`, and `formatServiceTime` functions: pass, 12 assertions for saved/draft/empty email, phone sanitization, valid/missing section targets, HTTP(S)/unsafe protocols, and midnight/noon/evening display.
- Targeted skipped/focused/placeholder test and `v-html` search in changed tests and editor: no matches.

## Evidence

- Fresh isolated Codex reviewer, runtime model `gpt-6-astra`, with no builder transcript, completed all four lenses over the whole requested feature delta. Request fields remain unchanged.
- Reviewed all authored changed application files: `SiteBlockController.php`, `StoreSiteBlockRequest.php`, `UpdateSiteBlockRequest.php`, `SiteBlock.php`, `Sites/Show.vue`, `ChurchDetailBlockTest.php`, and `SiteBlockEditorTest.php`. Followed neighboring site controller, routes, middleware, persistence/order tests, test configuration, and package commands to verify reachable behavior.
- Quality: examined exact content shapes, empty defaults, state loading, draft preservation, explicit save, field feedback, service-row editing, preview links, and existing editor integration against the active spec and coding standards. No new actionable finding.
- Security: owner-scoped site/block queries, authenticated routes, forbidden keys, same-site Hero targets, validated HTTP(S)/telephone/email links, escaped Vue text, and transactional target deletion were reviewed. No new authorization, injection, or data-integrity defect found.
- Performance: reviewed existing per-site locking, target recheck, Hero cleanup, order updates, and Vue iteration. No new confirmed performance defect for the stated single-page workload; no profiling claim is made.
- Tests: reviewed new validation/persistence cases and existing ownership/order regressions. No skipped, focused, or placeholder tests in the reviewed feature tests. F-04 incomplete-row cases and F-05 string-ID update/reload/delete plus legacy-target cases passed independently within the full suite.
- F-03 remains closed: the actual preview function keeps edited email drafts plain until server acceptance, and the RFC-invalid address endpoint regression passed.
- Generated Wayfinder changes were inspected and contain controller source-line reference updates only. Excluded generated/minified `public/build/assets/*` and generated manifest from authored-code review; dependencies, caches, and unrelated application areas were outside this current-feature audit.
- The spec records the user's prior successful live walkthrough. This is historical evidence, not a browser run by this reviewer. Check is explicitly not required by the request.

## Findings

- F-04 [P1]: closed after independent code review and passing endpoint regressions.
- F-05 [P1]: closed after independent code review and passing endpoint regressions.
- No new findings. F-02 [P2] remains open and unchanged; F-03 remains closed.

## Remaining risk

- `composer test` could not complete its parallel worker execution inside the sandbox. Serial Pint, PHPStan, and the complete Pest suite passed independently.
- Repository-wide `npm run check` still fails on 90 formatting paths; this receipt does not waive or fix that existing verification gap. The authored changed Vue component passed its targeted check.
- No fresh live browser, narrow-width, or keyboard walkthrough was performed, and no browser automation is configured. Isolated helper assertions do not establish end-to-end browser behavior.
- The build was not rerun because it rewrites tracked generated output. The spec reports the builder's passing build, and reviewer TypeScript verification passed; generated asset behavior was not independently validated.
- Concurrency was inspected through the common site-lock transactions, not exercised against MySQL under parallel load. No current dependency vulnerability scan or runtime performance profile was run.
- Existing nonblocking F-02 contrast concern remains unresolved.
