# Feature: Navigation and publishing

**From build-plan:** feature 8b
**Build attempt:** 1
**Status:** verified
**Branch:** feature/navigation-and-publishing
**Archive:** blueprint/history/features/08b-navigation-and-publishing.md

## Goal

Publish the site's ordered pages together, with visitor navigation, editable page addresses, and individual search/share metadata. Preserve the site-settings-to-page-editor flow from 8a and keep the last published version stable until the owner explicitly publishes again.

## In scope

- Keep Home at the existing /s/{site-slug} address. Other pages use a generated, unique, editable single-segment path such as /s/church/about.
- Preserve paths when display names change. Explicit path edits affect the public site only on Publish; old paths then return 404 without redirects, as approved by the user.
- Give each page its own optional title, description, and social preview image. Preserve Home's existing metadata. Default Home's title to the site name, and other pages to "Page name | Site name"; blank descriptions and social images stay unset.
- Move page-specific controls into the selected page's editor. Keep name, theme, logo/header, footer, site address, and page management at site level.
- Publish all saved pages, names, paths, order, blocks, metadata, and shared settings atomically. Draft deletion/editing never changes the public snapshot before Publish.
- Show only page navigation in the editor preview and public Blade pages, without a visible Pages heading. Use ordered page names and indicate the current page. Remove section-link menus per the user’s step-4 follow-up; retain section anchors for hero buttons.
- Keep version-1 single-page snapshots and their public media working without rewriting them during migration.

## Out of scope

- Per-page publishing, hidden/unpublished page flags, nested paths, redirects/history, duplicating pages, moving blocks between pages, changing Home identity, or cross-page hero section targets.
- Billing, domains, future routing abstractions, menus independent of the page list, noindex policy changes, new blocks/themes, media garbage collection, dependencies, and a browser-test harness.
- The existing unrelated muted-text contrast finding.

## Build loop

- This is a specification for review. Do not implement until approved. Use the frozen branch above; do not create or switch branches during spec preparation.
- workflow.stepReview is every: implement and verify one step, present its result, and wait for approval before the next.
- workflow.checkpointCommits is enabled: offer optional checkpoints after approved steps; do not commit without authorization. Complete owns the final work commit and merge.
- Regular Audit, Check, and try guide are manual. Independent review is required by when-sensitive because this work changes persisted data, owner-scoped writes, publication, public routes, and media access. Use configured automatic execution when isolation and identity can be proven, against the final approved checkpoint.
- Reuse the installed Laravel/Inertia/Blade implementation and site lock. Do not add a parallel renderer, generic content framework, or external service.

## Build steps

- [x] 1. Add draft page paths and metadata with a safe Home transition.
      Add page fields, deterministic path allocation/backfill, relationships, validation, and owner-scoped updates/uploads. Copy existing site metadata to Home without changing blocks, page identity/order, assets, or published snapshots. Make Home's page fields authoritative; adapt existing Home controls/endpoints to read/write that source until step 2 moves the UI. Keep version-1 Home-only publishing functional by reading Home metadata.
      **Done when:** focused Pest tests cover populated-site metadata backfill, unchanged snapshot bytes, generated path edge cases, stable paths after renaming, invalid/duplicate path edits, immutable Home root, ownership/media scoping, upload failure cleanup, and stale page writes. Existing Home publishing and workspace behavior remains working.

- [x] 2. Move page-specific settings into the focused page editor.
      Add a collapsible page-settings card for path, title, description, and social image (updated at user request after step 2). Home shows its fixed root address. Remove Home metadata controls from shared site settings, leaving the global site address there. Reuse field/upload/error/dirty-state handling and retain page selection after saves, uploads, and clears. Other pages still accurately display draft-only publication until step 3.
      **Done when:** response/redirect tests and available live UI checks show independent Home/other-page metadata, title fallback, preserved Home values, validation/focus, upload/clear states, protected unsaved navigation, and responsive layout. Shared settings and block editing still work.

