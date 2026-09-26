# Feature: Page management

**From build-plan:** feature 8a
**Build attempt:** 1
**Status:** verified
**Branch:** feature/page-management
**Archive:** blueprint/history/features/08a-page-management.md

## Goal

Let the owner manage and edit multiple draft pages in each site without losing existing content or changing the site's published version. This is the first half of approved Feature 8; Feature 8b adds multi-page publication.

## In scope

- Dashboard → site settings (shared settings and page management) → individual page editor (publishing and page content). This user-requested revision replaces the combined editor screen.

- Give every existing and newly created site one Home page. Preserve existing blocks, IDs, order, content, styling, images, section links, site settings, and published snapshots.
- Add blank pages, rename pages, reorder pages, delete non-Home pages, and select a page for block editing and preview. Home identity is permanent and cannot be deleted; its display name may be edited without changing its role or public address.
- Keep the current block types, side-panel editor, image uploads, section navigation, and block reordering working within the selected page.
- Keep theme, name/logo, footer, media ownership, shareable site address, and current publication metadata at site level.
- Preserve Home publication at `/s/{slug}`. Additional pages remain private drafts in this feature, with clear editor messaging. Existing published snapshots continue rendering unchanged until an explicit Home Publish.

## Out of scope

- Feature 8b: publish all pages together, public page navigation alongside section links, editable paths such as `/s/church/about`, page-specific metadata, and public removal of deleted pages only after Publish. Preserve these approved contracts for that feature.
- Changing which page is Home, duplicating pages, moving blocks between pages, cross-page section links, nested pages, hiding selected pages from navigation, or automatic redirects.
- Billing, domains, new dependencies, media garbage collection, and a browser-test harness.

## Build loop

- Use the configured `feature/` prefix and frozen branch above. This packet is for spec review; do not implement before approval.
- `workflow.stepReview` is `every`: implement one step, verify it, present its result, and wait for approval before the next step.
- `workflow.checkpointCommits` is `enabled`: offer an optional checkpoint commit after an approved step; do not commit without authorization. `/complete` creates the final feature commit.
- Regular Audit, Check, and try guide are manual. Independent review is selected by `when-sensitive`; this feature changes ownership scoping, persisted data, and publishing, so require that review before completion. Use configured automatic execution when isolation can be proven, otherwise the manual handoff.

## Build steps

- [x] 1. Establish pages and preserve the existing Home experience.
      Add the page model, relationships, migration/backfill, and creation/factory support. Assign existing blocks to the site's Home without changing their identifiers or content. Create Home atomically with a new site. Keep the existing editor showing Home, and constrain publication snapshot construction to Home blocks while preserving the version-1 snapshot shape and fingerprint behavior. Review real migration/index conventions before selecting the exact DDL. Do not run a data-removing rollback against user data.
      **Done when:** migration tests prove existing content and published snapshots survive; new-site and factory tests prove exactly one Home; existing workspace, block, publication, and public-rendering tests pass. No user-visible regression occurs before additional pages are exposed.

- [x] 2. Add page management and page-scoped server operations.
      Add owner-scoped page read/create/update/order/delete actions with validation and transaction locking. Scope block creation, ordering, updates, deletion, hero targets, and image assignment to a resolved page. Preserve existing unqualified site/block URLs as Home-only operations and add nested page endpoints for other pages using the same implementation, not a second editor. Page deletion removes only that page's draft blocks, compacts page positions, and keeps media objects and published snapshots. Reject Home deletion on the server.
      **Done when:** Pest covers valid management, invalid names/orders, stale orders, Home protection, cross-account/site/page rejection, and page-scoped block/image/hero operations. Home Publish excludes every non-Home block and its otherwise unreferenced images. Failed mutations leave persisted data unchanged.

