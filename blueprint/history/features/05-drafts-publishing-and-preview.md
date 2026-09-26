# Feature: Drafts, publishing, and preview

**From build-plan:** feature 5
**Build attempt:** 1
**Branch:** feature/drafts-publishing-and-preview
**Status:** verified
**Archive:** blueprint/history/features/05-drafts-publishing-and-preview.md

## Goal

Let owners explicitly publish a stable, public Blade page at `/s/{slug}` while subsequent edits stay private drafts. Include page title, description, and a social preview image. Publication does not require a subscription.

## In scope

- Owner-selected platform address, draft metadata, and social-image upload and removal.
- Atomic publication of saved site presentation, ordered blocks, metadata, and media descriptions.
- Public Blade rendering of all existing block types and all three themes, with shared styling and faithful editor preview.
- A shareable published-page link, publication status and timestamp, and clear save-before-publish feedback.
- Non-indexing instructions on public platform pages and public delivery restricted to currently published images.

## Out of scope

- Subscriptions, billing, custom hostnames, SSL provisioning, multi-page sites, deployment, unpublishing, publication history, scheduled publishing, and address changes after first publication.
- A separate public draft-preview URL, new block types, image library, object cleanup, or browser-test infrastructure.

## Build loop

Use the configured per-step review cadence: implement and verify one step, present its evidence, and wait for approval before the next. Checkpoint commits are optional and require permission. `/complete` creates the final feature commit. Run the configured independent-review gate for this work because it adds public access to previously private site content and media.

## Build steps

- [x] 1. Add draft publishing settings and social-image controls. Add migrations, model casts, owner-scoped validation, editor fields, and upload/removal using existing private storage behavior. Confirm current block, link, video, theme, and image validation/rendering contracts before extending them. **Done when:** owners can save their address and metadata and upload or remove a social image; other users cannot read or change them; existing sites migrate without becoming published; focused Pest coverage and frontend checks pass.
- [x] 2. Implement atomic snapshot publication and editor controls. Publish only persisted draft data, serialize all public content including media descriptions, and coordinate publication with all relevant draft mutations through the existing site-row transaction lock pattern. **Done when:** initial publication fixes the address; later edits leave the snapshot unchanged until another explicit publication; failure preserves the prior snapshot and timestamp; pending edits and requests prevent the editor Publish action; focused ownership, snapshot, validation, and failure tests pass.
- [x] 3. Serve the published Blade page and its referenced images. Add public routes, complete block rendering, metadata and non-indexing directives, and share theme styles with the editor. **Done when:** signed-out visitors can render every existing block and theme from the snapshot, follow section/contact/external links and valid video embeds, and see published images; unknown/unpublished sites and unpublished or cross-site media return 404; draft changes do not alter public HTML or images; focused Pest rendering and media tests plus `npm run build` pass.
- [x] 4. Verify the integrated editor and public experience. Complete accessible status, errors, saved-draft differences, and share-link behavior. **Done when:** `composer ci:check` and `npm run build` pass; an available browser or manual walkthrough covers save, publish, public viewing while signed out, draft changes, and republish at desktop and mobile sizes; actual UI evidence and any unavailable checks are recorded honestly; required independent review is completed before completion.
- [x] 5. Repair independent-review blockers F-03 and F-04. Convert a slug uniqueness race into a `slug` field error, and prevent appearance edits during save from being lost or treated as saved. **Done when:** a deterministic late-collision regression test confirms the field error, appearance fields cannot change while their save is pending, focused checks pass, and both findings are marked fixed pending independent closure.

## Files / areas

- `app/Models/Site.php`, `app/Models/MediaAsset.php`, and new migrations under `database/migrations/`.
- `app/Http/Controllers/SiteController.php`, `SiteBlockController.php`, and `SiteMediaController.php`; focused new publication and public-page controllers if needed.
- Existing request classes under `app/Http/Requests/` and focused requests for publishing settings and publication.
- `routes/web.php`, `resources/js/pages/Sites/Show.vue`, generated Wayfinder helpers, and existing editor types/styles where defined.
- New published-site Blade templates under `resources/views/`, shared site styling under `resources/css/`, and Vite asset configuration only as needed for public rendering.
- Existing site, appearance, block, and media tests under `tests/Feature/`, plus focused publication and public rendering tests.