- [x] 3. Publish and serve complete site snapshots.
      Add version-2 multi-page snapshots and public page routing while retaining version-1 rendering/media. Capture every saved page and shared setting under the existing site lock. Resolve visitor content solely from the snapshot. Change publishing controls in every editor to Publish site, explain that all saved pages are included, and remove the non-Home draft-only limitation. Compare the whole site for unpublished-change status.
      **Done when:** HTTP tests prove first publication/republishing, legacy compatibility, atomic failure preservation, per-page metadata/Open Graph URLs, draft isolation, changed/deleted paths before and after Publish, site-wide public media allowlists, and ownership isolation. Publishing retains the selected editor, whose public link uses its published path only if that page exists in the current snapshot.

- [x] 4. Add page navigation to public pages and editor preview.
      Render ordered page links and a current-page indicator with no visible menu heading or separate section-link menu. Public links use published names/paths/order; preview links open the corresponding draft editor through the existing discard guard. Keep section anchors and hero targets scoped to the displayed page. Support blank pages and Home-only sites.
      **Done when:** rendering tests and available live checks cover page rename/reorder, Home away from first position, page links and local hero targets, blank pages, protected preview transitions, keyboard/focus behavior, long names, mobile drawer controls, and existing themes. Public navigation must not change before Publish.

- [x] 5. Verify migration, publication, and the owner/visitor flow.
      Fill meaningful remaining coverage gaps, run composer ci:check and npm run build, and record exact evidence. Exercise dashboard -> site settings -> page settings/content -> Publish site -> visitor navigation, including later path changes and deletion followed by republishing.
      **Done when:** checks/build pass, available live evidence supports the UI and snapshot boundaries, and no unrelated source or generated-build changes are included. Mark implementation verified, prepare the approved checkpoint, and obtain the required independent-review receipt before Complete.

## Files / areas

- database/migrations/; app/Models/SitePage.php, Site.php, and existing media relationships.
- app/Http/Controllers/SitePageController.php, SiteController.php, SiteMediaController.php, SitePublishingController.php, and PublishedSiteController.php.
- app/Http/Requests/SitePageRequest.php, SiteSettingsRequest.php, StoreSiteImageRequest.php, and a focused page-settings request if needed to separate name-only page management from settings validation.
- app/Actions/BuildSitePublicationSnapshot.php, app/Http/Middleware/PreserveEditorPage.php where destinations need adjustment, and routes/web.php.
- resources/js/pages/Sites/Settings.vue, Show.vue, resources/js/components/sites/PageManager.vue, and resources/views/sites/published.blade.php. Reuse theme styles and regenerate Wayfinder helpers.
- Existing page, publishing, media, appearance, and public-rendering Pest tests under tests/Feature/, with focused new tests where appropriate.
- Do not change workflow skills, dependencies, or deployment configuration.

## Data / contracts

### Draft page fields and migration

- Add path: nullable string up to 100 characters, unique database key on (site_id, path). Home uses null for root; every non-Home page has a nonempty path. Client input cannot change Home identity, site ownership, or the root address.
- Generate paths with installed Laravel slugging from the page name, using lowercase ASCII letters, digits, and single separating hyphens, consistent with the existing site-address grammar. If slugging is empty, use page-{id}.
- Resolve generated collisions deterministically with -2, -3, etc., shortening the base to fit 100 characters. Backfill additional pages in ID order. Allocate under the site lock with the unique database key as final guard. Explicit duplicate user input produces a field error rather than silently changing the requested path.
- Trim/lowercase explicit input, then validate grammar/length. Reject slashes, full URLs, queries, fragments, internal whitespace, and empty non-Home paths. A name change never regenerates a path.
- Add page seo_title (nullable string, maximum 255), seo_description (nullable text, maximum 2,000), and social_image_id (nullable media reference). Reuse existing validation limits and blank-to-null behavior.
- Copy the site's current metadata to Home exactly. Other pages start with unset optional metadata; do not infer images/descriptions from blocks.
- Page fields become the single authoritative draft metadata source after backfill. Legacy site columns may remain unused to keep migration additive, but must not become a second writable source or fallback. Remove obsolete caller/model/request assumptions; do not dual-write.
- Preserve published JSON byte-for-byte. Version-1 snapshots retain embedded metadata regardless of draft schema changes. Never run data-removing rollback commands against application data.

### Owner operations and isolation

