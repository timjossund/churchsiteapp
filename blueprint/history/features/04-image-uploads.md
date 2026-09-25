# Feature: Image uploads

**From build-plan:** feature 4
**Build attempt:** 1
**Branch:** feature/image-uploads
**Status:** verified

## Goal

Let a site owner upload and use JPEG or PNG images for image blocks, text-and-image blocks, and the site logo. Store originals in the configured IONOS-compatible object bucket as private objects while the site is a draft.

## In scope

- Upload one image at a time from the existing image and text-and-image block controls and assign it to that block.
- Upload, preview, replace, and clear the logo in the existing site shell controls.
- Let the owner add or edit optional alt text for an uploaded image, and render it as escaped text in the image's `alt` attribute. Give a logo without alt text an accessible name based on the site name.
- Show a local preview while a file is being uploaded, clear progress after completion, and retain the prior saved image or logo if validation, storage, or save fails.
- Serve uploaded images to the authenticated owner through same-origin, site-scoped routes so the private editor can display them. Keep bucket objects private and require an authenticated, verified site owner for reads and writes.
- Keep the existing image block and text-and-image block content shape, using `media_asset_id` to reference a same-site asset.
- Provide accessible labels, associated validation messages, announced upload status, and keyboard-operable controls. Preserve entered alt text and current references on failed requests.

## Out of scope

- Public or anonymous image delivery, publishing, stable published image URLs, and visitor access; Feature 5 owns published access.
- Social preview images and SEO fields; Feature 5 owns them.
- Video thumbnails or changes to YouTube/Vimeo embeds.
- A standalone media library, batch upload, image cropping, resizing, transcoding, or automatic deletion of unreferenced assets.
- External image URLs, SVG, GIF, WebP, or other upload formats.

## Build loop

- Implement the steps in order. Guided cadence requires review and approval after every step.
- After an approved, passing step, offer the configured optional checkpoint commit. `/complete` creates the final feature commit.
- Keep block editing, site rename, appearance settings, and draft isolation working throughout.

## Build steps

- [x] **1. Persist media and add private owner-scoped upload and delivery.** Add a `media_assets` table and `MediaAsset` model with a site foreign key, generated storage key, verified MIME type, nullable alt text, and timestamps. Add a nullable `logo_media_asset_id` relation to sites. Install the Flysystem S3 adapter needed by the existing `s3` disk and use its environment-based IONOS-compatible endpoint and credentials; do not add credentials to source. Use `POST /sites/{site}/blocks/{block}/image` for an image-bearing block upload, `POST /sites/{site}/logo` and `DELETE /sites/{site}/logo` for logo upload and clear, `PATCH /sites/{site}/media/{mediaAsset}` for alt text, and `GET /sites/{site}/media/{mediaAsset}` for private inline delivery. Keep all routes authenticated and verified and scope every lookup through the owning site. Validate file contents as JPEG or PNG and enforce the inclusive 5 MB per-file limit on the server. Generate site-scoped object keys on the server; ignore client paths and filenames. Verify every block and media reference belongs to the requested site. Store to the private disk before changing a reference; if persistence fails, remove only the new object and leave the old reference intact. Clearing or replacing a reference must not delete an asset that may be referenced elsewhere. Add focused Pest tests using a fake disk for valid uploads, MIME and size rejection, storage and database failure behavior, owner and cross-site denial, private delivery, alt text, and logo clear/replace. **Done when:** valid owner uploads persist private objects and same-site references, invalid or unauthorized requests cannot alter another site's data, failed uploads preserve the prior reference, and focused PHP tests pass.
- [x] **2. Connect uploads to image block controls.** Replace the existing placeholders for `image` and `text_image` with file selection, progress, preview, replace, and clear controls. Save an upload against its selected block, display the persisted preview through the private owner route, and let the owner edit optional alt text. A failed selection or request must retain the last saved block image and alt text, associate validation feedback with the file or alt-text control, announce status, and focus the first invalid field using the editor's existing pattern. Do not alter block order or unrelated content when uploading. **Done when:** an owner can upload and see or clear an image in either supported block, alt text appears in the preview, failures preserve saved block content, the focused frontend and PHP checks pass, and a manual editor walkthrough confirms the behavior.
- [x] **3. Add logo upload to the existing site shell.** Add file selection, progress, preview, replace, clear, and optional alt-text controls alongside the current site name and appearance controls. Persist the logo reference on the site; show the saved logo in the private shell preview and use the site name as its accessible name when alt text is empty. Keep the name, theme, footer, and unsaved values intact through failed or unrelated Inertia requests. **Done when:** the owner can manage a logo independently of site rename and appearance settings, saved state reloads correctly, the focused PHP tests and frontend checks pass, and a manual editor walkthrough confirms logo states and preserved drafts.
- [x] **4. Run final feature checks.** Run `composer ci:check`, `npm run build`, and `node_modules/.bin/vp check resources/js/pages/Sites/Show.vue`. Manually inspect a draft site with no images, each image block type, logo present and absent, JPEG and PNG upload, replacement and clearing, alt text, rejected format and oversized files, storage failure, owner-only loading, keyboard use, and a narrow layout. **Done when:** the listed checks pass and the manual walkthrough confirms draft images remain available only through the authenticated owner experience and all failed uploads preserve the saved site state.

