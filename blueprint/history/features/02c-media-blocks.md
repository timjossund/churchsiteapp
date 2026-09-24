# Feature: Media blocks

**From build-plan:** feature 2c
**Build attempt:** 1
**Branch:** feature/media-blocks
**Status:** verified

## Goal

Add image, text-and-image, and video blocks to the existing single-page editor. Users can arrange them with the existing blocks, edit supported fields in the side panel, and see safe previews. Image sources remain placeholders until Feature 4 adds uploads.

## In scope

- Add `image`, `text_image`, and `video` to the existing block creation, persistence, ordered list, preview, and side-panel editing flows.
- Let users edit the heading and body of a text-and-image block. Keep its image area as a placeholder until uploads are available.
- Let users enter and save a single YouTube or Vimeo video link and preview it as an embed.
- Keep all new block actions owner-scoped, preserve drafts on failed saves, and give labeled, accessible validation feedback.

## Out of scope

- Uploading, selecting, replacing, or storing images; image metadata and alt text; IONOS integration. Feature 4 owns these.
- Hero images, public rendering or publishing, themes, and additional video providers or playlist embeds.
- New routes, tables, packages, or changes to existing block contracts.

## Build loop

- Implement the steps in order. The configured Guided cadence requires review and approval after **each** step before continuing.
- After an approved, passing step, offer the configured optional checkpoint commit. `/complete` creates the final feature commit after its final gates.
- Keep tests focused on the behavior added at each step. Record only observed results.

## Build steps

- [x] **1. Add the server contracts for media blocks.** Extend the existing create and update validation for `image`, `text_image`, and `video`, with the defaults and exact content shapes in Data / contracts. Keep the current owner-scoped routes, ordering, deletion, and JSON persistence. Add focused Pest coverage for defaults, valid updates, malformed or extra fields, and rejected unsafe video URLs. **Done when:** an owner can create and save each new block shape through the existing routes, invalid updates leave saved content unchanged, cross-owner access remains denied, and the focused PHP tests pass.
- [x] **2. Add media blocks to the editor.** Add the types to the block picker, preview, list, and side panel in `Sites/Show.vue`. Let text-and-image edit its text fields, show clear upload placeholders for image areas, and let video edit its URL with an embed preview only for a supported provider URL. Include new drafts in dirty-state and save handling, and keep validation, pending, failure, and focus behavior consistent with existing blocks. **Done when:** the owner can add, edit, save, reload, reorder, and remove all three types; invalid video input stays visible with an associated error and never renders an iframe; focused tests, TypeScript, and production build pass, and a manual keyboard and narrow-layout walkthrough succeeds.
- [x] **3. Enforce strict media and video query validation.** Reject every `media_asset_id` value except JSON `null`, including `false` and the empty string. Make server validation and editor preview agree on video query parsing: reject bracketed or duplicate query keys and playlist keys, while preserving supported single-video URLs. Add focused Pest coverage and verify the preview does not render an iframe for the same ambiguous URLs rejected by the server. **Done when:** malformed media references leave saved content unchanged; bracketed and duplicate video parameters are rejected by the server and never produce a preview iframe; focused PHP, TypeScript, Vue checks, and production build pass.
- [x] **4. Reject empty video query segments in previews.** Match the existing server parser when video query strings contain leading, trailing, or repeated `&` separators. Add focused server cases and verify the editor preview returns no iframe for the same URLs while preserving supported single-video links. **Done when:** the server rejects and the preview suppresses an iframe for URLs with empty query segments; valid supported URLs still preview; focused PHP, TypeScript, Vue checks, and production build pass.
- [x] **5. Reject browser-normalized encoded video hosts.** Compare the raw hostname with the parsed hostname before constructing an embed URL, so browser normalization cannot make a server-rejected host appear valid. Add a focused rejection case for a percent-encoded YouTube hostname and verify the actual preview helper suppresses that iframe. Preserve case-insensitive supported hosts and HTTPS default-port behavior. **Done when:** the server and preview both reject the encoded hostname; valid supported host casing and port forms still work; focused PHP, TypeScript, Vue checks, and production build pass.
- [x] **6. Reject browser-normalized user info and control characters.** Reject any `@` in the raw URL authority, including empty user-info prefixes, and reject interior ASCII control characters before browser URL parsing can remove them. Add focused server cases and verify the actual preview helper suppresses iframes for the same malformed URLs. Preserve surrounding-whitespace trimming and supported HTTPS hosts and port forms. **Done when:** empty user-info prefixes and interior control characters are rejected by both the server and preview; supported URLs still preview; focused PHP, TypeScript, Vue checks, and production build pass.
- [x] **7. Reject control characters before PHP URL parsing.** On the trimmed URL, reject interior ASCII control characters before `parse_url` can normalize them. Add focused server cases for a tab in a short-link ID and in ancillary query or fragment text, asserting validation errors and unchanged saved content. Preserve surrounding-whitespace trimming and supported video URLs. **Done when:** the server rejects interior ASCII controls in the path, query, or fragment, in agreement with the preview; valid URLs and surrounding-whitespace trimming still work; focused PHP tests and final checks pass.