- Use existing authenticated, verified routes. Resolve user -> owned site -> that site's page for every metadata/path write and image upload/clear. Scope media to the owned site.
- Use PATCH /sites/{site}/pages/{page}/settings (sites.pages.settings.update) for paths and metadata. Keep PATCH /sites/{site}/pages/{page} name-only for page management. This route choice was resolved in step 1.
- Page social-image upload/clear routes nest under /sites/{site}/pages/{page}/social-image. Reuse JPEG/PNG, size limits, private storage, and failure cleanup. Re-resolve the page after acquiring the site lock, including after storage I/O, to reject a late upload to a deleted page.
- Existing site-level metadata/social-image endpoints may remain Home-only compatibility entry points backed by Home fields. A return-page query must never change which page they update. Shared name/theme/logo/footer/site-slug operations remain site-wide.
- Use the existing site transaction lock for membership/order, metadata, blocks, images, and publication. Translate genuine uniqueness conflicts into path validation errors; do not hide unrelated database failures.
- Clearing/replacing an image or deleting a draft page never deletes its media object. Published assets remain readable while the old snapshot references them.
- Deleted/foreign/mismatched pages return 404. Failed validation, stale requests, storage failures, and publication failures preserve prior saved data and the public snapshot.

### Public routes and snapshots

- Home: GET /s/{site-slug}. Other pages: GET /s/{site-slug}/{page-path}, exactly one segment with the safe grammar above. Preserve /s/{site-slug}/media/{mediaAsset}; prove resource and page routing remain unambiguous.
- Site slug remains site-wide and immutable after initial publication, matching existing behavior.
- Keep the existing published_snapshot and published_at fields. Version 2 contains shared site data, ordered pages (ID, name, position, Home identity, path, metadata, ordered blocks), and a deduplicated site-wide media list. Retain stable block IDs and current content/style shape.
- Support version 1 explicitly as the existing Home-only public site, and version 2 explicitly as multi-page content. Malformed/unsupported snapshots retain safe 404 behavior.
- Capture all pages atomically while holding the site lock. Failure changes neither the snapshot nor timestamp. Never publish page-by-page or read visitor content from draft page rows.
- Names, paths, order, metadata, deletion, additions, and any page's blocks affect the whole-site draft fingerprint. Define/test comparison with a version-1 publication so newly publishable pages are detected without accidentally comparing incompatible shapes or altering visitor content.
- Changed/deleted published pages continue serving the old snapshot at the old path until Publish. After Publish, deleted/superseded paths return 404 without redirects. A path explicitly reused by another page resolves to that page after publication; there is no historical path reservation.
- Public media access includes only snapshot-referenced shared logo, all published block images, and each published page's social image. Draft-only assets stay private. Removed assets become 404 after republish unless another published page still references them.
- Preserve noindex/nofollow headers/meta, no-store behavior, storage-key ownership checks, escaped rendering, and safe external/video URL handling.
- Use custom page title when set; otherwise Home uses the published site name and another page uses "published page name | published site name". Blank descriptions/social images have no fallback. Open Graph URL identifies the current published page.

### Editor, navigation, and feedback

- Preserve dashboard -> shared site settings -> focused page editor. Page editor owns path, metadata, publication, blocks, and preview. Site settings owns global appearance and page management.
- Every publishing control explicitly publishes all saved pages. Dirty local block/metadata input must be saved or discarded first; never imply unsaved input is included. Retain the selected editor after Publish.
- For a changed draft path, View published page uses the old published path found by stable page ID. Unpublished pages have no public link. Preserve Home's version-1 public link.
- Public navigation uses snapshot names/order/paths. Editor preview links use draft pages and owner editor routes with unsaved-change guards; do not send private preview navigation through public routes.
- Keep an accessible name for page navigation without a visible heading, and mark the current page with aria-current="page". Use plain text links, underline the active link, and align navigation to the right of the header. Below 640px, show a hamburger that opens a right-side drawer, with an X to close, Escape/backdrop dismissal, keyboard focus containment/return, and current-page indication. Set the shared header logo to 75px high with automatic proportional width, constrained to the available width. Do not display a section-link menu. Anchors and hero section targets remain local to the current page's block IDs.
- Provide blank states, upload progress, disabled/pending controls, associated field errors, success announcements, and retry feedback for unexpected failures. Preserve input on failure/cancellation; focus invalid fields once controls are enabled.
- Keep shared site settings cards static. The page editor Page settings card is collapsible and initially collapsed, as requested after step 2. Retain unsaved input when collapsed, expose keyboard/expanded-state controls, and open for validation feedback. Reuse responsive/light/dark styles and keyboard controls. Long names/paths must not cause page-level horizontal overflow.