- [x] **5. Repair F-03 archive integrity.** Restore the five changed completed-feature archives byte-for-byte from the review base and exclude immutable Blueprint history from formatting. **Done when:** all five archive prefixes match their recorded byte lengths and hashes, the files match the base exactly, and the configured formatter accepts the exclusion.
- [x] **6. Repair F-04 interrupted uploads.** Coordinate editor writes during uploads and clean up cancelled block/logo uploads while preserving entered alt text and saved references. Set the existing Composer PHPStan command to use the verified 512 MB limit so final checks can run under Herd. **Done when:** interruption checks confirm temporary previews and upload status are cleared, frontend checks pass, and final feature verification is rerun before a new independent-review checkpoint.

- [x] **7. Repair F-05 image-only text controls.** Hide Heading and Text inputs for image-only blocks while keeping the image clear/undo/save controls and text-and-image editing. **Done when:** template checks confirm the correct controls for each block type, clear/undo/save and text-and-image payload checks pass, and final automated checks pass.

## Files / areas

- `app/Models/Site.php`, a new `app/Models/MediaAsset.php`, and migrations for the media table and nullable site logo reference.
- `app/Http/Controllers/SiteController.php` for site and logo props, new owner-scoped media upload/delivery controller actions and Form Requests, and `routes/web.php` for authenticated, verified site media routes.
- `app/Http/Controllers/SiteBlockController.php` and `app/Http/Requests/UpdateSiteBlockRequest.php` for same-site media assignment and clearing.
- `config/filesystems.php` and `composer.json` / `composer.lock` for the existing S3 disk and its missing Flysystem adapter.
- `resources/js/pages/Sites/Show.vue` for image-block controls, site-logo controls, private previews, and accessible status/error feedback.
- `tests/Feature/MediaBlockTest.php` and focused media-upload feature tests; the focused Vue check for `Sites/Show.vue`.

## Data / contracts