## Data / contracts

- The user approved owner-selected `/s/church-name` addresses, fixed after first publication, and requiring saved changes before Publish. Resolve the actual origin through Laravel URL configuration; do not hard-code a production origin into local development.
- Add nullable unique `slug`, nullable draft `seo_title` and `seo_description`, nullable same-site `social_image_id`, nullable JSON `published_snapshot`, and nullable `published_at`. Existing sites start with no publication. No backfill may publish draft content.
- Proposed input contract for this spec: slug is 1-100 lowercase ASCII letters/digits separated by single hyphens, with no leading/trailing hyphen. Trim and lowercase before validation, reject unsupported characters rather than silently transliterating, and enforce uniqueness in the database with a field error on collision. Owners choose and save it before Publish; once `published_at` exists, reject changes server-side. Unset draft addresses are allowed, but Publish requires one.
- Proposed metadata contract: optional trimmed title up to 255 characters, optional trimmed description up to 2,000 characters; blanks persist as null. Public title falls back to the snapshotted church name; omit absent description and social-image tags. These are validation limits, not claims about search-engine display limits. Render HTML title, description, and Open Graph title/description/image and URL using escaped values and absolute public URLs.
- Reuse the existing image upload validation, MIME restrictions, size limit, alt-text rules, private IONOS storage, unique immutable object keys, and failed-upload cleanup. Social-image removal clears its draft reference without deleting an object needed by a publication. Do not accept arbitrary storage keys, external image URLs, or foreign media IDs from the client.
- The snapshot is versioned JSON (`version: 1`), containing the saved church name, theme key, footer, logo, ordered blocks with stable IDs/type/position/content, resolved metadata, and referenced media descriptors (asset ID, immutable storage key, MIME type, and copied alt text). Preserve current header/navigation behavior and derive navigation from snapshotted blocks. Do not add a new header editor solely to match the overview's conceptual data model.
- Publication takes the authenticated owner's site from their relationship, locks that site row in a transaction, reads saved site/blocks/media, verifies same-site references, and replaces snapshot and publication timestamp together. Update relevant settings, logo-clear and alt-text mutation paths to use the same site-lock order as block mutations and uploads so a snapshot cannot combine partial writes. No uploaded objects are rewritten during publication. A failed publication leaves the previous public version intact.
- Publish is an authenticated, verified, CSRF-protected POST under the existing numeric site editor route. It accepts no client-supplied snapshot. Existing Inertia redirect and field-error patterns apply. Foreign-site access is 404; guests use existing authentication behavior. Disable duplicate submission while pending; no separate idempotency service is needed for replacing the single current snapshot.
- The editor labels unpublished sites and displays the last publication time and link after success. Compare public-relevant saved content with the snapshot to identify unpublished changes rather than relying only on `updated_at`. Track local dirty fields and pending mutations, including uploads, and require Save before Publish; do not silently discard local edits or auto-save them. Empty pages remain publishable using the existing site shell; no new content-completeness requirements are introduced.
- `GET /s/{slug}` renders only the snapshot with no authentication requirement. Unknown or unpublished sites return 404. `GET /s/{slug}/media/{mediaAsset}` serves only descriptors present in that site's current published snapshot; other IDs return 404 even if an asset belongs to the draft site. Storage paths and unpublished metadata are not returned in HTML. Preserve MIME and `nosniff` headers and use `no-store` responses initially to avoid stale HTML or media after republishing. The existing owner-only media route stays protected.
- Copying alt text and retaining immutable image keys makes later image replacement, reference removal, and alt edits invisible publicly until republish. A missing storage object must fail cleanly without revealing internal paths. Do not fall back to a current draft asset.
- Public pages emit a robots `noindex, nofollow` meta tag and `X-Robots-Tag` header; public image responses also emit the header. This is a public link, not confidential access. Do not block crawler access with robots.txt in a way that prevents reading the noindex instruction.
- Escape user text in Blade and Vue; never render arbitrary HTML. Preserve existing validated external/section links, stable section IDs, contact URL validation, and allowlisted YouTube/Vimeo embeds. Reuse shared theme tokens and styles rather than implementing divergent theme definitions. Public rendering must not require Inertia or editor authentication to initialize.
- Preserve responsive layouts, semantic headings, keyboard navigation, image alt text, and link accessibility. Settings need visible labels, associated field errors, announced status/error feedback, focus on invalid controls, and clearing stale feedback when retrying. During requests show progress; expected validation failures preserve edits; unexpected failures show a retryable message without raw exception details or a false success state.

