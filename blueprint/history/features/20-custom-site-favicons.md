# Feature: Custom site favicons

**From build-plan:** feature 20
**Build attempt:** 1
**Status:** verified
**Branch:** feature/custom-site-favicons
**Archive:** blueprint/history/features/20-custom-site-favicons.md

## Goal

Let an owner upload a church favicon in Site Settings and publish it across every public page, including customer domains. Keep the existing default icon when none is selected.

## In scope

- A Favicon section beside the existing Header logo controls, following their styling and upload interaction patterns.
- Upload, image preview, replace, and Remove actions for a square PNG. Recommend 512 by 512 pixels in help text without requiring that exact size.
- Reuse owned media storage, authenticated media previews, and the existing public media transport.
- Site-wide draft selection with explicit Publish: uploading, replacing, or removing does not change the currently published icon.
- An additive nullable site media reference and backward-compatible frozen publication data.
- Published HTML declares the custom PNG favicon when set; otherwise keeps the existing product default with reachable trusted platform URLs on customer domains.
- Cover ownership, validation, upload failures, replacement/removal, legacy snapshots, multi-page publication, customer media transport, and site deletion.

## Out of scope

- SVG, ICO, JPEG, GIF, WebP, remote URL input, cropping, generated icon variants, image conversion, or another dependency.
- Apple touch icons, web manifests, installed-app icons, automatic logo-to-favicon assignment, or changing the platform/dashboard/auth favicon.
- New billing rules, new public root favicon routes, storage garbage collection, or changes to unrelated logo/block/social uploads.
- Worker deployment, remote configuration, push, or publish on an owner's behalf.

## Build loop

Use the configured per-step review (`workflow.stepReview: every`). Stop after each passing step for review. Checkpoint commits are enabled but still need explicit approval. Complete owns the final work commit and merge. Independent review is required for the upload/ownership/publication boundary; reuse the configured automatic fresh-reviewer flow.

## Build steps

- [x] Step 1: Persist and manage the draft favicon. Add a nullable `favicon_media_asset_id` reference on sites and the corresponding model metadata/relationship. Add owner-scoped upload/removal routes and PNG-specific validation, reusing the existing media upload and failure-cleanup mechanism. Clear the draft reference when site deletion is requested. Done when focused Pest tests prove valid upload/replacement/removal, validation and ownership denials, old-asset retention, failed-write safety, and deletion behavior without changing existing upload rules.
- [x] Step 2: Add Site Settings controls and data. Include same-site authenticated favicon preview data in the shared settings payload. Add the upload/replace/Remove controls, a small image preview, help, progress, success and failure states, and integration with existing upload guards. Done when frontend checks/types/build pass, settings payload tests pass, and the owner has a manual path to upload, replace, remove, reload, and recover from a rejected file.
- [x] Step 3: Publish and serve the favicon. Include the selected media in the frozen site snapshot and validated public media list. Render the custom icon on Home and other pages; remove competing default icon declarations when custom is selected. Preserve the existing default when unset and make its links reachable from customer domains. Done when focused publication and customer-transport regressions prove stable live icons during draft edits, changed icons after Publish, removal restoring the default, ownership isolation, legacy compatibility, and GET/HEAD media delivery. Run the combined final gate and fresh independent review before completion.

- [x] Review repair F-07: Send the current middleware asset version in the favicon partial-response and existing calendar CSP Inertia test requests. Keep production versioning and real response assertions intact. Done when focused tests and the combined final gate pass with built assets, followed by a new approved checkpoint and fresh full review.

- [x] Review repair F-08: Match Inertia string version semantics when development middleware returns null by using an empty version header. Exercise both repaired requests under explicit development and built-asset modes. Done when focused and final gates pass, then obtain a new approved checkpoint and fresh full review.

## Files / areas