- A media asset belongs to exactly one site and stores `site_id`, a server-generated site-scoped `storage_key`, server-verified `mime_type` (`image/jpeg` or `image/png`), nullable `alt_text`, and timestamps. The database constrains its site relation. Block references are nullable `media_asset_id` values in the existing block content shape; the site logo is a nullable `logo_media_asset_id` reference. All references must point to assets belonging to the same site.
- The block upload route in Step 1 persists the new asset and assigns its ID to the requested block as one saved operation. Clearing a block image uses the existing block update route with `content.media_asset_id: null`; any non-null reference accepted there must identify an asset owned by that same site. The logo upload and clear routes use the shapes listed in Step 1. The private media GET route responds inline with the stored, verified image MIME type and private no-store caching.
- Accept JPEG and PNG image files only, based on server-side file inspection, up to and including 5 MB per file. Reject other content types, files over the limit, missing files, and malformed alt text without changing any saved reference. Enforce the limit for each request on the server; client-side accept filters are guidance only.
- Use the existing configured `s3` disk for IONOS-compatible object storage. Objects remain private. Use unpredictable keys generated by the server under a site-specific prefix; never use the uploaded filename as a key or expose the bucket's public URL.
- Upload and persist the new asset before switching the block or logo reference. If storage or database work fails, clean up the newly written object when possible and preserve the previous reference and image. Replacing or clearing a reference does not delete the old asset or object; asset cleanup and library management are deferred.
- Only the authenticated, verified owner can upload, edit alt text, clear the logo, or read image bytes. Resolve sites through the authenticated user's owned-sites relation first; resolve blocks and assets through that site. Return a not-found response for cross-owner or cross-site identifiers. Image delivery is same-origin and private; no anonymous route, public URL, or public cache is allowed in this feature.
- Store and render alt text as plain text. Image blocks use the saved alt text, including an empty alt value when the image is decorative. Logos use saved alt text when present and otherwise use the site name as their accessible name. Vue must render text normally, never as HTML.
- Upload controls accept one image per request. A cleared image or logo has a null reference. An unsuccessful upload leaves the existing reference and alt text visible; errors remain tied to their controls and status is announced.

## Testing

- Use Pest with a fake private `s3` disk; do not require live IONOS credentials or claim live-bucket verification.
- Cover valid JPEG and PNG content, wrong or spoofed MIME, the 5 MB boundary and an over-limit file, malformed input, storage failure, database failure cleanup, same-site assignment, guest, cross-site and cross-owner denial, private image delivery, alt-text update, logo set/replace/clear, and preservation of prior references after failure.
- Verify upload progress, preview, clear and replace, accessible alt-text behavior, associated errors and announcements, and preserved drafts during same-site Inertia updates in the manual editor walkthrough. The repository has no configured Vue unit or browser test command.
- Final configured checks are `composer ci:check`, `npm run build`, and `node_modules/.bin/vp check resources/js/pages/Sites/Show.vue`. Browser automation is not configured; perform the manual walkthrough in Step 4 and record only observed results.

## Notes for the AI

- The build-plan and Feature 3 archive defer image-block and logo uploads to this feature. Existing media blocks already store a nullable `media_asset_id`; current validation intentionally rejects non-null references until upload support exists.
- The overview defines `MediaAsset` as site-owned, stored in an IONOS bucket, with MIME type and nullable alt text. Feature 5 adds public published access; do not generate a public bucket URL or weaken private delivery to make the draft preview work.
- `config/filesystems.php` already defines an environment-configured `s3` disk, but the S3 Flysystem adapter is not installed. Add that required package and configure the existing disk; do not duplicate credentials, add another storage abstraction, or require credentials in tests.
- Existing site routes use `auth` and `verified` middleware and owner-scoped lookups. Extend that boundary for media and validate site ownership for every block and asset reference.
- The project has no browser test command. Use Pest, the configured frontend checks, and the manual walkthrough; do not install a browser runner.

## Repair verification

- F-03 repaired and its Guided step approved by the user. All five archive byte/hash proofs pass.
- F-04 cancellation and write-coordination runtime probes pass for both logo and block uploads, using the actual component script and installed Vue/useForm. Draft alt text and saved previews are preserved. This is callback-level evidence, not a full browser walkthrough.
- Frontend lint/format and TypeScript checks pass. `npm run build` passes. Build-generated tracked files were returned to their pre-build bytes; new output was preserved under `/private/tmp/church-build-verification-461bu0gk`.
- `php artisan test`: 114 tests and 800 assertions pass. `vendor/bin/phpstan analyse --memory-limit=512M`: passes. PHP formatting passes.
- Final gate passes: the existing Composer PHPStan command now specifies `--memory-limit=512M`. `composer types:check` and the unmodified `composer ci:check` invocation pass, including frontend checks, PHP formatting, PHP analysis, and 114 tests / 800 assertions. No machine-wide PHP settings were changed.
- F-03 and F-04 were closed by independent review of checkpoint `d1f822bc`. That review raised F-05; its repair is now fixed pending a new checkpoint and independent review.