## Files / areas

- `app/Http/Controllers/SiteBlockController.php` for type-specific initial content.
- `app/Http/Requests/StoreSiteBlockRequest.php` and `UpdateSiteBlockRequest.php` for accepted block types, exact content fields, and video URL validation.
- `resources/js/pages/Sites/Show.vue` for the picker, side panel, draft state, and escaped editor preview.
- `tests/Feature/SiteBlockEditorTest.php` or a focused `tests/Feature/MediaBlockTest.php` for creation, validation, persistence, and ownership regressions.
- No migration, route, or dependency is expected; blocks already store ordered JSON content.

## Data / contracts

- Preserve the existing authenticated, verified, owner-scoped site and block routes. The server remains authoritative for block type and content validation; clients cannot change `site_id`, `type`, or `position` through content updates.
- `image` content is exactly `{ "media_asset_id": null }`. The editor displays an image placeholder and offers no upload action. Reject non-null asset IDs until Feature 4 adds site-owned media assets and their authorization checks.
- `text_image` content is exactly `{ "heading": string, "body": string, "media_asset_id": null }`. Heading and body follow the existing text-block handling. Its image area remains a placeholder.
- `video` content is exactly `{ "url": string }`. An empty URL is a valid new-block draft. For a non-empty value, trim surrounding whitespace and accept only HTTPS single-video links: YouTube `youtube.com/watch?v=<11-character-id>` or `youtu.be/<11-character-id>`, and Vimeo `vimeo.com/<numeric-id>`, with optional `www` hosts. Before PHP URL parsing, reject interior ASCII control characters; before browser URL parsing, also reject any `@` in the raw authority and interior ASCII control characters. This prevents parser normalization from making server-rejected input previewable or persisting server-accepted input that cannot preview. The preview must extract the raw ASCII hostname without decoding, lowercase it, and compare it with the browser-parsed hostname before provider validation; percent-encoded or otherwise normalized hosts are invalid. Query segments must be non-empty, query keys must be simple ASCII names and unique, and YouTube playlist keys are rejected. Reject unsupported hosts, malformed IDs, and other schemes. Keep the validated source URL in the JSON content; construct the preview iframe URL from the parsed provider and video ID on an allowlist rather than embedding arbitrary user input.
- Show no iframe for an empty or invalid draft URL. Give each rendered iframe an accessible title. Render headings, body text, and errors as text, not HTML. Invalid saves retain the user's entry, associate errors with the labeled URL field, announce feedback, and move focus using the editor's existing validation pattern.
- Image placeholders are not images and need no alt text. Feature 4 must supply the image and description contract when real assets are introduced.

## Testing

- Add focused Pest tests for exact defaults and updates, valid YouTube/Vimeo URL forms, empty video drafts, rejected malformed or unsafe URLs, strictly null image references, bracketed, duplicate, and empty video query segments, encoded provider hosts, empty user-info prefixes, interior ASCII control characters in video paths, queries, and fragments, unchanged stored content after invalid updates, and existing ownership boundaries.
- Run `composer test`, `npm run types:check`, and `npm run build`; run the relevant frontend check for the changed Vue file. Browser automation is not configured, so manually inspect add/edit/save/reload, video preview, validation, keyboard focus, and narrow layouts.
- Do not claim live browser or integration evidence unless it is observed during implementation.