- `database/migrations/` and `app/Models/Site.php`: nullable owned-media reference, model metadata, relationship.
- `app/Http/Requests/StoreSiteImageRequest.php` and a small favicon-specific request if needed: reuse validation and ownership patterns without broadening existing image types.
- `app/Http/Controllers/SiteMediaController.php`, `routes/web.php`: existing upload/assignment/cleanup path and authenticated upload/removal actions.
- `app/Http/Controllers/SiteController.php`, `resources/js/pages/Sites/Settings.vue`: payload and controls next to Header logo.
- `app/Actions/BuildSitePublicationSnapshot.php`, `app/Http/Controllers/PublishedSiteController.php`, `resources/views/sites/published.blade.php`: frozen media selection, validation, public icon link and default.
- `app/Actions/RequestSiteDeletion.php` and existing deletion/storage cleanup: clearing the draft reference and preserving recoverable media inventory.
- Existing upload, publication, multi-page and customer-domain tests; a focused favicon feature test using Pest and fake storage.
- `workers/domain-proxy/content.test.mjs`: actual forwarding regression for PNG favicon bytes through the existing media path if not already adequately covered.

## Data / contracts

- Each site has nullable `favicon_media_asset_id`, referencing `media_assets`; deleting the referenced media clears the draft reference. No separate favicon table or content block. No change to logo selection. New sites default to null; existing sites retain their current icon.
- Proposed routes: authenticated `POST sites/{site}/favicon` and `DELETE sites/{site}/favicon`, named `sites.favicon.store` and `sites.favicon.destroy`. Resolve the actor from the authenticated session and site through that user's non-deleting sites. Deny cross-owner, cross-site, guest, and offline/deleting-site writes with existing conventions. Clients upload a file, never assign a raw asset ID/storage key/owner/URL.
- Upload field `image`: required file, actual PNG image, positive equal width/height, at most 5120 KiB, matching the existing image byte limit. Check the file on the server even if browser accept/client checks passed. Reject other formats, misleading extensions/MIME, malformed images, non-square files and forbidden extra fields with an associated image error. No client-supplied alt text needed for a favicon; store an empty alt description under the existing media contract.
- Reuse the existing site-prefixed random storage key, trusted MIME metadata and atomic owned-site/media assignment. On expected storage failure preserve the previous selection and return an actionable upload error. Clean up only newly written objects on failure using the current behavior. Unexpected failures are reported through the existing error path. Replacement and Remove retain old objects/assets, since the live snapshot may still reference them; whole-site deletion uses the existing cleanup inventory.
- Draft changes persist as soon as upload/removal succeeds, like the logo; they do not depend on Save site settings. Do not silently discard other unsaved settings when navigating/reloading after media actions. Reuse existing upload-progress/disabled-action/navigation protection patterns; include favicon operations in their busy state.
- Settings show the selected owned image or an unset state describing the default. File label/help describe PNG, square shape, size limit and recommended 512 by 512 dimensions. Show progress and announced success/error feedback; associate errors with the input and focus it on validation failure. Clear errors when selecting another file and release temporary object URLs on replacement/unmount. Do not require an alt-text editor for this decorative preview.
- Public media remains reachable only through the frozen publication. Add optional positive `favicon_media_asset_id` to the site's version-2 snapshot when set; omit it when null so adding this feature does not create false unpublished changes for existing unset sites. Legacy publications lacking the field behave as unset. Follow the current strict snapshot validation and same-site storage/MIME checks; do not substitute draft data into old publications. Malformed present references must never expose another site's media or become arbitrary link URLs.
- Include favicon media in the existing unique media list and publication fingerprint. An uploaded draft-only icon cannot be fetched through public media routes. Upload/replace/remove leaves published HTML and bytes unchanged until Publish; replacing gets a distinct asset ID and URL. Publish applies the current selection to all pages together. Removal then publishes the default. Existing publish failure preserves the previously published snapshot.
- Render one custom `<link rel="icon" type="image/png" href="...">` with an escaped URL generated by the existing published media mapping (`/s/{slug}/media/{id}` on the platform, `https://{hostname}/_media/{id}` on a customer domain). Do not retain default ICO/SVG rel=icon declarations alongside a custom choice, and do not claim `sizes="any"` for an ordinary raster icon.
- When unset, retain the existing product default icon on public pages, with trusted platform URLs for static default files that remain reachable from customer domains. Do not widen Worker routes to arbitrary files or assume customer `/favicon.ico` or `/favicon.svg` exists. The current `_media/{id}` PNG GET/HEAD transport already fits uploaded favicons; verify the full path rather than checking only Laravel origin headers.
- Platform account, auth, homepage, and builder browser tabs keep the product favicon. The settings image preview is the draft preview; do not globally mutate Inertia head icons for one site's setting. Browser favicon caches may delay the visible tab change; do not claim DOM/header tests prove browser refresh behavior.