- F-05 repair: image-only blocks exclude Heading/Text inputs; the form remains available for clearing images. Template condition checks cover all nine block types. Actual component script probes confirm image clear/undo/save and text-and-image heading/body payloads. `npm run build`, focused Vue checks, and `composer ci:check` pass (114 tests / 800 assertions). No full browser walkthrough was performed for this repair.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":14997,"specSha256":"d6ceb9dd3cbb32ae570e8575e4f022f3d4251c374dfd9610232455c3c783083d","branch":"refs/heads/feature/image-uploads","head":"f238d80de14570e661144f3f31b9a8cc6f2af956","baseRef":"refs/heads/main","baseCommit":"60ac93da0d1b7d9e3e60d077b5dce2ba4cda74f8","sourceTree":"95b30a6ec821cb2e6bf990507b6ba4d70375a522","absentOptional":[]} -->

## Findings

### 04/F-03 [P1] closed - Restore the exact bytes of completed feature archives

**File:** blueprint/history/features/02b-church-details.md:52
**Found:** 2026-09-25 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** This checkpoint reformats five completed archives, removing one byte from the required two-newline annotation separator in all five and changing the hash-bound spec table in 02b. The installed completion-recovery contract requires extracting the original spec prefix by removing exactly the two inserted newlines, then matching both `specBytes` and `specSha256`, with only uniform LF/CRLF candidates permitted. A read-only reproduction of that contract validates all five archives at main (60ac93da0d1b7d9e3e60d077b5dce2ba4cda74f8) and rejects all five at this target. Consequently, these historical records can no longer support the workflow's exact-byte completion-recovery proof. The other affected archives are 01-site-workspace.md, 02a-editor-foundation.md, 02c-media-blocks.md, and 03-themes-and-site-shell.md.
**Suggested fix:** Restore all five historical archives byte-for-byte from the base and exclude immutable history from general formatting, so subsequent format checks do not require rewriting hash-bound evidence. Preserve their recorded hashes and completion metadata.
**Resolution:** Implement repair restored all five archives byte-for-byte from review base 60ac93da0d1b7d9e3e60d077b5dce2ba4cda74f8 and added `blueprint/history/**` to the existing formatter ignore patterns. Exact-byte comparisons and all five recorded spec byte-length/SHA-256 proofs pass. `node_modules/.bin/vp check vite.config.ts blueprint/history` passes and excludes the archives. Awaiting independent re-review; marked fixed, not closed.

Independent re-review at d1f822bc79a31b63dbea464f52a99ba16f91bf71 on 2026-09-25 closes F-03. All five files equal the base bytes, retain the two-LF annotation separator, and match their recorded `specBytes` and `specSha256`. The formatter exclusion is present and the focused check passes while excluding history. No new archive-integrity defect found.


### 04/F-04 [P1] closed - Clean up uploads interrupted by another editor request