## Testing

- Baseline composer ci:check passed before spec preparation: frontend formatting/lint/typecheck, Pint, PHPStan, and 173 Pest tests with 1,573 assertions. Product files have not changed since that baseline.
- Test the real migration/backfill in the isolated test database: preserve Home metadata, block IDs/content/order, media, and version-1 snapshot bytes and rendering.
- Cover generated collisions/fallback/truncation, invalid/duplicate paths, stable paths on rename, Home protection, ownership, and stale/concurrent-write boundaries using existing test seams. Do not claim live MySQL concurrency from SQLite tests.
- Verify metadata independence for Home and two additional pages, title fallbacks, same-site shared images, and rejection of foreign assets.
- Cover whole-site publication, version transition, failed publication rollback, draft isolation, changed/deleted paths on republish, navigation/order/anchors, page-specific Open Graph fields, and public media access before/after replacement/deletion.
- Run focused tests per logic-bearing step and composer ci:check plus npm run build for final verification. Format regenerated route helpers and keep unrelated generated build output out of the feature.
- No browser harness is configured. Use the already running app for proportionate one-off evidence when available; do not start a server or install a harness. Check desktop/mobile, light/dark, keyboard navigation, dirty-state cancellation, pending/errors, and the owner-to-public workflow. Record limits honestly.
- No implementation, application-data migration, live check, or independent review for 8b has run during planning.

## Notes for the AI

- User-approved decisions: generated editable paths without redirects, stable paths when names change, explicit URL changes on Publish, page-name-plus-site-name title fallback, preserved Home metadata, and unset blank descriptions/social images.
- Build attempt 1 is supported by no prior 8b archive, no Git history at the exact destination, ordinary archive parent directories, and no existing or namespace-conflicting branch ref. Branch and archive names above are frozen.
- Preserve 8a's Home protection, isolated page blocks, stable published media, and separate settings/editor screens.
- Keep existing metadata/version-1 assertions when moving their source of truth. Do not delete behavior coverage merely because schema or props moved.
- Prefer additive migration and direct existing-controller changes. A helper must remove real duplication needed now, not anticipate later domains or publishing modes.
- The user approved the spec and implementation. The user approved steps 1–3, including the collapsible-card follow-up. The user approved step 4 and its menu/logo refinements and authorized final verification in step 5. No checkpoint commit is authorized yet.

## Implementation evidence

### Step 1 — ready for review

- Added page paths, metadata, and social-image relationships; generated paths allocate under the existing controller site lock and preserve their value on rename. The same allocator backfills pages in ID order. The site/path unique key also protects explicit writes.
- Home metadata is authoritative on its page row. Existing Home controls, legacy endpoints, editor-return redirects, and version-1 publishing read/write that source; legacy site metadata is neither written nor used as fallback.
- New nested settings and social-image endpoints enforce owner/site/page membership, field validation, selected-page redirects, and a fresh page lookup under the site lock. New upload objects are cleaned after storage/assignment failures or a deleted page.
- Migration preservation tests exposed SQLite's table-rebuild cascade behavior inside a transaction. The SQLite migration adds the nullable foreign-key column directly, preserving child blocks while retaining an enforced media foreign key. Other databases use the existing Laravel schema conventions.
- composer ci:check passed: frontend formatting/lint and Vue/TypeScript check, Pint, PHPStan, and **203 Pest tests / 1,786 assertions**. New focused suite: 30 tests, including migration, unique paths, metadata independence, stale settings/uploads, image failures, FK enforcement, and Home compatibility. Existing public-rendering and Home publishing assertions remain covered.
- Local migrate reported Nothing to migrate: the new schema was already applied (batch 8). A read-only inspection found valid Home roots/additional-page paths and 13 existing blocks. Existing-row hashes were unchanged across this no-op migration command. One Home description differs from its obsolete site column and has a newer update timestamp; it was preserved. Exact backfill/snapshot preservation is proven by isolated migration tests, not claimed from a live pre-migration capture.
- UI placement, multi-page publication, and navigation remain steps 2–4. No build, new live browser check, or independent-review gate has run yet for this feature. No commit, merge, or push was performed.