## Notes for the AI

- Reuse the current `SiteBlockController`, Form Requests, and editor patterns. Do not add storage, upload controls, migrations, routes, a public renderer, or a new abstraction for these placeholders.
- Preserve the existing text, Hero, Service times, and Contact behaviors while adding the new types to the same ordering and deletion flows.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":10228,"specSha256":"15be199997be5cf5c4c50c49f117188b8273d05b91f2655f65ab715a1beba503","branch":"refs/heads/feature/media-blocks","head":"b2827484c5fddb4e2c94744ef0ca44587da90375","baseRef":"refs/heads/main","baseCommit":"c5d34ed5f1a895c5896e6950655418085ef75a35","sourceTree":"e313d22991d3cdd8e7a5fba95e9eb1a673033569","absentOptional":[]} -->

## Findings

### 2c/F-03 [P2] closed - Enforce strict null for placeholder media references

**File:** app/Http/Requests/UpdateSiteBlockRequest.php:109
**Found:** 2026-09-24 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The image and text_image contracts require media_asset_id to be exactly null. The new present/nullable/Rule::in([null]) combination also accepts false and the empty string. A read-only probe using the actual request rules and after callbacks confirmed both pass validation, while the update controller persists validated content unchanged. PATCH block requests deliberately bypass ConvertEmptyStringsToNull. These malformed values can therefore be saved and returned outside the declared content schema. This does not currently resolve or expose another site's asset because real media references are not implemented.
**Suggested fix:** Keep the present requirement and enforce strict null with an existing appropriate validation mechanism or a small explicit strict comparison. Extend the focused media tests to reject false and empty string for both image-bearing block types while preserving saved content.
**Resolution:** The update request now strictly compares placeholder asset references with `null`. Focused Pest coverage confirmed JSON `false` and empty-string updates are rejected for both image-bearing block types without changing saved content. Closed by fresh independent review at e241a683650709e263cb09f07fdece28cb58460c: the strict comparison rejects every non-null value; the full Pest suite passed, including the JSON false/empty-string regression cases.

### 2c/F-04 [P2] closed - Keep video preview validation aligned with save validation

**File:** resources/js/pages/Sites/Show.vue:429
**Found:** 2026-09-24 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** URLSearchParams checks literal query keys, while the server uses PHP parse_str, which interprets bracketed keys as arrays. The actual preview function returns a YouTube iframe URL for https://youtube.com/watch?v=abcDEF_1234&list[]=PL123 and for https://youtube.com/watch?v=abcDEF_1234&v[]=bad, but the actual server validator rejects both. After a failed save the draft still renders the iframe, contrary to the spec's no-iframe rule for invalid drafts. The iframe destination remains on the hard-coded provider allowlist, so this is a validation/feedback inconsistency rather than an arbitrary-origin embed vulnerability.
**Suggested fix:** Align the accepted query grammar on the server and client, including bracketed and duplicate parameter handling, and verify the same accepted/rejected examples against both paths. Keep constructing iframe sources from the allowlisted provider and validated ID.
**Resolution:** Server and preview parsers now reject bracketed and duplicate query keys. Focused Pest tests and a probe of the actual preview function confirmed the same ambiguous URLs are rejected without rendering an iframe. Closed by fresh independent review at e241a683650709e263cb09f07fdece28cb58460c: direct execution of the current PHP validator and extracted TypeScript preview function rejected the original bracketed and duplicate query examples on both sides. The original query-key defect is repaired. A distinct empty-query-segment discrepancy is recorded as F-05.

### 2c/F-05 [P2] closed - Align empty video query segments between preview and save