## Testing

- Planning baseline `composer ci:check` passed on 2026-09-30: 914 Pest tests, 6328 assertions, frontend formatting/lint/types, PHP formatting/static analysis, and production build.
- Focused Pest: migration defaults, square PNG/boundaries, non-square/other-format/spoofed/invalid/oversized input, unknown fields, guest/cross-owner/site and deletion denials, fake-storage failures, existing-selection preservation, replacement retention, Remove, settings preview payload, reload, publication change detection and freeze, legacy unset snapshots, all pages, paid domain media GET/HEAD and isolation, default/custom HTML, and whole-site cleanup.
- Regression: logo, social image and block uploads, publication, snapshot/media isolation and deletion tests. Run existing Node Worker tests when touching their test/implementation surface.
- Final `composer ci:check`; no new test runner or dependency. Independent review must inspect the complete branch and actual customer-media delivery path.
- Manual path: Site Settings -> Favicon -> upload square PNG -> check preview/reload -> open page editor and Publish -> inspect Home and another page on shareable/custom addresses -> replace/remove and confirm the live choice changes only after Publish. Check rejected files, progress/errors, keyboard access and small-screen layout when browser evidence is available.
- Browser tests are not configured. Do not start a dev server or claim browser/tab-icon behavior was observed when only automated output checks ran.

## Notes for the AI

- Tim approved the feature-20 roadmap addition and square-PNG upload/preview/replacement/removal/default/Publish scope on 2026-09-30. Tim authorized implementation by invoking Implement.
- Build attempt 1, archive path and branch availability were checked against local refs, existing archives and available Git/reflog history before freezing this spec.
- Keep the existing image pipeline and its failure behavior. Limit any refactor to the small shared seam needed for a validated favicon upload; no generic upload service or icon generation pipeline.
- Critique tightened draft/live media retention, legacy-null fingerprint compatibility, reachable default-icon URLs on custom domains, unchanged platform tabs, and upload error/unsaved-settings handling.
- No new unresolved product decision. PNG-only support and the existing byte limit are explicit reviewable contracts; no deployment or Git action is included.

## Step 1 evidence

- Added the nullable favicon media foreign key and relationship, PNG/square/5120-KiB validation, owner-scoped upload/removal, and deletion-time reference clearing. Reused the existing upload/transaction/failure-cleanup path. Old assets remain available to frozen publications; existing logo/social/block request rules are unchanged.
- Focused tests passed: `php artisan test --compact tests/Feature/SiteFaviconTest.php tests/Feature/SiteMediaUploadTest.php tests/Feature/SiteDeletionTest.php`, 48 tests and 389 assertions. Covers real MIME detection of disguised files, invalid shape/format/size/extra fields, ownership and guest isolation, private storage/read, null-on-media-delete, replacement/removal retention, storage and unexpected assignment failures, publication preservation and site deletion.
- `composer types:check` passed with zero PHPStan errors. Changed PHP files passed Pint. Tests use their isolated migrated database; the local application database migration and settings UI remain for the next step. No browser evidence, work commit, merge, push, or deployment performed.
- Tim approved Step 1 before Step 2.

## Step 2 evidence

- Added a Favicon card immediately below Header logo in Site Settings with upload/replace, 48-pixel draft preview, Remove, square-PNG/size/recommended-dimension guidance, progress, announced feedback, validation association and focus, and temporary-URL cleanup. Platform browser-tab icons remain unchanged.
- Server payload resolves the selected favicon from same-site media only. Media visits explicitly preserve component state and refresh only site props, retaining unrelated appearance/name state and avoiding page-manager prop refresh. Favicon upload/removal participates in existing write/navigation guards and disables overlapping logo operations.
- Focused upload/settings regression command passed: `php artisan test --compact tests/Feature/SiteFaviconTest.php tests/Feature/SiteMediaUploadTest.php`, 38 tests and 346 assertions. Added upload/replacement/removal/reload payload checks, forged cross-site reference suppression, and partial-response scope coverage. Existing Step 1 deletion regressions already passed before these settings-only changes.
- Frontend formatting/lint, `npm run types:check`, `npm run build`, changed PHP formatting, and `composer types:check` passed. Applied only the new favicon migration to the local application database using its exact path; it completed successfully.
- Manual try path: open Site Settings, find Favicon below Header logo, upload a square PNG, verify the small preview, reload, replace, and Remove. Try a non-square image for validation and try uploading while an unrelated settings draft is unsaved to check preservation. Live published/tab icons remain Step 3. Browser behavior has not been observed by the agent; no browser runner is configured.
- Tim approved Step 2 before published rendering. No work commit, merge, push, or deployment performed.