### Step 2 — ready for review

- Added a static Page settings card in each editor for path, search/share title and description, and social preview image. Home shows its fixed root address; title previews use the approved Home/site-name and page-name-plus-site-name defaults.
- Shared settings retain theme/footer/site address and no longer expose page metadata controls or props. Selected-page props contain only that page's metadata and a site-scoped image URL.
- Form saves, uploads, and clears retain the selected editor. Page settings integrate with pending controls, navigation/unload guards, and the dirty-publish guard. Failed uploads retain the selected file for retry or discard. Metadata and block drafts survive each other's saves.
- composer ci:check passed: formatting/lint, TypeScript, Pint, PHPStan, **204 tests / 1,868 assertions**. Final markup simplification also passed formatting/lint and TypeScript; git diff --check passed.
- Live Chrome checks against the running Herd app passed with a temporary account: shared/page control separation, Home metadata preservation and immutable root, default-title preview, normalized save, duplicate-path focus, canceled navigation, image validation/focus, selected-page image clearing, HTTP save failure with draft preservation, retry/pending controls, dirty-publish protection, and independent block/metadata draft saves. Desktop light and 390px mobile dark screenshots were inspected; no horizontal overflow or page runtime errors. HTTP failure/pending states were deliberately intercepted. No successful remote upload was attempted; backend upload/storage behavior remains covered by Pest. Temporary account/records and credentials were removed.
- Evidence screenshots: /private/tmp/churchsite-settings-desktop.png and /private/tmp/churchsite-settings-mobile.png. Manual try path: dashboard -> site settings -> open Home or another page -> Page settings. Additional pages remain draft-only until step 3.
- No commit, merge, push, build, or independent review in this step. Next: implement whole-site snapshots and public page routes after step review approval.

### Step 2 follow-up — collapsible page settings

- User requested that the page editor settings card become collapsible. It starts collapsed, uses a keyboard-accessible button with aria-expanded/aria-controls, and retains form input when hidden. A collapsed card displays an unsaved-changes indicator; validation opens the card and focuses the invalid field. Shared site settings cards remain static.
- Frontend formatting/lint and TypeScript passed. Live mobile Chrome verified initial collapsed state, Enter/Space toggling, preserved drafts, the dirty indicator, canceled navigation with a collapsed draft, visible validation focus, no overflow, and no runtime errors. Temporary local fixture and credentials removed.

### Step 3 — ready for review

- Publish site captures version-2 snapshots containing shared site data, ordered pages with their stable IDs/paths/metadata/blocks, and a deduplicated list of all referenced site-owned media. The existing site lock and transaction protect the whole publication. Invalid media/path references fail without replacing the previous snapshot or timestamp.
- Public root and single-segment page routes render only saved snapshots, with per-page title/description/social image/Open Graph URL. Home remains the root regardless of position. Page path `media` coexists with the existing `/media/{asset}` resource route. Unsupported/malformed snapshot structures return 404.
- Version-1 publications are adapted in memory for rendering, never rewritten or supplemented from draft rows. Legacy publications are explicitly marked as awaiting a whole-site publish; incompatible v1/v2 fingerprints are not compared. Version-2 fingerprints include every page's name/path/order/metadata/blocks and additions/deletions. Invalid draft media leaves the editor available for repair.
- Every page editor has Publish site with wording that includes all saved pages/shared settings. Dirty page/block drafts disable publish. Publishing keeps the selected editor; its public link resolves by stable page ID to the published path, including after draft path edits. Newly added pages have no public link until publication. Additional pages are no longer labeled permanently private drafts.
- Existing migration tests still use version-1 publication fixtures and verify byte preservation, while current publication tests assert version-2 structure. Focused new coverage proves legacy rendering/media and explicit upgrade, all-page atomic publication, per-page metadata/title fallback, changed/deleted/reused paths, draft isolation, fingerprints, public media retention/revocation, route coexistence, failure preservation, and malformed-snapshot handling.
- Final composer ci:check passed: formatting/lint, TypeScript, Pint, PHPStan, **228 Pest tests / 2,258 assertions**. git diff --check passed.
- Live Chrome check against the running local app passed: publish from About includes Home and About, keeps selected editor, disables publish with dirty metadata, retains old public URL during a draft path edit, shows retry/pending feedback for an intercepted publication failure, then serves the new URL/title after republish with a 404 at the old URL. Mobile layout had no overflow and no page runtime errors. The test used a temporary local account/site and no remote media uploads; fixtures and credentials were removed afterward.
- No production/user-owned site was published by the checks. No commit, merge, push, build, or independent review in this step. Page navigation and preview page links are next in step 4; final build and independent review remain in step 5.