**File:** resources/js/pages/Sites/Show.vue:425
**Found:** 2026-09-24 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The client iterates URLSearchParams, which drops empty query segments, while UpdateSiteBlockRequest::parseVideoQuery rejects them. Executing the current preview function and PHP validator confirmed that `https://youtube.com/watch?v=abcDEF_1234&`, `https://youtube.com/watch?&v=abcDEF_1234`, and `https://youtu.be/abcDEF_1234?si=share&&feature=test` produce an iframe URL in the editor but fail server validation. The retained invalid draft therefore continues to preview after a failed save, contrary to the no-iframe contract. Embed destinations remain on the provider allowlist; this is a validation-feedback inconsistency.
**Suggested fix:** Check the raw query for empty ampersand-separated segments before iterating URLSearchParams, matching the existing server parser. Add these examples to the focused server coverage and compare both parsers again. No new dependency or abstraction is needed.
**Resolution:** The preview now rejects any non-empty raw query containing an empty ampersand-separated segment before URLSearchParams normalization. Focused Pest coverage rejects the trailing, leading, and repeated separator examples without changing saved content, and a probe of the actual preview helper confirms it suppresses those iframes while preserving valid YouTube and Vimeo links. Closed by fresh independent review at 52cb94c3f1af607bffa2cf536748e85aaeb71f8e: executing the extracted current TypeScript preview helper and actual PHP validator rejected the leading, trailing, and repeated empty query segments on both sides, while valid YouTube and Vimeo links remained accepted. The full PHP suite passed (98 tests, 619 assertions); the committed browser bundle contains the same empty-segment guard. A distinct encoded-host discrepancy is recorded as F-06.

### 2c/F-06 [P2] closed - Reject normalized encoded video hosts in the preview

**File:** resources/js/pages/Sites/Show.vue:410
**Found:** 2026-09-24 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** `new URL` decodes percent-encoded hostname characters before the preview compares the hostname with its allowlist, while PHP `parse_url` preserves those characters and rejects the hostname. A read-only probe executing the actual preview helper and PHP validator confirmed that `https://%79outube.com/watch?v=abcDEF_1234` produces `https://www.youtube-nocookie.com/embed/abcDEF_1234` in the editor but is rejected on save. The invalid retained draft therefore renders an iframe despite the no-preview-for-invalid-URLs contract. The generated iframe remains on the hard-coded provider allowlist; this is a validation-feedback inconsistency.
**Suggested fix:** Validate the raw hostname against the same exact host grammar as the server before accepting the normalized browser URL. Preserve case-insensitive supported hosts and the existing HTTPS/port rules. Add the encoded-host example to the focused server cases and compare it against the actual preview helper without adding a dependency.
**Resolution:** The preview now extracts the raw hostname, lowercases it without decoding, and requires it to match the parsed browser hostname before provider validation. Focused Pest coverage rejects the percent-encoded host and preserves uppercase `YOUTUBE.COM:443`. A probe of the actual preview helper confirms the encoded host is rejected while uppercase supported hosts and HTTPS port 443 still preview. Closed by fresh independent review at 3a7eb3aad8827a15fd8e98f0ce9e0d9a4566b4f1: executing the actual TypeScript preview helper and PHP validator rejects the encoded hostname and preserves uppercase supported hosts with port 443. The committed Show bundle includes the raw-host comparison. The full Pest suite passed (98 tests, 625 assertions). The encoded-host defect is repaired; the distinct remaining normalization cases are recorded as F-07.

### 2c/F-07 [P2] closed - Reject empty user info and control normalization before video preview

**File:** resources/js/pages/Sites/Show.vue:411
**Found:** 2026-09-24 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The raw authority extraction discards everything through `@`, and the credential check only rejects non-empty parsed username/password values. Executing the actual preview helper and PHP validator confirms that `https://@youtube.com/watch?v=abcDEF_1234` and `https://:@youtube.com/watch?v=abcDEF_1234` return a preview iframe URL but fail server validation because PHP preserves the empty user-info component. Browser parsing also removes an interior ASCII tab in a query video ID: `https://youtube.com/watch?v=abc\tDEF_1234` (where `\t` represents an actual tab) previews while the server rejects it. These retained invalid drafts can show an iframe despite the spec's no-iframe requirement. The destination remains on the fixed provider allowlist; this is a validation-feedback inconsistency, not an arbitrary-origin embed issue.
**Suggested fix:** Reject any raw authority containing `@`, including empty user info, and reject interior ASCII URL control characters before browser normalization. Keep both validators aligned on those control characters, and exercise these cases alongside the existing supported and rejected URL examples against the actual preview helper and server validation. Preserve surrounding-whitespace trimming and supported HTTPS hosts/port 443. No dependency is needed.
**Resolution:** The preview now rejects any raw authority containing `@` and checks for interior ASCII control code points before calling `new URL`. Focused Pest coverage rejects both empty user-info forms and an interior tab; the actual extracted preview helper rejects all three while continuing to accept an uppercase supported host with HTTPS port 443 and a URL with surrounding whitespace. `npm run types:check`, the focused Vue check, and `npm run build` passed. Closed by fresh independent review at fbffa90349e009c860bfd730df9ef7ad46fb77f1: direct execution of the current TypeScript helper rejects both empty user-info forms and the original interior-tab example; the actual PHP request validator also rejects those examples. Supported uppercase hosts, HTTPS port 443, and surrounding-whitespace examples still preview. The committed bundle contains both guards. A distinct missing server control-character guard is recorded as F-08.