## Step 3 evidence

- Frozen publications include the selected owned PNG and omit the optional reference when unset, preserving existing publication fingerprints. Public pages render one custom icon declaration, while unset customer domains use trusted platform URLs for default icons. Public media access uses the frozen selection and existing GET/HEAD paths.
- Focused publication, upload/settings/deletion and customer-domain tests passed: `php artisan test --compact tests/Feature/SiteFaviconPublicationTest.php tests/Feature/SiteFaviconTest.php tests/Feature/CustomerDomainTransportTest.php`, 68 tests and 670 assertions. Covers draft/live replacement and removal, all-page publication, legacy snapshots, malformed references and storage keys, ownership isolation, failed publication preservation, default links, and whole-site storage cleanup.
- `node --test workers/domain-proxy/content.test.mjs workers/domain-proxy/worker.test.mjs` passed all 121 tests, including exact binary PNG forwarding and HEAD behavior. Worker implementation and routes are unchanged.
- Final `composer ci:check` passed: frontend formatting/lint/types, PHP formatting/static analysis, production build, and 961 Pest tests with 6689 assertions. The initial PHPStan inference failure was repaired with a native media lookup loop; the narrow PHP type check and full gate then passed without suppressions.
- Manual path: Site Settings -> Favicon -> upload a square PNG -> open a page and Publish -> inspect Home and another page on shareable/custom addresses. Replace or Remove and confirm the live choice changes only after another Publish. Browser/tab-cache behavior remains unobserved; no browser runner is configured.
- Existing unrelated P2 findings F-02 and F-06 remain open. No blocking findings are recorded. The verified checkpoint still requires explicit commit approval and fresh independent review before completion. No commit, merge, push, or deployment performed.

## Independent-review repair evidence

- Fresh codex / gpt-6-astra review at checkpoint `0303d71e6aab72ffb1564cd6ec72abfc842fbb1d` requested changes for F-07: two Inertia test requests omitted the asset version and returned 409 with built assets. No new product-code defect was confirmed. Its failed gate supersedes the initial environment-dependent passing signal above.
- Added the current middleware asset-version header to the favicon partial-response test and existing Calendar CSP test. Production asset-version handling and their real response assertions remain intact. No product code changed in this repair.
- Focused `php artisan test --compact tests/Feature/SiteFaviconTest.php tests/Feature/EmbedBlockTest.php tests/Feature/InertiaAssetVersionTest.php` passed 45 tests and 344 assertions. No `public/hot` file was present, so verification used built assets.
- Repaired final `composer ci:check` passed frontend formatting/lint/types, Pint, PHPStan, build, and all 961 Pest tests with 6689 assertions. F-07 is fixed pending independent closure. New checkpoint approval and full fresh review are still required; no merge, push, or deployment performed.

## Completion-gate repair evidence

- Complete's final verification with the local Vite hot file present exposed a nullable-header mismatch left by F-07: middleware returns null in development while Inertia's response version is an empty string. Both test requests now coalesce the version to an empty string and run as explicit development/built-asset datasets. Production code and the local dev server remain unchanged.
- Focused favicon/Calendar/asset-version tests passed: 47 tests and 363 assertions. Final `composer ci:check` passed formatting/lint, frontend and PHP types, build, and all 963 tests with 6708 assertions. F-08 is fixed awaiting independent review; the prior receipt no longer establishes the changed test/spec state.
- Completion archival remains pending the new approved checkpoint and fresh review. The unrelated generated Vite metadata change was preserved separately in a stash; no generated cache change belongs to the feature.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":19967,"specSha256":"46a516cd8d147fcb600508e608ca78b6b0a3d93e3c688c965484a5c0bf48d6ec","branch":"refs/heads/feature/custom-site-favicons","head":"b682b6891f99c91506cc1479158729e93d7a7e83","baseRef":"refs/heads/main","baseCommit":"cad29f8656a92a197bda7254ee4fd19e63adc91a","sourceTree":"be984d22816026b3057c38b649247e108b1ad1cc","absentOptional":[]} -->