**File:** resources/js/pages/Sites/Show.vue:1326
**Found:** 2026-09-25 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** A logo upload disables only its own logo controls; rename, appearance save, and block upload can still issue another synchronous Inertia request. Conversely, a logo upload can interrupt a block upload. Installed Inertia defaults to synchronous visits and cancels the previous request, invoking `onCancel` and `onFinish`, without invoking upload success/error/network handlers. Both upload handlers omit cancellation cleanup. The saved server image is then masked by a stale local blob preview and the status stays 'Uploading' after processing has ended. A read-only runtime probe of the actual Show.vue script with the installed Vue/useForm implementation confirms that invoking Inertia cancellation/finish callbacks leaves `processing: false`, the blob URL selected, and the upload status unchanged while the saved logo URL remains different. This is reachable by starting a slow logo upload and saving the site name, or starting a block upload and then a logo upload.
**Suggested fix:** Coordinate in-flight editor writes so these actions cannot silently interrupt uploads, and add cancellation cleanup to both upload handlers to revoke the temporary preview, clear progress/status and file input, preserve entered alt text, and restore or reconcile the saved reference. Verify slow-upload interruption alongside existing success/error cases.
**Resolution:** Implement added shared upload/write guards, disabled competing controls during uploads, and added cancellation cleanup for both upload handlers. A runtime probe of the actual component script with installed Vue/useForm confirms logo and block cancellation clear blob previews, file inputs, progress, and upload status while preserving alt-text drafts and saved preview references. It also confirms competing writes and uploads are blocked. Frontend lint/type checks, production build, PHPStan with explicit 512 MB, and 114 Pest tests (800 assertions) pass. The configured aggregate command now passes after the Composer PHPStan command was given an explicit 512 MB limit. No full browser walkthrough was performed. Awaiting independent re-review; marked fixed, not closed.

Independent re-review at d1f822bc79a31b63dbea464f52a99ba16f91bf71 on 2026-09-25 closes F-04. A fresh read-only probe evaluates the actual component script with installed Vue/useForm and intercepted router callbacks. For both upload types, cancellation/finish clears temporary previews, file inputs, progress, and status, preserves the alt-text draft, and restores the saved preview URL. Rename, appearance save, block save, and the other upload do not dispatch competing writes during the upload. This verifies the original repair at callback level; no browser walkthrough is claimed. F-05 is a separate defect in the same component.


### 04/F-05 [P1] closed - Hide unsavable text fields on image-only blocks

**File:** resources/js/pages/Sites/Show.vue:2818
**Found:** 2026-09-25 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** Removing the form's image-type exclusion exposes Heading and Text controls for an image-only block. Both drafts enable Save block, but `saveBlock` sends only `media_asset_id` for that block type (line 828). Its success handler then marks the omitted drafts saved (lines 847-848), clears the dirty state, and displays Block saved. The owner can therefore enter text, receive a successful-save message, and lose that text upon switching blocks or reloading. A fresh runtime probe using the actual component script and installed Vue/useForm confirms a payload containing only the media reference, `contentSaved: true`, and `isContentDirty: false` after success while persisted content contains neither text field.
**Suggested fix:** Exclude image-only blocks from the Heading and Text field conditions while retaining the form and Save block control needed to persist a pending image clear. Keep text-and-image fields working. Verify that image-only blocks expose no unsavable text fields, that clear/undo/save still works, and that text-and-image content persists normally.
**Resolution:** Implement excluded image-only blocks from both Heading/Text conditions while retaining the form and Save block action. Template expression checks cover all nine block types, and actual component script probes confirm image clear/undo/save and text-and-image heading/body payloads. Focused Vue checks, production build, and `composer ci:check` pass, including 114 tests / 800 assertions. No full browser walkthrough was performed. Fixed pending independent re-review.

Independent re-review at f238d80de14570e661144f3f31b9a8cc6f2af956 on 2026-09-25 closes F-05. Fresh evaluation of the actual template ancestor conditions confirms correct Heading/Text visibility for all nine block types. A read-only probe of the actual component script with installed Vue/useForm confirms image clear/undo/save, the null media-reference payload and clean state after success, and text-and-image heading/body payloads. Both upload cancellation paths and competing-write guards also pass. The complete current delta was reviewed across all four lenses; no new blocking defect was found. Focused Vue checks and composer ci:check pass (114 tests / 800 assertions). No full browser walkthrough is claimed.


## Independent review