### 2c/F-08 [P2] closed - Reject interior control characters before PHP URL parsing

**File:** app/Http/Requests/UpdateSiteBlockRequest.php:152
**Found:** 2026-09-24 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The verified Step 6 contract requires the server and preview to reject interior ASCII controls, but only the preview has an explicit guard. A read-only probe executing the actual request preparation, rules, and after callbacks accepts `https://youtu.be/abc\tEF_1234`, `https://youtube.com/watch?v=abcDEF_1234&x=a\tb`, and `https://youtube.com/watch?v=abcDEF_1234#x\ty`, where each `\t` denotes an actual tab. PHP parse_url replaces the tab with an underscore, which can form a valid short-link ID or disappear into an unchecked value/fragment. The controller persists the original validated URL, while the actual preview helper returns null for all three. Thus an owner can receive a successful save for a malformed URL that cannot preview. This is a validation/data-contract inconsistency; the allowlisted iframe destinations and ownership boundary remain intact. The existing regression inserts a tab into an already 11-character ID, so its resulting 12-character value fails incidentally and does not cover these accepted cases.
**Suggested fix:** Reject interior ASCII code points 0x00 through 0x1f and 0x7f on the trimmed source string before parse_url, matching the existing preview guard. Add focused cases for a control replacing an ID character and controls in ancillary query/fragment text, asserting validation errors and unchanged stored content. Preserve surrounding-whitespace trimming and supported URLs.
**Resolution:** `isSupportedVideoUrl` now trims the URL and rejects ASCII control code points before calling `parse_url`. Regression cases cover a tab that would normalize into a valid short-link ID, plus tabs in query and fragment text; each request receives a validation error and the saved URL remains unchanged. The focused MediaBlockTest passed (5 tests, 135 assertions), as did Pint. Closed by fresh independent review at b2827484c5fddb4e2c94744ef0ca44587da90375: the actual PHP URL validator and extracted TypeScript preview helper agreed on 111 cases, including all 33 ASCII control code points in path, query, and fragment positions. The complete Pest suite passed (98 tests, 643 assertions), including request-level rejection and unchanged-content assertions for the repaired cases. The guard precedes parse_url and preserves supported URLs and surrounding ASCII whitespace. No new defect was identified in this repair.

## Independent review

# Independent Review

**Status:** passed
**Target commit:** b2827484c5fddb4e2c94744ef0ca44587da90375
**Base commit:** c5d34ed5f1a895c5896e6950655418085ef75a35
**Base ref:** refs/heads/main
**Spec hash:** 15be199997be5cf5c4c50c49f117188b8273d05b91f2655f65ab715a1beba503
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-24T14:23:08Z
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-24T14:26:12.438961Z
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Commands