## Findings

### 20/F-07 [P1] closed - Send the asset version in Inertia test requests

**File:** tests/Feature/SiteFaviconTest.php:188
**Found:** 2026-09-30 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The new partial-reload test sends `X-Inertia` but omits `X-Inertia-Version`. With the normal built manifest present and no Vite hot file, HandleInertiaRequests returns the manifest hash, so Inertia responds with its expected 409 reload before the test can assert the favicon payload or page-prop exclusion. The focused review run fails 1 of 94 tests at line 192. The required `composer ci:check` also fails: 959 of 961 tests pass, with this failure and the same omission in the pre-existing `tests/Feature/EmbedBlockTest.php:62` request. The latter request is unchanged from the base commit. This is a reproducible test-gate failure and local-asset dependency, not evidence that real browser uploads fail.
**Suggested fix:** Send the current middleware-provided asset version in these test requests, following the existing InertiaAssetVersionTest pattern, or explicitly isolate versioning in these tests while retaining their real partial-response/CSP assertions. Keep production asset-version handling intact and keep built assets in place. Run the focused tests and `composer ci:check` without depending on a local Vite dev-server hot file.
**Resolution:** Confirmed at `0303d71e6aab72ffb1564cd6ec72abfc842fbb1d`; no repair performed in the independent reviewer context.

Builder repair on 2026-09-30 sends the middleware-provided current asset version in both identified Inertia test requests, preserving real partial-prop and CSP assertions and production version handling. Focused 45 tests / 344 assertions and full `composer ci:check` (961 tests / 6689 assertions) pass with no Vite hot file. F-07 is fixed, awaiting fresh independent re-review; no closure or acceptance is claimed.

Independent full-delta review by codex / gpt-6-astra at `e09198e2ce0edc0f3a5208a6ef9c899b7fc73794` on 2026-09-30 closes F-07. Re-examined both repaired requests in `tests/Feature/SiteFaviconTest.php` and `tests/Feature/EmbedBlockTest.php`: they send the current middleware asset version and retain their real partial-prop and CSP assertions; production version handling is unchanged. Fresh `composer ci:check` passed all 961 tests and 6689 assertions with `public/hot` absent, including both regressions. No new defect was found in the repair or complete feature delta.

### 20/F-08 [P1] closed - Normalize nullable development asset versions in test headers

**File:** tests/Feature/SiteFaviconTest.php:192
**Found:** 2026-09-30 during Complete final verification
**Why it matters:** Both repaired test requests send the nullable middleware asset version directly. With a Vite hot file present, middleware returns null but Inertia's getVersion converts it to an empty string; an explicit null request header fails the strict comparison and returns 409. Complete's final `composer ci:check` reproduced both failures (959 of 961 tests passed). The prior independent pass covered built assets only.
**Suggested fix:** Coalesce nullable middleware versions to an empty string, matching Inertia's wire contract, and exercise the favicon partial-response and Calendar CSP assertions in both development and built-asset modes. Preserve production code and the local dev server.
**Resolution:** Confirmed from vendor Inertia Middleware strict comparison and ResponseFactory getVersion string conversion. Repair and fresh review pending.

Builder repair on 2026-09-30 coalesces nullable versions to the empty wire string and tests both requests in explicit development/built-asset datasets. Focused 47 tests / 363 assertions and `composer ci:check` (963 tests / 6708 assertions) pass with the local hot file present. F-08 is fixed pending fresh independent closure.

Independent full-delta review by codex / gpt-6-astra at `b682b6891f99c91506cc1479158729e93d7a7e83` on 2026-09-30 closes F-08. Re-examined both requests in `tests/Feature/SiteFaviconTest.php` and `tests/Feature/EmbedBlockTest.php`: nullable middleware versions are normalized to the empty string, matching Inertia ResponseFactory string conversion and strict middleware comparison. Both tests exercise explicit development and built-asset datasets while retaining real partial-response and CSP assertions. Fresh `composer ci:check` passed 963 tests and 6708 assertions with the local `public/hot` present and untouched; all frontend/PHP checks and the production build passed. The full base-to-target review found no new defect in this repair or the complete feature delta.