### Step 4 — ready for review

- Added distinct Pages and On this page navigation in the public header and editor preview. Page links use ordered names, keyboard focus styles, and aria-current="page". Long labels wrap within their container. The editor heading also wraps long unbroken page names.
- Public navigation comes entirely from the published snapshot: names, paths, ordering, deletion, and current-page identity remain frozen until republish. Home stays at the root when reordered. Home-only and blank pages retain page navigation without inventing section links.
- Editor navigation uses the owner's ordered draft page IDs and editor routes, passing through the existing unsaved-page/block guard. Section anchors and hero targets remain scoped to the displayed page's blocks.
- New rendering/response tests cover all three themes, Home away from first, current-page indication, blank/Home-only pages, changed names/paths/order and deleted pages before/after republishing, independent draft navigation, local section targets, and escaped long names.
- composer ci:check passed: frontend formatting/lint, TypeScript, Pint, PHPStan, **232 tests / 2,353 assertions**. git diff --check passed.
- Live Chrome checks passed: ordered preview/public links, selected-page markers, private editor destinations, keyboard Enter navigation, cancel/accept of metadata and block discard dialogs, local section anchors, Home away from first, blank pages, and 240-character unbroken page names. Mobile dark editor and public navigation had no horizontal overflow or runtime errors; screenshots were inspected. Temporary account/site/credentials removed; no remote media objects created.
- Evidence: /private/tmp/churchsite-navigation-preview.png, /private/tmp/churchsite-navigation-preview-mobile.png, /private/tmp/churchsite-navigation-public-mobile.png. Manual try: open a page, use Pages in its preview, publish, then use Pages on the public site; On this page stays within the current page.
- No commit, merge, push, build, or independent-review checkpoint in this step. Step 5 remains final verification/build and the approved immutable checkpoint for independent review.

### Step 4 follow-up — page links only

- User requested removal of the On this page menus and the visible Pages heading. Both preview and published headers now show only page links; accessible navigation names and current-page indicators remain. Hero buttons can still target the existing local section anchors.
- Removed unused section-menu label code. Updated the active spec and rendering expectations to match this request.
- Frontend formatting/lint, TypeScript, Pint, 12 focused navigation/public-rendering tests (222 assertions), and git diff --check passed. No commit or publication action performed.

### Step 4 follow-up — text navigation and compact logo

- User requested plain page links with the active link underlined, navigation on the right of the header, and a 75px-high proportional logo. Applied to both the editor preview and published site. Removed link borders/backgrounds/rounded button styling; retained keyboard focus outlines and active-page semantics. Headers wrap on narrow screens while navigation remains right aligned.
- Frontend formatting/lint, TypeScript, 12 focused navigation/public-rendering tests (222 assertions), and git diff --check passed. Live Chrome measured a 2:1 test logo at 150×75px in preview/public headers at desktop/mobile sizes; confirmed transparent borderless text links, only active-link underline, right alignment, no overflow, and no runtime errors. Header screenshots inspected: /private/tmp/churchsite-header-desktop.png and /private/tmp/churchsite-header-mobile.png. The synthetic logo was intercepted locally; no remote upload. Temporary local account/site/credentials removed.

### Step 4 follow-up — mobile menu drawer

- User requested a hamburger on mobile that opens a drawer from the right, with an X to dismiss. Both preview and public headers now use that pattern below 640px; desktop text links remain right aligned. Mobile links retain the current-page underline and existing editor navigation guards.
- A shared native-dialog controller handles focus trapping/return, Escape, backdrop dismissal, scroll locking/restoration, and closing when resizing to desktop. Entry animation respects reduced-motion preferences. The public renderer loads a small dedicated Vite entry instead of the editor application.
- Frontend formatting/lint, TypeScript, production build, 12 focused navigation/public-rendering tests (222 assertions), and diff whitespace checks passed. Browser checks of the actual rendered public header with built CSS and the shared controller verified right alignment/full height, X/Escape/backdrop dismissal, keyboard focus containment/return, scroll restoration, desktop reset, and no horizontal overflow. Screenshot inspected: /private/tmp/churchsite-mobile-drawer.png.
- Live authenticated checks could not complete because Chrome stalled loading development assets. The isolated browser check does not establish end-to-end editor navigation; its existing Inertia guard remains in place. Temporary local test records and credentials removed afterward. No commit, merge, or push performed.