## Testing

- Baseline `composer ci:check` passed: frontend format/lint and typecheck, PHP format/static analysis, and 114 Pest tests with 800 assertions. The first sandboxed attempt failed because Pint could not open its worker socket; the authorized rerun outside the sandbox passed.
- Cover migration defaults, slug normalization/uniqueness/immutability, metadata boundaries, and same-site social-image upload/reference validation.
- Test authenticated owner publication, denied cross-owner and guest mutation, atomic failure preservation, and snapshot stability after block changes/reordering/deletion, theme/name/footer changes, logo/social/block image replacement or removal, and alt-text edits. Confirm republish updates the existing URL. Use fake storage; external IONOS availability is not required for deterministic tests.
- Test public rendering without a session across block types/themes, escaping hostile text, safe links/video URLs, correct metadata/noindex headers, empty sites, absent optional media, missing objects, and no draft/private fields or storage keys in HTML. Test public media allowlisting, unpublished and foreign asset denial, and continued access to snapshotted images after draft removal.
- Check transaction/locking behavior at the repository seam; SQLite tests alone do not prove MySQL concurrency. Document the actual evidence and any production-database concurrency check still needed.
- After implementation run `composer ci:check` and `npm run build`. Browser automation is not configured; use available live evidence or provide an explicit manual walkthrough without claiming it was executed.

## Notes for the AI

- Spec critique tightened media visibility, copied alt text, coordinated snapshot locking, and unsaved-edit handling so publication cannot expose drafts or drift after editing.
- Keep this one feature scoped to publication of the existing single-page model. Reuse Laravel, existing requests, storage and UI patterns; add no packages or generic publishing framework.
- Feature 5 has no prior build record. Build attempt 1, the archive path, and configured branch were checked: archive absent with ordinary directory parents, no prior archive Git history, valid branch name, and no conflicting local ref.
- The user approved the spec before implementation. Step 1 is implemented on `feature/drafts-publishing-and-preview`; no commit or deployment was created.
- Step 1 evidence: 12 focused Pest tests passed (80 assertions); `npm run check`, `npm run types:check`, `composer lint:check`, and `composer types:check` passed. Sandbox socket restrictions required PHP lint and static-analysis commands to run outside the sandbox. Step review cadence is every step; wait for approval before step 2.
- Step 2 evidence: 27 focused Pest tests passed (222 assertions); `npm run check`, `npm run types:check`, `composer lint:check`, and `composer types:check` passed. The tests cover atomic snapshot fields and media references, missing slug, owner scope, ignored client snapshot input, draft isolation, and failure preservation. The editor disables Publish while local edits or any write request is pending. SQLite feature tests verify transaction rollback but do not prove MySQL row-lock concurrency. Step review cadence is every step; wait for approval before step 3.

- Step 3 evidence: 25 focused Pest tests passed (381 assertions), including signed-out rendering and media access; `npm run build`, `npm run check`, `npm run types:check`, `composer lint:check`, and `composer types:check` passed. `git diff --check` passed. Public pages render all current blocks and themes from the publication snapshot; public media is limited to published descriptors. Vite build-generated artifacts were restored after verification because the feature does not use generated route helpers. SQLite tests do not prove MySQL row-lock concurrency. Step review cadence is every step; wait for approval before step 4.

- Step 4 evidence: the editor now exposes the absolute published-page URL after publication; existing status states identify unpublished pages, saved draft differences, and local unsaved edits, and Publish remains disabled during local edits or writes. Removed strict rejection of unrelated settings input so the existing owner rename flow continues to ignore an attempted `user_id` change. `composer ci:check` passed (138 Pest tests, 1,027 assertions, frontend checks, Pint, and PHPStan); `npm run build` passed with the existing optional `fontaine` warning. `git diff --check` passed. Vite/Wayfinder build outputs were restored after build. No browser automation or live authenticated browser session was available, so no desktop/mobile walkthrough was executed; the final handoff will provide the exact manual path and expected results. SQLite does not prove MySQL row-lock concurrency. Independent review remains required before `/complete`.