- [x] 3. Separate site management from individual page editing.
      From the dashboard, open site settings with shared name, styles, header/logo, footer, address, and metadata controls plus page creation, renaming, ordering, and deletion. Page management stays on this screen and preserves pending shared settings. Open an individual page for publishing status, blocks, preview, and the existing side-panel editor, with a link back to site settings. Home has the current Home-only Publish action; additional pages show a private-draft notice. Protect unsaved changes when leaving either screen and retain page selection after block saves/uploads.
      **Done when:** server tests verify separate Inertia screens and redirect destinations; live checks demonstrate shared settings, page CRUD/order, focused editing, error/pending states, unsaved navigation, Home protection, and desktop/mobile layouts.

- [x] 4. Verify the complete draft-page workflow and publication boundary.
      Add focused regression coverage not already provided by earlier steps, including existing populated-site migration, deleted/stale page requests, site-wide settings on multiple pages, independent block order, existing public snapshots, and republishing Home after other pages have been edited or deleted. Run the configured combined check and build. Record actual manual UI evidence or its availability limitation without claiming browser automation.
      **Done when:** `composer ci:check` and `npm run build` pass; evidence demonstrates no data loss, cross-page editing, or accidental publication of additional pages. After these implementation checks, the selected independent-review gate must pass against the checkpoint before completion.

## Files / areas

- `database/migrations/`, `database/factories/`, `app/Models/Site.php`, `app/Models/SiteBlock.php`, and a new `app/Models/SitePage.php`.
- `routes/web.php`; `app/Http/Controllers/SiteController.php`, `SiteBlockController.php`, `SiteMediaController.php`, `SitePublishingController.php`, and a page-management controller.
- `app/Http/Requests/` for page requests and existing block/order/image validation; `app/Actions/BuildSitePublicationSnapshot.php` for Home-only snapshots.
- `resources/js/pages/Sites/Settings.vue`, `Show.vue`, and small existing-pattern components if needed to keep page management understandable. Regenerate Wayfinder helpers through the existing tooling where used.
- `tests/Feature/` for page management/migration coverage and affected site, block, media, and publishing tests.
- `app/Http/Controllers/PublishedSiteController.php` and `resources/views/sites/published.blade.php` are regression surfaces; multi-page public routing/rendering belongs to 8b.

## Data / contracts

### Stored data and migration

- `site_pages`: generated integer `id`, `site_id` foreign key, trimmed nonempty `name` up to 255 characters, nonnegative integer `position`, boolean `is_home`, and timestamps. New sites and migration create a page named `Home`, with `is_home = true` and position 0. New additional pages append with `is_home = false`; duplicate display names are allowed because identity is the numeric ID.
- Exactly one Home per site. Create it with the site in one transaction; page mutation input cannot set `site_id` or `is_home`. Home participates in display ordering but keeps its identity and root public URL regardless of position/name. Use site locking for mutations that can change membership/order; page positions are contiguous from zero after mutation.
- Add a required `page_id` foreign key to blocks after backfilling. Keep existing `site_id`; every block must belong to a page in that same site. Use page-scoped creation and enforce matching site/page ownership at every write. Deleting a page removes its blocks transactionally; deleting Home is always rejected.
- Preserve original block IDs and content during backfill so existing `target_block_id` links and published snapshot references remain valid. Cover sites with no blocks as well as populated sites.
- Do not introduce page slugs or relocate SEO/social-image fields in 8a. Feature 8b owns those contracts and their migration. Do not rename or rewrite existing published snapshots during this migration.

### Requests, ownership, and ordering