## Independent review

# Independent Review

**Status:** passed
**Target commit:** b682b6891f99c91506cc1479158729e93d7a7e83
**Base commit:** cad29f8656a92a197bda7254ee4fd19e63adc91a
**Base ref:** refs/remotes/origin/main
**Spec hash:** 46a516cd8d147fcb600508e608ca78b6b0a3d93e3c688c965484a5c0bf48d6ec
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-30T21:15:53.979566+00:00
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-30T21:18:50.915384+00:00
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Commands

- `composer ci:check`: passed on authorized rerun outside the sandbox; frontend formatting/lint/types, Pint, PHPStan, production build, and all 963 Pest tests with 6708 assertions. The initial sandbox run stopped at Pint's local worker socket with EPERM; this environmental restriction was resolved by the successful rerun.
- `node --test workers/domain-proxy/content.test.mjs workers/domain-proxy/worker.test.mjs`: passed, 121 tests, no skipped or todo tests.
- `git diff --check cad29f8656a92a197bda7254ee4fd19e63adc91a HEAD`: passed.
- `git rev-parse HEAD`, `git symbolic-ref refs/remotes/origin/HEAD`, `git merge-base refs/remotes/origin/main HEAD`, `git status --short`, and `shasum -a 256 blueprint/context/current-feature.md`: passed checkpoint/base/spec freshness checks; only permitted review evidence differs from the target.

## Evidence

- Independently reviewed the complete `cad29f8656a92a197bda7254ee4fd19e63adc91a..b682b6891f99c91506cc1479158729e93d7a7e83` delta against the verified tracked spec, with all four lenses. Review and findings records were context, excluded from the product-code scope. No builder transcript was supplied.
- Reviewed all changed application actions, controllers, request validation, model, migration, routes, Settings Vue controls, Blade rendering, PHP/Worker tests, and planning/spec changes. Followed nearby upload transaction/cleanup, settings state/navigation guards, publication fingerprint, deletion lifecycle, domain eligibility, Laravel media streaming, and Worker forwarding contracts. Unrelated project areas, generated assets/helpers, dependency source beyond the relevant Inertia contract, and caches were excluded.
- Uploads enforce authenticated active-site ownership, PNG content/extension, square dimensions, size limits and field restrictions. Assignment reuses the existing site lock, private storage, transaction and failure cleanup; replacement/removal retains assets needed by frozen publications.
- Publication uses same-site validated media and generated escaped URLs, freezes the icon across pages, denies draft-only media, preserves legacy unset snapshot fingerprints, and restores trusted platform defaults on customer domains. Existing Worker routing remains restricted; PHP and Worker tests verify PNG GET bytes and bodyless HEAD.
- Settings controls preserve component state and restrict refreshed props to site data, participate in existing busy/navigation guards, release temporary URLs, and expose associated errors/progress. Existing Laravel/Vue/TypeScript, ownership, proportionality and testing standards were checked. No new dependency, abstraction, unbounded operation or query-per-asset loop was introduced.
- F-08 repair follows Inertia's actual string version contract, with explicit true/false hot-mode datasets for both requests. Real partial-response and CSP assertions remain intact; production versioning is unchanged. The full suite passed with the local hot file present and untouched, and the normal generated local build was retained.
- No skipped, focused or placeholder tests were found in the changed tests. Source review and automated tests establish response and transport behavior; no browser behavior is claimed.

## Findings

- No new findings across quality, security, performance or tests.
- F-08 [P1] closed after full fresh review and passing verification in both explicit asset modes. F-07 remains closed.
- Existing F-02 [P2] muted text contrast and F-06 [P2] ownership DNS-name length remain open. No finding was accepted or waived. No P0/P1 finding remains open or fixed.

## Remaining risk

- Browser interaction, unsaved-draft preservation in a real browser, responsive layout, keyboard/focus behavior and browser favicon caching were not observed; no browser test runner is configured and no development server was started.
- Customer-domain evidence is local Laravel integration plus actual Worker code with an offline origin seam. Live Cloudflare/IONOS deployment and browser delivery were not exercised.
- No dedicated security scanner or performance profiling command is configured. No network vulnerability scan or runtime performance measurement was performed.
- No verification command remains unavailable. The initial sandbox-only Pint socket limitation was resolved by the authorized successful full-suite rerun.