**Status:** passed
**Target commit:** f238d80de14570e661144f3f31b9a8cc6f2af956
**Base commit:** 60ac93da0d1b7d9e3e60d077b5dce2ba4cda74f8
**Base ref:** main
**Spec hash:** d6ceb9dd3cbb32ae570e8575e4f022f3d4251c374dfd9610232455c3c783083d
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-25T16:27:37.340Z
**Workflow:** regular
**Check required:** no
**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-25T16:32:06.069Z
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Commands

- `git rev-parse HEAD`, `git merge-base main HEAD`, `git status --short`, and exact spec SHA-256 verification: pass before and after review. Only the two permitted evidence files differ from the checkpoint.
- `composer ci:check`: pass after allowing local worker sockets. Frontend formatting/lint, Vue TypeScript, Pint, PHPStan, and all 114 Pest tests / 800 assertions pass. The first sandboxed attempt stopped at Pint with EPERM opening a local socket; the authorized rerun resolved that limitation.
- `node_modules/.bin/vp check resources/js/pages/Sites/Show.vue`: pass.
- Read-only inline Node probe using the actual Show.vue script and installed Vue/useForm: pass for image clear/undo/save, text-and-image payloads, block/logo cancellation cleanup, preserved draft alt text, restored saved previews, and competing-write guards.
- Read-only Vue template AST condition evaluation: pass for Heading/Text visibility across all nine block types.
- Read-only normalized TypeScript/JavaScript AST comparison: 59 changed files are semantically equivalent to the base after ignoring formatting and redundant parentheses; remaining changes are the new media helpers, their exports, and the Vite history exclusion.
- Read-only byte comparison of all six completed feature archives against main: pass.
- `git diff --check main...HEAD`: pass. Focused skipped/only/todo test search: none found in SiteMediaUploadTest.php or MediaBlockTest.php.
- `npm run build`: not rerun because it rewrites tracked generated files; existing builder evidence was read but is not counted as an independently rerun build.

## Evidence

- Reviewed the complete current delta from 60ac93da0d1b7d9e3e60d077b5dce2ba4cda74f8 through f238d80de14570e661144f3f31b9a8cc6f2af956, including the active spec, all changed project-owned application code, migrations, routes, requests, models, Vue editor, tests, dependency manifests, disk/build settings, and workflow/configuration changes. Followed adjacent SiteBlockController and request contracts.
- Generated Wayfinder routes/actions were checked for the new media route contract and existing-file semantic equivalence. Third-party dependency internals, generated Composer autoload metadata, font manifests, minified output, caches, and build output were excluded from hand review; dependency additions were inspected through composer.json/composer.lock. No current vulnerability scan is claimed.
- Server-side review confirms authenticated/verified owner and same-site lookups, inspected JPEG/PNG validation and inclusive 5 MB limit, private unpredictable storage keys, transaction rollback/new-object cleanup, preserved old assets, same-origin private no-store delivery, and plain-text alt attributes. SiteController batches media lookup for referenced assets.
- Project standards checked: Laravel Form Request validation and ownership boundaries, Eloquent transactions and migrations, existing Vue/Inertia/Wayfinder patterns, accessible labels and associated errors/status, proportional scope, and the configured PHP/frontend gates.
- F-05 is closed after fresh review of its file and runtime/template evidence; F-03 archive restoration and F-04 cancellation behavior remain sound in this checkpoint.

## Findings

- No new findings.
- F-05 [P1]: closed in this pass.
- F-02 [P2]: existing muted-text contrast finding remains open and is not waived.
- No P0/P1 finding remains open or fixed.

## Remaining risk

- No full browser walkthrough, keyboard/narrow-layout interaction, or real browser network interruption was performed. Component-script probes exercise actual Vue/useForm callbacks, not DOM rendering or real HTTP uploads. No configured browser test command exists.
- Live IONOS storage and production MySQL were not exercised; Pest uses the configured test database and fake storage.
- Production build was not rerun to preserve the frozen tracked tree. The builder reports a passing build, but this receipt relies on independently passing frontend checks and PHP verification plus code/runtime probes.
- Existing F-02 [P2] contrast issue remains open.