- Trusted actor comes from the authenticated, verified request. Resolve site through `$request->user()->sites()`, then page through that site's pages, then block through that page. Never load a submitted page/block globally before ownership resolution. Missing or mismatched resources return 404 using current patterns.
- Existing `GET /sites/{site}` opens site settings. Add `GET /sites/{site}/pages/{page}` for explicit page selection; POST/PATCH/DELETE page-management actions under `/sites/{site}/pages`, with `/order` before dynamic page routes. Numeric IDs use numeric constraints. Block and block-image actions for explicit pages nest under `/sites/{site}/pages/{page}/blocks`.
- Existing unqualified block endpoints operate only on Home. Do not infer a different page from a supplied block ID. Preserve page selection in all redirects from the editor; any return-page parameter is validated through the owned site's pages, never treated as an arbitrary redirect URL.
- Page create/update accepts only `name`; ordering accepts integer arrays `expected_order` and `order`, with distinct IDs and the exact current membership. Compare the expected sequence and apply the desired sequence while holding the owning site's row lock in a transaction, following the current block-order implementation. Stale order returns an actionable validation error and does not partially reorder.
- Reuse the same site-lock convention for page deletion and page/block mutation. Re-resolve page and block after obtaining the lock so a concurrently deleted page cannot be written through cached request models.
- Hero section targets must be another block on the same page. Deleting a block clears referencing hero links only in that page. Media remains site-owned and may be referenced across pages in the same site; assigning or serving another site's media remains denied.
- Validation uses the existing Laravel/Inertia error bag and redirects. Home deletion returns a field/action validation error. Unexpected failures retain error reporting and show a retry message without claiming success.

### Publication and client behavior

- Publish remains an explicit snapshot of Home plus site-wide shell/metadata, regardless of selected draft page. Fingerprint/status compares the same Home publication scope. Non-Home edits do not make the Home publication status inaccurate.
- No page-management or block-edit action mutates `published_snapshot` or `published_at`. Deletion retains media needed by existing snapshots; no storage deletion is added.
- Render user-entered names and content as escaped text using existing Vue/Blade patterns. Keep current URL validation for hero external links and media handling.
- Reuse existing dirty-state handling; canceled navigation keeps edits and focus. Pending requests prevent duplicate/conflicting actions. Inputs have labels and associated errors; validation focuses the first invalid input, errors are announced, success is announced, and stale messages clear on retry/page change. Page reorder has keyboard controls. Deletion requires confirmation naming the page and explaining that its draft blocks are removed; cancel leaves everything unchanged.
- An empty page offers the current add-block state. Page-management errors offer a refresh of the site settings page list, without silently discarding pending edits. Keep server authorization failures distinct from empty content.

## Testing

- Final step 4 verification: `composer ci:check` passed frontend formatting/lint/typecheck, Pint, PHPStan, and 173 Pest tests (1,573 assertions). `npm run build` passed; its optional fontaine fallback warning does not prevent the build. Existing tracked build output was restored after verification to avoid committing partial ignored build artifacts. Existing live UI evidence plus user acceptance cover the revised settings/editor flow. No new abstraction or dependency was added.
- Added end-to-end HTTP coverage for republishing changed Home content/media after editing and deleting other drafts, both-page shared settings, deleted page reads/writes, stable public URL, public image allowlist replacement, and a cleared unpublished-changes flag. F-05 is fixed pending independent re-review. Required independent review follows the approved checkpoint; see review.md for its current receipt.

- Page-opening bug repair: reproduced a 500 when an empty page belongs to a site with a logo/social image. Empty Eloquent collections retained model semantics, so deduplicating appended numeric media IDs called `getKey()` on an integer. Convert to a base collection before collecting media IDs. Regression cases cover logo, social image, and one image serving both roles on empty Home and newly created pages. All three cases reproduced the reported 500 before the fix and pass afterward; `composer ci:check` passes with 172 tests and 1,510 assertions, plus frontend checks, Pint, and PHPStan.