- Step 5 evidence: F-03 now maps a late database slug collision to a field validation error after transaction rollback; the focused regression test and related publishing tests passed (14 tests, 96 assertions). F-04 is addressed by disabling rename input during rename and appearance fields while the appearance save is pending, so later keystrokes cannot be discarded by the save success handler. `npm run check`, `npm run types:check`, focused Pint, and `git diff --check` passed. Independent review F-03/F-04 are marked fixed pending fresh closure; existing F-02 remains open P2. `composer ci:check` passed after the repairs (139 Pest tests, 1,031 assertions, frontend checks, Pint, and PHPStan); `npm run build` passed with the existing optional `fontaine` warning; `git diff --check` passed. Generated Vite/Wayfinder artifacts were restored. The independent receipt remains `changes-requested` for target `67c2eff1`; F-03 and F-04 are fixed and require fresh independent closure on the next checkpoint.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":16776,"specSha256":"b32d3fa932bfb379f05a704b6543f3c5bce545c314873f09be1b8c5674a82784","branch":"refs/heads/feature/drafts-publishing-and-preview","head":"b323c32dd21be2fd22fa6ef6049a9de34e987007","baseRef":"refs/heads/main","baseCommit":"2e8976394108bf9a8471fbb2499aa5ae715a96a3","sourceTree":"4be714072602bd7c4e872d344377e9f8bc7e62b4","absentOptional":[]} -->

## Findings

### 5/F-03 [P1] closed - Return a slug field error for a concurrent address claim

**File:** app/Http/Controllers/SiteController.php:112
**Found:** 2026-09-25 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The uniqueness validation query runs before the update transaction. Two different sites can both pass validation for the same available slug, and locking their separate site rows does not serialize those competing claims. The database rejects the second update, but the controller does not translate the unique-constraint exception into the slug field error required by the spec. A deterministic in-memory SQLite interleaving passed the first request's uniqueness validation, assigned that slug to another site, then invoked the update with already validated input. It threw `Illuminate\Database\UniqueConstraintViolationException`, which the standard handler renders as a server error. The original site's slug remained intact.
**Suggested fix:** Keep the unique index and catch the slug-specific unique-constraint conflict at the update boundary, returning a `ValidationException` for `slug` while rethrowing unrelated database errors. Add a regression test for a collision after validation.
**Resolution:** Caught the unique-constraint exception after the update transaction rolls back and translated it to a `slug` validation error when another site owns the requested address. Added a regression test that supplies already-validated settings after a competing claim. Focused Pest tests passed. Closed by fresh independent review at b323c32dd21be2fd22fa6ef6049a9de34e987007: the controller catches the unique constraint after rollback, checks that another site owns the requested slug, returns a slug ValidationException, and rethrows unrelated failures. The deterministic collision regression passes in the complete 139-test suite; no new defect was found in the repair.

### 5/F-04 [P1] closed - Keep edits made during Save marked as unsaved before Publish

**File:** resources/js/pages/Sites/Show.vue:1376
**Found:** 2026-09-25 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** Appearance fields remain editable during the save request. If an owner submits title A and types title B before the response, the success callback calls `appearanceForm.defaults()` on the current B values, although the server saved A. The new Publish gate relies on `appearanceForm.isDirty`, so it becomes enabled and publishes A while the form displays B without a save-before-publish warning. This violates the spec's local-unsaved-edit requirement. A read-only reproduction with the installed Inertia `useForm` and Vue `nextTick` captured the submitted title, changed the visible title before running the same success normalization/defaults operation, and confirmed distinct submitted/visible values with `isDirty: false`. This was a library-seam reproduction, not a browser walkthrough.
**Suggested fix:** Disable the appearance inputs throughout the save request, or establish defaults from submitted/server-confirmed values while preserving subsequent edits as dirty. Do not use current mutable form values as the saved baseline. Verify the delayed-response sequence before publishing.
**Resolution:** Disabled the site-name input while rename is processing and all appearance fields while appearance settings are processing, preventing an in-flight save from discarding newer typing or marking it clean. Frontend checks and typecheck passed. Closed by fresh independent review at b323c32dd21be2fd22fa6ef6049a9de34e987007: source inspection and a read-only Vue template AST check confirm native disabled bindings for name, theme, footer, slug, title, and description throughout their respective saves. This prevents the reported in-flight typing before the success handler resets defaults. Frontend checks pass; no browser delayed-response walkthrough was performed.