- `git status --short`, `git rev-parse HEAD`, `git merge-base refs/heads/main HEAD`, and `shasum -a 256 blueprint/context/current-feature.md`: passed before and after review; exact target, merge base, and raw spec hash matched; only the two permitted evidence paths may differ.
- `git diff refs/heads/main...HEAD` with bounded source, test, and generated-route reads, plus `git diff --check refs/heads/main...HEAD`: passed.
- `vendor/bin/pest`: passed, 98 tests and 643 assertions.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/phpstan analyse --no-progress`: unavailable because the sandbox denied its local parallel-worker socket (EPERM).
- `vendor/bin/phpstan analyse --no-progress --debug`: passed, zero errors; completed without the denied parallel socket.
- `npm run types:check`: passed.
- `npx vp check resources/js/pages/Sites/Show.vue`: passed formatting and lint with no warnings.
- Read-only `node --input-type=module` heredoc using installed TypeScript and `php -r`: passed all 111 cases against the extracted actual preview helper and actual PHP URL validator. Checked four supported forms, eight malformed-query/authority regressions, and all 33 ASCII control code points in three interior locations.
- Read-only Node build-manifest inspection: passed; all referenced files/imports exist and the committed Show bundle contains media placeholders, allowlisted embeds, and control guards.

## Evidence

- Reviewed the entire application delta from c5d34ed5f1a895c5896e6950655418085ef75a35 through b2827484c5fddb4e2c94744ef0ca44587da90375 against the verified media-blocks spec. Read the controller, both changed Form Requests, complete Vue additions and affected draft/save/select/reorder/remove callers, and MediaBlockTest. Followed SiteController, SiteBlock, OrderSiteBlocksRequest, routes, input middleware, test configuration, and existing editor/ordering tests.
- Quality: new types reuse existing storage, transaction, ordering, and draft patterns; exact content shapes and strict null media references agree with the spec. No source dependency, route, migration, or service additions. Generated Wayfinder deltas only update controller line references.
- Security: authenticated verified routes and site-owned block lookups remain intact. Unknown content fields and non-null assets are rejected. Vue interpolates text and constructs iframe sources solely from fixed provider origins and validated identifiers. New regression tests assert rejection preserves persisted content.
- Performance: additions introduce no database queries or external server requests; lazy iframe loading is present. Preview parsing is linear in URL length. No confirmed new performance defect was found; no runtime profiling is claimed.
- Tests: read all new tests and affected existing ownership, malformed-input, ordering, and persistence contracts; the full suite passed. No skipped, focused, or placeholder tests were found in the changed test file. Source review confirms draft retention, associated URL error, focus callback, and accessible iframe title.
- Generated build changes were checked for manifest integrity and relevant media behavior; minified vendor internals were excluded from line-by-line review. Dependencies, caches, and unrelated application areas were excluded. Reviewed project standards for Laravel validation/ownership, repository-native Vue patterns, escaped output, accessible inputs, and proportional implementation.

## Findings

- No new findings across quality, security, performance, and tests.
- F-08 [P2]: closed this pass after reviewing the repaired source and executing the server/client control-character cases plus request-level Pest regressions.
- F-03 through F-07 [P2]: remain closed; the strict-null regression tests and query/authority preview probes reconfirm their repairs in the reviewed full delta.
- F-02 [P2]: remains open from earlier work; the unrelated workspace contrast token was not changed or re-reviewed in this pass. No P0/P1 finding is open or fixed.

## Remaining risk

- The normal `vendor/bin/phpstan analyse --no-progress` parallel execution was unavailable due to sandbox socket restrictions; the serial `--debug` run passed the analysis.
- No live browser, keyboard, responsive-layout, provider playback, or network-failure walkthrough was performed in this reviewer context. Browser automation is not configured, and Check was not required. Source and helper execution do not prove those browser interactions.
- `npm run build` was not rerun because it regenerates committed artifacts outside the reviewer's evidence-only write scope. The committed manifest and relevant bundle were inspected, and TypeScript/Vue checks passed; this is not a fresh production-build result.
- No dedicated security scanner or performance profiler is configured; no current dependency-vulnerability or load-testing claim is made.
- The existing nonblocking F-02 contrast finding remains open.

## Completion checks

- `npm run build`: passed after review; the build completed and tracked generated output remained unchanged.
- `npm run types:check`: passed.
- `node_modules/.bin/vp check resources/js/pages/Sites/Show.vue`: passed formatting and lint with no warnings.
- `vendor/bin/pint --test`: passed.
- `vendor/bin/phpstan analyse --no-progress --debug`: passed with zero errors.
- `php artisan test`: passed, 98 tests and 643 assertions.
- `composer test`: its parallel Pint worker could not bind a loopback socket in the sandbox (EPERM); the serial Pint, PHPStan, and Pest components above passed.
- `composer ci:check`: stopped at repository-wide Vite+ formatting checks reporting 91 paths, including unrelated baseline docs and generated helpers. The feature Vue file passed its focused check; no broad auto-format was applied.