- Shared-settings refinement requested after review: replace all three collapsible sections with static cards and fixed headings; fields stay visible.
- Revised step 3 implemented and awaiting review: dashboard opens dedicated site settings; page management stays there and preserves shared drafts. Explicit page routes show only publishing, blocks, preview, and the side panel. Home retains its Publish action and other pages have a private-draft notice.
- Automated evidence for the revision: `composer ci:check` passed frontend checks, Pint, PHPStan, and 169 Pest tests (1,392 assertions). Updated existing response tests and added coverage proving separate settings/editor props and page-management redirects. Frontend formatting/lint/typecheck and `git diff --check` passed again after layout and validation-focus refinements.
- Live revised-flow evidence: exercised the running Herd app using bundled Playwright/Chrome and an isolated temporary verified account. Confirmed dashboard navigation, create/rename/keyboard-reorder/delete staying in settings, preservation of pending shared settings, canceled/confirmed unsaved navigation from both screens, separate Home/Visit content, draft-only non-Home UI, Home protection, saved site name/theme/footer, validation focus, simulated HTTP failure preserving input, pending controls, and successful retry. Fixed shared-setting error focus to wait until request controls are enabled. Desktop (1440px), mobile (390px, no horizontal overflow), and dark editor screenshots inspected; no browser runtime errors. Evidence: `/private/tmp/churchsite-settings-desktop.png`, `/private/tmp/churchsite-settings-mobile.png`, `/private/tmp/churchsite-page-editor-desktop.png`, `/private/tmp/churchsite-page-editor-mobile.png`, `/private/tmp/churchsite-page-editor-dark.png`. No dev server or test harness was installed. Step 4 verification is recorded below; independent review is recorded separately in review.md.

### Earlier combined-screen evidence (superseded by the revised flow)

- Step 3 implemented and awaiting review: `composer ci:check` passed frontend formatting/lint/typecheck, Pint, PHPStan, and 168 Pest tests (1,352 assertions). Frontend checks were rerun successfully after adding the selected-page accessibility announcement. New server tests cover selected-page redirects for shared settings, logo/social image operations, media descriptions, publishing and ordering, plus rejection of malformed/foreign return-page context before mutation.
- Live UI evidence: exercised the existing Herd app at `http://churchsiteappnew.test` in isolated headless Chrome using the bundled Playwright runtime and a temporary verified account. Verified blank-page creation, rename, keyboard page/block reorder, independent content and section targets, canceled/confirmed unsaved navigation and deletion, protected Home, shared settings retaining page selection, validation focus, simulated HTTP failure recovery, and pending controls. Desktop (1440px) and mobile (390px) screenshots were inspected; mobile had no horizontal overflow. Light and dark appearance were inspected with no browser runtime errors. Screenshots: `/private/tmp/churchsite-pages-desktop.png`, `/private/tmp/churchsite-pages-mobile.png`, `/private/tmp/churchsite-pages-dark.png`. Temporary account/sites/sessions were removed after verification. `php artisan migrate --no-interaction` reported nothing to migrate; no dev server or browser-test harness was installed or started.

- Step 2 approved: `composer ci:check` passed frontend formatting/lint/typecheck, Pint, PHPStan, and 166 Pest tests (1,308 assertions). Twelve new endpoint tests cover page management, Home protection, ownership/page scoping, hero links, uploads, stale writes, and rollback on failure. The nested block URL exception was added to empty-string middleware, and existing editor block requests now target the selected page. Wayfinder helpers were regenerated. Page management controls and preserving selection after shared settings changes remain in step 3. No application database migration or live UI check was run.

- Step 1 approved: `composer test` passed Pint, PHPStan, and all 154 Pest tests (1,131 assertions), including 8 new page-foundation tests. `git diff --check` passed. Backfill was tested against the legacy table shape in the isolated SQLite test database; no application database migration or live UI check was run.

- Baseline `composer ci:check` passed before planning changes: frontend formatting/lint/typecheck, Pint, PHPStan, and 146 Pest tests with 1,067 assertions. The first sandboxed attempt could not open Pint's local worker socket; the authorized retry passed.
- Add focused Pest tests for the migration and all persisted/ownership/order/publication contracts above; reuse existing storage fakes and block fixtures. Test the actual migration backfill, not just new factories.
- Run targeted tests after each implementation step, then `composer ci:check` and `npm run build` at the final step. Browser automation is not configured; do not add it here.
- Check the editor at desktop and mobile widths using the running app when available, including keyboard operation, error and pending states, unsaved edits, and successful page transitions. No live UI or integration verification has been performed while writing this spec.

## Notes for the AI