### Step 5 — implementation verified; checkpoint approval pending

- User approved the completed navigation/mobile refinements and authorized final verification. Reconciled the active contract with the requested mobile drawer and removed outdated section-menu acceptance wording.
- Final composer ci:check passed: frontend formatting/lint and TypeScript, Pint, PHPStan, 232 Pest tests / 2,359 assertions. npm run build passed. The build reports the existing optional Fontaine font-fallback advisory; no dependency was added. Regenerated Wayfinder files were formatted after the build.
- Earlier live owner/public evidence covers shared settings, per-page metadata, guarded navigation, whole-site publishing, and changed paths. HTTP tests cover page deletion/republishing and version-1 preservation. The latest drawer has isolated rendered-browser evidence; live authenticated drawer verification remains limited by development-asset loading, as recorded above.
- The proposed checkpoint consists of the navigation-and-publishing application code, migration, generated route helpers, focused tests, Vite entry configuration, and this verified spec. Generated build output and local public/hot and public/fonts-manifest.dev.json changes are excluded; the latter predated final verification and support the running local development server.
- Independent review is required and not yet requested: await explicit approval to create the immutable application-code checkpoint, then prepare the automatic fresh-reviewer handoff. This checkbox records implementation verification, not completion of independent review. No commit, merge, or push has occurred.
- Findings ledger has one pre-existing open P2 muted-text contrast finding (F-02), outside the approved scope; no P0/P1 findings are recorded. Regular Audit, Check, and try guide are manual under the current configuration.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":33838,"specSha256":"451ea7b20234f709c39c100d99f8c77dee012a5207ee9be4cfccf500f95b211f","branch":"refs/heads/feature/navigation-and-publishing","head":"84987f0b76ccac27899ec5a1391894c52ad7857a","baseRef":"refs/heads/main","baseCommit":"8155f431724a1ca198629da4f0634c9167e195af","sourceTree":"999aef801f076043b54a73164cd6d09c4ae775bb","absentOptional":[]} -->

## Findings

### 8b/F-03 [P2] closed - Format the verified spec so the required check passes

**File:** blueprint/context/current-feature.md:202
**Found:** 2026-09-27 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** Running `npm run check` on checkpoint cd7a01e36459e515b94b20e75d653d0101e51ab7 exits 1 and identifies this committed spec as the one file with formatting issues. The extra blank line before Step 5 is visible in the checkpoint. This prevents the required `composer ci:check` gate from passing even though the product typechecks and PHP tests pass, so the recorded final verification does not establish the checkpoint's current state.
**Suggested fix:** Apply the repository's existing formatter to this spec only, rerun the required checks, and prepare a new checkpoint/request because the tracked spec and its hash change. No application behavior change is needed.
**Resolution:** Removed the extra blank line using the existing formatter. composer ci:check passed afterward: formatting/lint, TypeScript, Pint, PHPStan, and 232 tests / 2,359 assertions. Independent re-review at 84987f0b76ccac27899ec5a1391894c52ad7857a confirmed the extra blank line is gone and the exact spec hash is 451ea7b20234f709c39c100d99f8c77dee012a5207ee9be4cfccf500f95b211f. All 47 changed committed files match the installed-dependency checkout byte-for-byte. Fresh composer ci:check passed there: frontend format/lint, TypeScript, Pint, PHPStan, and 232 tests / 2,359 assertions. The complete checkpoint delta was reviewed and no repair regression found. Closed by this independent pass.

## Independent review

# Independent Review

**Status:** passed
**Target commit:** 84987f0b76ccac27899ec5a1391894c52ad7857a
**Base commit:** 8155f431724a1ca198629da4f0634c9167e195af
**Base ref:** main
**Spec hash:** 451ea7b20234f709c39c100d99f8c77dee012a5207ee9be4cfccf500f95b211f
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-27T00:25:24.694787+00:00
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-27T00:29:06.065364+00:00
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Commands