## Independent review

**Status:** passed
**Target commit:** b323c32dd21be2fd22fa6ef6049a9de34e987007
**Base commit:** 2e8976394108bf9a8471fbb2499aa5ae715a96a3
**Base ref:** main
**Spec hash:** b32d3fa932bfb379f05a704b6543f3c5bce545c314873f09be1b8c5674a82784
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-25T19:45:07Z
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-25T19:48:44.154345Z
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Commands

- `git status --short --untracked-files=all`, `git rev-parse HEAD`, `git merge-base main HEAD`, and `shasum -a 256 blueprint/context/current-feature.md`: passed; target, merge base, hash, and allowed evidence-only changes match the request.
- `git diff --check main...HEAD`: passed.
- `npm run check` and `npm run types:check`: passed.
- `php artisan test`: passed, 139 tests and 1,031 assertions.
- `composer lint:check`: unavailable in the sandbox; parallel workers cannot bind their local TCP socket (EPERM).
- `vendor/bin/pint --test`: passed as the serial formatting-check alternative.
- `composer types:check`: passed, zero PHPStan errors.
- Read-only `node --input-type=module` Vue template AST inspection and contrast calculation: passed; all six affected form fields have processing-based native disabled bindings; existing muted-text contrast remains 4.16:1, 3.86:1, and 3.97:1.

## Evidence

- Reviewed the complete 2e8976394108bf9a8471fbb2499aa5ae715a96a3..b323c32dd21be2fd22fa6ef6049a9de34e987007 delta across all four lenses, excluding review/findings evidence from the code scope. Reviewed the snapshot action, all changed controllers/requests/model/migration/routes, VideoEmbedUrl, the Vue editor changes, the public Blade view, and every changed test. Followed adjacent block/media mutation controllers, upload and alt-text requests, shared CSS, and test/build configuration where needed. Dependencies and generated build/route assets were excluded from source review.
- Ownership stays at authenticated relationship queries; public HTML and media read only the published snapshot. Blade escapes user text, URLs use constrained schemes/providers, and image delivery checks the snapshot allowlist, site key prefix, and MIME type. Site-row locks coordinate publication with the relevant draft mutation paths. No confirmed new security or performance defect was found.
- The late-slug-collision regression passes, and the repaired error boundary preserves the original slug while reporting the collision on the slug field. Native input disabling closes the reported appearance-save race.
- Current tests cover initial snapshots, atomic write failure, draft isolation, owner boundaries, all block types/themes, escaping, safe embeds, noindex headers, and private/public media separation. No focused, skipped, or placeholder tests were found in the changed test files; the full run reports all 139 tests passed.
- Checked the verified spec, project ownership/publication contracts, local validation and error-handling standards, proportional implementation, accessibility bindings, and shared theme tokens.

## Findings

- F-03 [P1] closed: concurrent slug claim now returns a field error after rollback.
- F-04 [P1] closed: processing disables the affected editable fields before success establishes defaults.
- F-02 [P2] open: existing muted workspace text contrast remains below 4.5:1.
- F-05 [P2] open: add successful republish and public-media allowlist replacement regression coverage.
- No P0 or P1 finding remains open or fixed.

## Remaining risk

- `composer lint:check` could not run with its configured parallel workers because of sandbox socket restrictions; the same formatter passed without parallel workers.
- `npm run build` was not rerun during this evidence-only review because Vite/Wayfinder regenerates tracked files. The spec records a passing post-repair build; this reviewer did not independently reproduce it.
- No live browser or desktop/mobile delayed-response walkthrough was performed. Vue disabled bindings were inspected and parsed, not exercised through a browser. Check was not required by this request.
- SQLite tests do not establish production MySQL row-lock behavior or true simultaneous cross-site slug contention. The deterministic late-collision test exercises the exception boundary.
- F-05 leaves successful republishing and removal of old public-media access without a regression test; no product failure was reproduced. Live IONOS storage and current external dependency vulnerabilities were not checked.