- Approved scope: 8a page management followed by 8b navigation/publishing. Implement only 8a in this branch. The plan and overview carry the approved end-state contracts for 8b.
- Use the repository's existing Laravel/Inertia request, transaction, validation, styling, and test patterns. Do not add a parallel editor, generic content framework, new dependency, or storage cleanup system.
- Before implementation, trace all site-wide block queries and editor return destinations; every affected query must intentionally select Home, the active page, or a site-wide collection appropriate to its purpose.
- Build attempt 1 is supported by no prior archived feature 8a. The exact archive leaf was absent, parents were ordinary directories, the exact archive path had no Git history, and the branch was valid and had no existing/namespace-conflicting local ref at spec preparation.
- The approved plan split and regenerated overview are documentation changes only. No application code, database data, or Git branch was changed during spec preparation.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":20310,"specSha256":"c95eae04fd579700cd46d240d923c2c2139e982171a1dcdef4cfd3838f11bba1","branch":"refs/heads/feature/page-management","head":"4a699022b3c80400b8b5c0990c35bad01f490f70","baseRef":"refs/heads/main","baseCommit":"de1a194b254236c97ba441c4f74ea807b4665fae","sourceTree":"73b024b16928e6285b4eab9a9ad25a930aef6f3b"} -->

## Findings

### 8a/F-05 [P2] closed - Cover successful republishing and replacement of public media access

**File:** tests/Feature/SitePublishingTest.php:96
**Found:** 2026-09-25 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The feature's central update path is not exercised by a successful second publication in the current tests. The draft-isolation test stops after editing, and the public-media test verifies continued access before republishing but never checks that the previous image becomes inaccessible afterward. A regression that retains the old snapshot, leaves the unpublished-changes flag set, or preserves a removed image in the public allowlist could therefore pass the suite. This is a confirmed coverage gap, not a reproduced product defect.
**Suggested fix:** Extend the existing Pest tests to publish, change content and replace or remove a referenced image, then publish again. Assert that the URL is unchanged, new content is served, the new image is accessible when present, the old image is 404, and the editor reports no unpublished changes. Reuse fake storage and the existing routes.
**Resolution:** Fixed during feature 8a final verification. The new `republishing Home updates public content and media without publishing other pages` test in tests/Feature/SitePageManagementTest.php proves successful second publication, unchanged URL, new public content and image access, old/private image 404, and cleared unpublished-changes state. Focused test and full 173-test suite pass. Independent review on 2026-09-26 at 4a699022b3c80400b8b5c0990c35bad01f490f70 inspected SitePublishingTest.php and the new SitePageManagementTest.php regression, confirmed the original coverage gap is closed without introducing a new defect, and reran all 173 Pest tests (1,573 assertions) successfully. Closed F-05.

## Independent review

# Independent Review

**Status:** passed
**Target commit:** 4a699022b3c80400b8b5c0990c35bad01f490f70
**Base commit:** de1a194b254236c97ba441c4f74ea807b4665fae
**Base ref:** refs/heads/main
**Spec hash:** c95eae04fd579700cd46d240d923c2c2139e982171a1dcdef4cfd3838f11bba1
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-26T21:41:03.486905+00:00
**Workflow:** regular
**Check required:** no
**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-26T21:45:07.755688+00:00
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Handoff

Review the active spec and the complete `de1a194b254236c97ba441c4f74ea807b4665fae..4a699022b3c80400b8b5c0990c35bad01f490f70` delta in a fresh
session or isolated subagent without the builder conversation. Run all Audit lenses from scratch.
Run Check when required above. Do not edit product code, accept findings, or
reuse the existing findings as the review scope.

## Commands