- `git rev-parse HEAD`, `git merge-base main HEAD`, `git status --short`, and SHA-256 of `blueprint/context/current-feature.md`: pass; exact target, base, spec hash, and only the two permitted evidence paths differ in the review worktree.
- Byte comparison of all 47 changed committed files between the review worktree and `/Users/timjossund/Herd/churchsiteappnew`: pass. The original checkout also has HEAD at the target; only findings/review and existing `public/hot` and `public/fonts-manifest.dev.json` differ there. The original checkout was used only for its installed dependencies; its local asset files were preserved.
- `composer ci:check` in the matching original checkout, with local worker-socket permission: pass. Frontend format check (204 files), frontend lint (69 files), TypeScript, Pint, PHPStan (zero errors), and Pest (232 passed tests, 2,359 assertions).
- `git diff --check main...HEAD`: pass.
- Targeted test-source searches: no skipped, focused, or placeholder tests identified in the reviewed suite.
- Fresh source-token contrast calculation: reproduces F-02 at 4.16:1, 3.86:1, and 3.97:1.
- `npm run build`: not rerun; review instructions prohibit builds. Existing recorded build evidence was read but is not independent build execution.

## Evidence

- Independently reviewed the complete `8155f431724a1ca198629da4f0634c9167e195af..84987f0b76ccac27899ec5a1391894c52ad7857a` delta against local `main`, the verified spec, AGENTS.md, project overview, coding standards, workflow configuration, and the local Audit contract. This reviewer started without the builder conversation; the existing ledger did not define the scope.
- Backend scope includes snapshot construction/fingerprints, public page and media resolution, site/page/media controllers and requests, models, path generation, the additive settings migration, public and authenticated routes, plus adjacent publication locking, upload cleanup, editor-return middleware, and ownership paths.
- Frontend scope includes PageSettings, PageManager, both site editor/settings pages, shared menu lifecycle, published entry, public Blade rendering, CSS, Vite configuration, and generated Wayfinder route/action bindings. Generated bindings were checked against their route contracts; vendor code, node_modules, build output, caches, and unrelated application areas were excluded.
- Quality/security review verified page-scoped metadata writes, stable Home identity/root, site-scoped uniqueness, re-resolution under the site lock, upload cleanup, frozen legacy adaptation, escaped page names and metadata, safe local hero links, media allowlists, immutable published site slug, and no draft page lookup on visitor routes.
- Performance review traced eager page/block loading, media deduplication, whole-site fingerprint construction, public snapshot validation, and menu listener/scroll cleanup. No concrete new performance defect was found for the established requirements; no runtime profile was claimed.
- Tests review includes all changed publishing, legacy, navigation, page foundation/management/settings, publication settings, and social-image tests. Fresh passing tests exercise migration preservation, invalid/duplicate paths, authorization, stale writes, failed uploads, atomic publication, draft isolation, deleted/renamed paths, metadata, navigation, escaping, and public media retention/revocation.
- F-03 repair was re-examined in the exact reviewed spec and independently verified by the passing formatter and combined check. F-02 was freshly recalculated and remains the existing nonblocking issue.

## Findings

- No new confirmed findings across quality, security, performance, or tests.
- F-03 [P2]: closed by this review; formatting repair confirmed without a new regression.
- F-02 [P2]: remains open; existing muted workspace text contrast issue. No finding was accepted or waived.
- No open or fixed P0/P1 findings remain. No blocking repair order is required.

## Remaining risk

- No independent `npm run build` execution in this pass because the review handoff prohibited builds. The verified spec records a prior passing build and an optional font-fallback advisory; that remains builder-recorded evidence.
- No live browser flow was exercised by this reviewer, and no browser harness is configured. The spec's earlier live and isolated drawer evidence was read, including its stated limit on authenticated drawer verification caused by development-asset loading. Source review and HTTP tests do not prove visual layout or live focus/navigation behavior.
- Pest uses SQLite in memory and fake storage. Passing tests do not establish live MySQL lock/concurrency behavior or real object-storage integration; no performance profile or external dependency vulnerability scan was run.
- F-02 remains an open P2 contrast issue outside the active feature's repair scope.