- `git rev-parse HEAD`, `git merge-base refs/heads/main HEAD`, `git status --short`, and `shasum -a 256 blueprint/context/current-feature.md`: passed; target, base, tracked spec hash, and allowed dirty paths match the request.
- `git diff de1a194b254236c97ba441c4f74ea807b4665fae..4a699022b3c80400b8b5c0990c35bad01f490f70` with bounded path reads: reviewed the full feature delta and relevant adjacent code.
- `composer ci:check`: passed after an authorized retry permitting Pint's local worker socket. Frontend formatting/lint, Vue TypeScript, Pint, PHPStan, and 173 Pest tests (1,573 assertions) passed. The initial sandbox attempt stopped at Pint's socket with EPERM; the retry resolved that environment limitation.
- `git diff --check de1a194b..4a699022`: passed.
- `php artisan route:list --path=sites --json`: passed; page and legacy route methods, ownership middleware, and generated page helper contracts inspected.
- Targeted searches for skipped, focused, or placeholder tests in the new page test files: none found.
- Read-only Python calculations and final freshness assertions: passed; reproduced F-02 contrast and verified no product, test, configuration, or spec changes during review.

## Evidence

- The runtime selected Codex model `gpt-6-astra`, high reasoning, with `fork_turns=none`; the reviewer received the project-local handoff without the builder transcript. The parent confirmed the exact spawn metadata. This is the independent reviewer context, not a continuation of the builder review.
- Reviewed changed application controllers, requests, models, middleware, Home-only snapshot selection, the page migration, routes/bootstrap, Settings.vue, Show.vue, PageManager.vue, changed feature tests, and feature planning/spec changes. Adjacent review included SitePublishingController, PublishedSiteController, SitePublishingTest, SiteNameRequest, Dashboard links, and workspace CSS. Applied the project's Laravel/Vue, ownership, validation, transaction, accessibility, proportionality, and test standards.
- Ownership is resolved user -> site -> page -> block. Membership/order mutations share the site lock and re-resolve pages after acquiring it. Home protection, stale order rejection, transaction rollback, foreign account/site/page rejection, image assignment, and same-page section links are covered by passing HTTP tests.
- The real migration backfill test preserves legacy block IDs/content/order, media, site fields, published snapshot bytes, fingerprint, and public rendering. New-site creation rolls back when Home creation fails; required page foreign keys are tested.
- Home-only publication excludes additional draft blocks and otherwise unreferenced media. The second-publication regression verifies changed public content, stable URL, new image access, old/private image denial, and cleared unpublished-changes state. F-05 is closed.
- Source review covered page create/rename/order/delete, retained shared-setting drafts during partial page responses, selected-page editor redirects, unsaved navigation guards, labelled controls, keyboard order controls, status/error feedback, and responsive layout. No new confirmed quality, security, performance, or test finding was identified.
- Inspected the recorded live-flow evidence in the spec and `/private/tmp/churchsite-settings-desktop.png` and `/private/tmp/churchsite-page-editor-mobile.png`. The settings screenshot predates the subsequent static-card heading refinement; that small refinement was inspected in the final source. Build success is recorded in the exact hashed spec; the build was not rerun because it rewrites tracked generated output.
- Generated Wayfinder route/action declarations were checked at their route contracts and through frontend checks; repetitive generated helper bodies were excluded from hand-maintained-code review. Dependencies, caches, built/minified output, ignored dashboard state, and review/findings evidence were excluded from product-code scope. No network audit, external action, or product repair was performed.

## Findings

- F-02 [P2] remains open: existing muted workspace text contrast is 4.16:1 on white, 3.86:1 on the page background, and 3.97:1 on the soft surface. No acceptance or severity waiver was made.
- F-05 [P2] closed: successful republishing and public-media replacement coverage now verifies the missing contract.
- No new findings. No open or fixed P0/P1 findings remain.

## Remaining risk

- Browser interactions were not replayed by this reviewer. Existing live-flow notes and two screenshots were inspected; no browser harness is configured. The settings screenshot predates the final static-heading refinement.
- Migration and transactional regression tests run against isolated SQLite. MySQL DDL and concurrent row-lock behavior were not exercised against a live MySQL database.
- `npm run build` was not rerun in this read-only review to avoid modifying tracked generated output; the exact spec records its passing final build and optional fontaine warning.
- F-02 remains an open, nonblocking P2 accessibility contrast issue; darkening the existing token is the remaining repair recommendation.
