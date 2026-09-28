# Feature: Hero blocks

**From build-plan:** feature 10
**Build attempt:** 1
**Status:** verified
**Branch:** `feature/hero-blocks`
**Archive:** `blueprint/history/features/10-hero-blocks.md`

## Goal

Let owners build image-backed heroes with readable text, preset heights, two optional buttons, and normal, fixed, or half-speed background motion. Preserve the current hero appearance and published content until owners explicitly change and publish their drafts.

## Design reference

Extend the existing hero and side-panel controls in `resources/js/pages/Sites/Show.vue` and the matching Blade hero. There is no `prototypes/` directory or supplied visual reference; this is an extension of the existing design, not visual replication. Reuse `resources/css/app.css` theme tokens.

## In scope

- User-approved follow-up: place Site publishing heading and actions opposite the page title on the same surface, without card styling. Group Publish site and conditional View published page buttons together; stack naturally on mobile and retain publishing status, errors, and unsaved-edit guards.

- Upload, replace, and clear a hero background through the existing site image workflow, retaining its JPEG/PNG and 5 MiB limits and private storage.
- User-approved editor addition: group all block types into native collapsible Content and Background & layout sections, plus Buttons when supported. Content opens initially; multiple groups may stay open. Preserve unsaved fields when collapsed and open/focus sections containing validation errors. Image/text-image content keeps its image controls in Content; hero backgrounds belong under Background & layout.
- Editable welcome label. A missing value displays the existing `Welcome`; an explicitly empty value hides the label.
- Height presets: current content-driven height, medium with a 60vh minimum, and full-screen with a 100vh minimum. Long content must expand the section rather than clip. Viewport heights describe the hero alone, without subtracting the site header.
- Light, medium, and dark overlay presets for image-backed heroes. Calibrate their fixed presentation values during visual verification; these are preset identifiers, not arbitrary CSS or owner-entered opacity. Keep text, buttons, and focus indicators readable over bright and dark images in every theme.
- Background modes: normal page scrolling, viewport-fixed, and half-speed parallax. Only the image moves differently; text and buttons scroll with the section.
- Add an optional second button and allow both buttons to target a section on the current page, a page within the site, or an external HTTP(S) URL. Either button may be disabled independently.
- Matching editor preview, platform publication, and custom-domain rendering. Reduced-motion preferences disable both fixed and half-speed effects in favor of a normal static section image.

## Out of scope

- Shared styling controls from Feature 11, image crop controls from Feature 12, site font/accent/button customization from Feature 14, and rich text from Feature 15.
- Video backgrounds, arbitrary height or opacity controls, additional upload formats, a new media library, and cross-page section targets.
- Redesigning other blocks, installing a browser test runner, deployment, or remote changes.

## Build loop

`workflow.stepReview` is `every`; `workflow.checkpointCommits` is `enabled`. Implement and verify one step, then pause for review and the optional checkpoint choice. Do not implement until this spec is approved. `/complete` creates the final feature commit. Preserve pre-existing generated Wayfinder changes and the separate roadmap edits.

## Build steps

- [x] **Extend hero data and image handling.** Add optional hero fields and strict validation, enable hero uploads in both request authorization and controller assignment, and retain the existing image failure/cleanup behavior. Add persistence, ownership, and upload tests. **Done when:** legacy hero payloads still save unchanged; valid hero settings and same-site images persist; invalid presets and cross-site image references are rejected; failed uploads retain the previous image and report a field error; focused Pest coverage passes.
- [x] **Support both buttons and page targets.** Extend validation and transactional save checks, page/block deletion handling, and public link resolution. Keep primary fields compatible with existing heroes and snapshots. **Done when:** either button can independently link to a valid current-page section, same-site page including Home, or HTTP(S) URL; wrong-site/wrong-page targets are rejected; renamed paths resolve after Publish; deleted targets do not leave active broken buttons; focused persistence, page-management, and published-link tests pass.
- [x] **Add hero editing and static rendering.** Wire upload state, label, heights, overlays, motion selector, and both button editors into the existing save/discard flow. Render the static image-backed hero in Vue and Blade before enabling motion. **Done when:** controls have labels and associated errors, invalid fields receive focus, pending operations prevent conflicting saves, upload errors are retryable, discard restores the saved draft, and default/no-image/long-content states remain usable; preview and published markup agree on saved options and snapshot isolation tests pass.
- [x] **Implement background motion in both renderers.** Share a small native TypeScript motion utility between the Vue preview and `resources/js/published.ts`, using the actual scrolling context, clipping to each hero, resize/image-load updates, and cleanup on unmount or reinitialization. **Done when:** measured image movement for page scroll delta D is -D for normal, 0 for fixed, and -0.5D for half-speed while section content moves -D; effects work for heroes below the fold and multiple heroes without exposing blank image edges; reduced motion and unavailable JavaScript retain readable static content; the built public bundle loads through the existing custom-domain asset path.
- [x] **Verify the complete feature and present review.** Run `composer ci:check` and a browser walkthrough across all three themes, desktop/mobile layouts, platform/custom-domain routes where available, image failures, both links, resizing, reduced motion, save/discard, and Publish. **Done when:** automated checks pass, observable UI/motion evidence is recorded, preview and published results match, and any unavailable live custom-domain verification is explicitly reported rather than claimed. Present the review packet and optional code walkthrough.

## Files / areas

- `app/Http/Requests/UpdateSiteBlockRequest.php` and `StoreSiteImageRequest.php`: hero allowlists, presets, link validation, and upload eligibility.
- `app/Http/Controllers/SiteBlockController.php`, `SiteMediaController.php`, and `SitePageController.php`: defaults, transactional references, hero uploads, and deletion cleanup.
- `app/Actions/BuildSitePublicationSnapshot.php`: existing snapshot/media capture, with regression coverage for new hero fields.
- `app/Http/Controllers/PublishedSiteController.php`: safe primary/secondary link resolution against published pages and media.
- `resources/js/pages/Sites/Show.vue`, `resources/views/sites/published.blade.php`, and `resources/css/app.css`: editor controls and matching hero presentation.
- New focused utility under `resources/js/lib/`, imported by the editor and `resources/js/published.ts`: motion lifecycle. `app/Support/PublishedAssets.php` already traverses public bundle imports; verify compatibility rather than adding another delivery mechanism.
- Existing Pest areas: `SiteBlockPersistenceTest.php`, `SiteBlockEditorTest.php`, `SiteMediaUploadTest.php`, `SitePageManagementTest.php`, `SitePublishingTest.php`, and `PublishedSiteTest.php` under `tests/Feature/`.

## Data / contracts

- Keep optional additions in the existing block `content` JSON; no new table or publication schema version is needed solely for these fields. Preserve the primary `heading`, `body`, `button_label`, `link_type`, `target_block_id`, and `external_url` fields.
- Add `welcome_label` (string), `media_asset_id` (nullable integer), and `target_page_id` (nullable integer) to hero content. Missing label means `Welcome`; empty means hidden. Missing image/page references mean null.
- Extend hero `style` with `height: current|medium|full`, `overlay: light|medium|dark`, and `motion: normal|fixed|half`. Missing height/motion retain current/normal behavior. Image-backed heroes initially use the medium overlay; without an image, overlay and motion have no visual effect. Existing alignment and background choices continue to work. Unknown keys and enum values are rejected; non-hero blocks cannot submit hero-only style keys.
- Add optional `secondary_button` containing `button_label` (string), `link_type` (`none|section|page|external`), `target_block_id` (nullable integer), `target_page_id` (nullable integer), and `external_url` (string). Missing object means disabled. Add `page` to the primary link enum. Use the same validation rules for both buttons: enabled links require a nonblank label and their matching destination; inactive destinations normalize to null/empty. Render labels as escaped text.
- Source ownership from the authenticated user's site and its page/block relationships. Image references must belong to that site. Section targets must be another block on the current page; page targets must belong to the site. Recheck referenced targets inside the existing site-locked transaction so concurrent deletion cannot persist a stale target.
- On section/page deletion, disable affected draft buttons under the same site lock, following existing primary section-link cleanup behavior. Published links continue using the old snapshot until Publish. Resolve page targets by stable page ID against the publication snapshot, using its saved paths and existing platform/custom-host URL builder. Missing published targets produce no link. External links retain HTTP(S)-only validation and the existing safe new-tab behavior.
- Preserve the image upload lifecycle: upload/replacement saves a draft asset through the existing route; clearing takes effect with block Save. Never delete an old storage object as part of clearing/replacement because publication may still reference it. Use snapshot media URLs publicly, not authenticated editor URLs or arbitrary remote image URLs.
- Background images are decorative presentation behind the hero's text; retain asset metadata but do not duplicate it as announced hero content. A missing/failed image falls back to a readable theme surface. Ensure image-backed text contrast and keyboard focus remain usable for every overlay preset, adding a local text backing if needed rather than assuming an overlay guarantees contrast.
- Motion is progressive enhancement, with normal static rendering before initialization or when scripts fail. Half-speed means the image's viewport displacement is half that of normal content (a +0.5D compensation relative to a section moving -D). Fixed means zero viewport displacement. Clip the image layer to the hero, retain sufficient coverage, recalculate after resize/load, and use animation-frame scheduling with listener cleanup. Respect reduced-motion changes at runtime and avoid hijacking scrolling. Apply the same contract to the editor's scrolling viewport.

## Testing

- Baseline `composer ci:check` passed before spec creation: frontend lint/format/typecheck, PHP format/static analysis, production build, and 517 Pest tests with 4,033 assertions. The first sandboxed attempt stopped at Pint's local worker socket permission; the permitted retry passed. This is baseline evidence, not implementation verification.
- Add focused regression coverage for legacy payloads/snapshots, valid/invalid presets, denied ownership, upload failure, both button destinations, concurrent target rechecks, target removal, path changes, published snapshot stability, and public media/asset delivery.
- Verify actual motion and visual contrast through direct browser evidence; markup assertions alone do not prove scroll speed. No browser test harness is configured. Record measurable scroll displacement and reduced-motion fallback during the walkthrough.
- The final automated gate is `composer ci:check`. Do not add tests that merely mirror CSS constants; test persisted contracts, boundary behavior, and public rendering outcomes.

### Step 1 evidence

- `php artisan test tests/Feature/HeroBlockTest.php tests/Feature/SiteMediaUploadTest.php tests/Feature/SiteBlockEditorTest.php tests/Feature/SiteBlockPersistenceTest.php`: 51 tests passed, 325 assertions.
- `vendor/bin/pint --test` on the five changed PHP files passed. `composer types:check` passed with zero errors after the local worker socket permission retry. `git diff --check` passed.
- Hero presentation and controls are intentionally deferred to Step 3; Step 1 is verified through request, persistence, storage-failure, and snapshot tests. No commit created.

### Step 2 evidence

- `php artisan test tests/Feature/HeroBlockTest.php tests/Feature/ChurchDetailBlockTest.php tests/Feature/SiteBlockEditorTest.php tests/Feature/PublishedSiteTest.php tests/Feature/SitePageManagementTest.php`: 95 tests passed, 938 assertions.
- PHP formatting passed on changed files; `composer types:check` passed with zero errors; `git diff --check` passed.
- Coverage includes both button destinations, cross-site/cross-page rejection, deletion cleanup, snapshot path stability, custom-domain URL resolution, missing destinations after validation, and inactive-field normalization. Old rejection cases for inactive fields were replaced with normalization assertions matching this spec.
- Button editor controls and secondary-button markup remain in Step 3. No commit created.

### Step 3 evidence

- Implemented hero image editing, welcome label, preset controls, page target selection, secondary button fields, dirty/save/discard state, error focus, and static Vue/Blade presentation. Motion selection is stored but animation remains Step 4.
- `npm run check:fix`, `npm run types:check`, and `npm run build` passed. Focused HeroBlock, PublishedSite, and SiteBlockEditor Pest suites passed: 62 tests, 380 assertions. PHP formatting for the added test passed.
- Published rendering tests cover escaped labels, two links, image/preset markup, hidden label, and draft/publication isolation.
- Live browser visual/interaction evidence is not yet captured. Leave Step 3 unchecked pending review of upload, presets, both buttons, Save/Discard, and mobile presentation in the running editor. No commit created.

### Editor reload repair

- User reported native Leave/Stay dialogs after image upload and Save, followed by lost editor state. Inertia inherited production-manifest versioning even during Vite development, allowing verification builds to trigger a hard reload on the redirected GET.
- `HandleInertiaRequests::version` now returns no asset version while Vite is hot; production retains parent version handling. Focused development/production reload regression tests and hero tests passed: 35 tests, 161 assertions. PHP formatting passed.
- The existing open tab needs one deliberate refresh after preserving any unsaved text. User confirmation of the real upload/save flow is pending; do not claim earlier unsaved text was recovered.

### Hero preset binding repair

- Reproduced Vue cloning a reactive object passed through its reserved `style` prop: the child height changed while the parent draft stayed unchanged. Renamed the HeroOptions named model to `appearance` at all three section instances. Persisted JSON remains `content.style`.
- A one-off Vue runtime check compiled and mounted the actual HeroOptions setup with the installed compiler and renderer, then verified height, overlay, and motion changes reach the parent draft. It passed; this is model-binding evidence, not a live browser walkthrough.
- `npm run check`, `npm run types:check`, and HeroBlock Pest tests passed (33 tests, 148 assertions). User confirmation of Save/reload in the real editor remains pending.

### Hero dropdown event follow-up

- User still observed no dirty state after the binding rename. The current source and served Vite modules contain the new binding; the exact open-browser state has not been captured.
- Replaced nested preset mutation with an explicit select change handler assigning a new appearance model object, emitting the parent model update.
- A one-off check compiled the actual component template and setup, mounted it with Vue, dispatched real registered select change handlers for height/overlay/motion, and asserted parent draft changes and dirty-state activation. The check deliberately passes detached props to verify explicit event propagation. It passed. This does not substitute for user confirmation in the live editor.

### Full editor browser verification

- Used bundled Playwright with a separate installed headless Chrome session, actual Vite app assets, and synthetic Sites/Show page data. No browser runner was installed and no real site writes were made.
- In the full editor, changing height to full, overlay to dark, and motion to half each enabled Save. Intercepted Save submitted those exact three values and reached Block saved without reload.
- Chrome blocked Vite WebSocket live updates in the isolated session. A stale open tab is a hypothesis for the remaining user report, not confirmed access to their browser. Rebuilt assets; requested one full refresh after preserving unsaved text.

### Direct hero editor controls

- User confirmed heading edits enable Save but hero preset edits do not, without console errors. Moved all three preset selects directly into Sites/Show with `v-model` on `draftBlockStyle`, matching the existing working alignment controls. Removed the child appearance model and preset event handler.
- Browser check used actual site 2/page 2 Laravel SSR HTML in an isolated session. Each select independently enabled Save; restoring its saved value disabled Save again. Intercepted Save contained full/dark/half. No actual site content was modified.
- Frontend checks, TypeScript, build, and 33 hero tests passed. User-browser confirmation remains pending.

- User confirmed the completed static editor works after reviewing it with the understanding that motion comes in Step 4. Step 3 visual review is accepted.

### Step 4 evidence

- Shared native motion utility now powers the Vue preview and published bundle, with animation-frame scheduling, scroll-container detection, resize/image-load handling, runtime reduced-motion support, and cleanup on preview changes/unmount.
- Isolated Chrome browser measurements using actual Vite utility: a 120px scroll moved normal images -120px, fixed images 0px, and half-speed images -60px; text moved -120px in all modes. Multiple heroes, taller-than-viewport coverage, nested scroll containers, reduced-motion changes, resize, and disposal passed.
- Full Sites/Show browser check with actual page props and a synthetic image measured -100px/0px/-50px background displacement for a 100px scroll. Switching the real editor motion control updated the effect without runtime exceptions. No real site writes were made.
- Frontend formatting, TypeScript, and production build passed. HeroBlock and CustomerDomainTransport suites passed: 51 tests, 403 assertions, including compiled published asset delivery through customer domains. These are isolated browser and automated test results; user review of motion is pending.
- No commit created. Final full verification remains Step 5 after Step 4 review.

### Final verification and review handoff

- User approved Step 4 after trying parallax in the running editor.
- `composer ci:check` passed: frontend formatting/lint and TypeScript, PHP formatting and PHPStan, production build, and 553 Pest tests with 4,214 assertions. `git diff --check` passed.
- Browser walkthrough used the actual published Blade template with synthetic data and the production CSS/JS bundles, without writing site data. Warm, clean, and bold themes passed at 390px and 1440px widths with all overlay presets; screenshots were visually inspected. Text retains white foreground over the 75% black backing; buttons retain dark text on white. Long content expanded without horizontal overflow. Built public JavaScript initialized motion, responded to reduced motion, and restored theme styling on image failure without runtime errors. Both rendered button destinations and external-link attributes were checked.
- Prior editor browser checks cover Save payload/dirty state and motion, and the user accepted the real editor behavior. Persistence, publication snapshot isolation, uploads, and custom-domain asset routing passed the automated suite. No live customer-domain browser visit or live Publish action was performed; synthetic rendering and request tests do not establish deployed DNS/provider behavior.
- Proportionality review: one native motion helper shares the required behavior; no dependency, schema migration, or new service was added.
- Regular Audit, Check, and try-guide are manual and were not separately invoked. Independent review is selected because persisted link relationships and image ownership validation changed. No current receipt exists. Existing F-02 and F-06 are unrelated open P2 findings; there are no recorded blocking P0/P1 findings.

### Publishing layout follow-up

- Moved Site publishing into the page heading row on the existing surface, with Publish site and the live page link together beneath its heading. Removed the card border, background, rounding, padding, and shadow. Mobile stacks the publishing controls below the title.
- Retained all pending-edit guards, status/error messages, and existing publish handler. The page link appears only with a published site and a published URL for the selected page.
- Frontend format/lint and TypeScript passed. Isolated Chrome verified live/draft link visibility, desktop positioning, mobile stacking without horizontal overflow, and no card background/shadow. No site writes or publication occurred.
- User approved the previous checkpoint proposal but requested this adjustment first. Present the layout for review before creating the checkpoint; refresh the final gate for the changed source.

### Exact proposed independent-review checkpoint

Commit message: `Add hero backgrounds, styling, links, and parallax`

Only these files are proposed for the local checkpoint:

- `app/Http/Controllers/PublishedSiteController.php`
- `app/Http/Controllers/SiteBlockController.php`
- `app/Http/Controllers/SiteMediaController.php`
- `app/Http/Controllers/SitePageController.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Requests/StoreSiteImageRequest.php`
- `app/Http/Requests/UpdateSiteBlockRequest.php`
- `app/Models/SiteBlock.php`
- `resources/css/app.css`
- `resources/js/components/sites/HeroOptions.vue`
- `resources/js/lib/hero-motion.ts`
- `resources/js/pages/Sites/Show.vue`
- `resources/js/published.ts`
- `resources/views/sites/published.blade.php`
- `tests/Feature/ChurchDetailBlockTest.php`
- `tests/Feature/HeroBlockTest.php`
- `tests/Feature/InertiaAssetVersionTest.php`
- `tests/Feature/SiteMediaUploadTest.php`
- `blueprint/context/current-feature.md`

Generated Wayfinder/build/font files and separate roadmap/overview edits remain outside this checkpoint. No commit, merge, push, or deployment has been performed. After explicit checkpoint approval, run `/audit independent current` using the configured automatic isolated reviewer.

## Notes for the AI

- Critique added explicit target-deletion handling, snapshot-based page URLs, custom-domain script reachability, image-failure fallback, and motion lifecycle checks.
- Keep old heroes visually unchanged when new fields are absent. Use existing Tailwind/theme patterns and native browser capabilities; no animation dependency is required.
- The requested preset names and heights are settled. Exact overlay presentation values are visual tuning within these presets, to be checked against bright/dark images and all themes before acceptance.
- Feature identity is a first build: no Feature 10 archive metadata, no prior use of the planned archive path in available Git history, and no conflicting local branch were found. The archive leaf is absent and its parent directories are ordinary directories. Freeze the branch and archive above.

### Publishing controls simplification

- User requested removing the publishing heading and text beneath the buttons, and shortening the live-page link to `View page →`. Removed routine status/helper copy and retained transient publish success/error feedback and existing save guards. Publishing controls retain an accessible region label; the arrow is decorative.

### Approved final checkpoint

- User accepted the simplified publishing controls and requested continuation. Existing checkpoint approval applies to the same listed files including the accepted layout edits. Refreshed `composer ci:check` passed with 553 tests and 4,214 assertions, frontend lint/format/TypeScript, PHP formatting/PHPStan, and production build.
- Independent review uses a clean isolated checkout of this tracked spec and approved product checkpoint, preserving unrelated generated files and roadmap edits in the original checkout.

### Preview link repair

- User requested repair of F-07. Use Inertia Link for same-site page destinations so the existing navigation guard protects pending edits; native anchors for section links and safe new-tab external links. Applies to both hero buttons.
- [x] Verify section scrolling, external new-tab navigation, and page navigation with unsaved-edit cancellation; rerun final checks.

- Isolated Chrome exercised both primary and secondary buttons for all three destinations: keyboard section scrolling, safe external popup navigation, cancelled page navigation with unsaved changes, and clean Inertia page navigation. All six scenarios passed without runtime exceptions or real site writes.
- Final `composer ci:check` passed: 553 tests, 4,214 assertions, frontend lint/format and TypeScript, PHP formatting/PHPStan, and production build. `git diff --check` passed.
- Proposed repair checkpoint: `Fix hero preview button navigation`, containing only `resources/js/pages/Sites/Show.vue`, `blueprint/context/current-feature.md`, and `blueprint/context/findings.md`. The earlier review receipt is stale for this repair; approval for the new checkpoint and full fresh independent review is pending.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":26653,"specSha256":"64d9012abed5542b45c77a7d99394476292d5fd603edad31631d8e641a30799c","branch":"refs/heads/feature/hero-completion","head":"582efe81d16f8a13b2d4eea97989d03543f2be60","baseRef":"refs/heads/main","baseCommit":"69c5f3f85a32601eae4489cbe73661b82b4c09b2","sourceTree":"781d8bfcd4eda4c0bad2dcf26d5c37a0d82fea8a","absentOptional":[]} -->

## Findings

### 10/F-07 [P2] closed - Preserve working hero links in the editor preview

**File:** resources/js/pages/Sites/Show.vue:2019
**Found:** 2026-09-28 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** Every preview hero anchor now has an unconditional `@click.prevent` with no navigation handler. A configured section, page, or external button therefore does nothing on ordinary mouse or keyboard activation. Before this change, the primary section link scrolled and the external link opened in a new tab; the neighboring preview page-navigation links remain operable. Owners cannot try either hero destination in the editor. Published links are unaffected, so this is a nonblocking preview regression. Compiling the exact Sites/Show template confirms Vue generates `withModifiers(() => {}, ["prevent"])`; invoking that modifier cancels the event with no replacement action.
**Suggested fix:** Preserve normal section navigation and restore safe new-tab attributes for external preview links. Use the existing Inertia navigation/unsaved-edit guard for page destinations. Remove the unconditional no-op prevention handler; no new navigation abstraction is needed.
**Resolution:** Confirmed at `f3ee5050a1f7b722d2ed4e2b4438c21772f51eeb` by source comparison and the installed Vue compiler/runtime. No product code changed. No browser click walkthrough was performed in this review.

F-07 repair evidence: native section anchors, safe external new-tab anchors, and Inertia page Links now replace the no-op prevention handler for both buttons. Isolated Chrome verified all six destination/button combinations including unsaved-page cancellation. Final project checks passed with 553 tests and 4,214 assertions. Independent re-review on 2026-09-28 at `15b8234eaf48835ddf1aa30daa5216e3bf6b2ea3` confirmed the repair and closes F-07. The exact current Vue template was compiled and rendered to VNodes for both buttons across all three destination types: section/external use native anchors without cancelling click handlers, external links retain `_blank` and `noopener noreferrer`, and page destinations use Inertia Link. Source tracing confirms those Link visits pass through the existing router before-event and unsaved-edit confirmation guard. Both changed Vue script/template pairs compile. This pass reviewed the complete base-to-target delta and found no new defect from this repair. No fresh browser click walkthrough was performed by this reviewer.

Independent re-review on 2026-09-28 at `582efe81d16f8a13b2d4eea97989d03543f2be60` covered the complete base-to-target delta, including the roadmap additions. F-07 remains closed: both preview button slots retain native section/external anchors and Inertia page Links that pass through the existing navigation guard. The current Vue script/template pairs compile. No new findings were identified. Fresh browser behavior was not exercised; PHP verification and complete TypeScript verification were unavailable in this isolated checkout as recorded in `review.md`.

## Independent review

# Independent Review

**Status:** passed
**Target commit:** 582efe81d16f8a13b2d4eea97989d03543f2be60
**Base commit:** 69c5f3f85a32601eae4489cbe73661b82b4c09b2
**Base ref:** main
**Spec hash:** 64d9012abed5542b45c77a7d99394476292d5fd603edad31631d8e641a30799c
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-28T00:22:29.839764+00:00
**Workflow:** regular
**Check required:** no


**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-28T00:26:18.968107+00:00
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Handoff

Review the active spec and complete `69c5f3f85a32601eae4489cbe73661b82b4c09b2..582efe81d16f8a13b2d4eea97989d03543f2be60` delta in a fresh isolated subagent without the builder conversation. Run all Audit lenses from scratch. Do not edit product code, accept findings, or reuse existing findings as the review scope.

## Commands

- `git rev-parse HEAD`, `git merge-base main HEAD`, `git status --short`, and exact spec SHA-256 comparison: passed; target/base/hash match and only the two permitted evidence files differ.
- `git diff --check main...HEAD`: passed.
- `npm run check`: passed, 141 formatted files and 77 files without lint warnings/errors; initial sandbox cache denial resolved with permitted access.
- `composer lint:check`: passed with permitted local cache/socket access.
- `npm run types:check`: unavailable as a clean verification signal; exits 2 because the isolated checkout lacks generated CustomHostnameController and goLive route exports used by unchanged domain/settings components.
- `composer types:check`: unavailable; Composer autoload exits 255 because `vendor/mtdowling/jmespath.php/src/JmesPath.php` is absent.
- `php artisan test tests/Feature/HeroBlockTest.php tests/Feature/InertiaAssetVersionTest.php tests/Feature/SiteMediaUploadTest.php tests/Feature/ChurchDetailBlockTest.php`: unavailable; the same missing Composer dependency prevents test boot.
- Read-only Node invocation of the installed Vue SFC compiler: passed for both the script and template of `Sites/Show.vue` and `HeroOptions.vue`.
- Read-only roadmap SHA-256 calculation using the Overview contract: passed; the overview fingerprint matches both plans and its size is 13,142 bytes.
- Targeted searches of the four changed test files: no skipped, focused, TODO, or placeholder tests found.

## Evidence

- Reviewed the complete `69c5f3f85a32601eae4489cbe73661b82b4c09b2..582efe81d16f8a13b2d4eea97989d03543f2be60` delta across all four lenses. The review includes all changed controllers, requests, middleware, SiteBlock model, Vue components, native motion utility, published entry point, CSS, Blade template, four test files, active spec, and the project-plan/build-plan/overview additions. Review/findings are evidence, not the product review scope.
- Followed affected ownership, site-locking, image assignment/failure cleanup, publication snapshot/media capture, public URL sanitization, and custom-domain asset traversal through their existing callers. Both button destinations are checked under the site lock; deletion disables affected draft links while public URLs continue to resolve from the frozen snapshot. No new authorization, injection, or snapshot-isolation defect was identified.
- Reviewed editor save/discard and dirty state, native collapsible groups and error revelation, publish controls, both preview links, static image fallback, image-only motion, resize/load scheduling, reduced-motion behavior, and listener disposal. Native section/external anchors and Inertia page Links retain the F-07 repair and existing unsaved-edit guard.
- Performance review found no new database N+1 path or concrete concurrency defect. Motion is scheduled once per animation frame with observer/listener cleanup. No runtime profile or load test is claimed.
- Test source covers legacy optional fields, presets, ownership, upload failure/rollback, both destination types and slots, stale destination rechecks, deletion cleanup, snapshot path isolation, escaped markup, and development/production asset-version handling. Existing spec records builder browser measurements and the 553-test full gate; these are historical builder evidence, not tests rerun by this reviewer.
- Applied repository requirements for minimal changes, Laravel validation and ownership, Vue/TypeScript boundaries, theme/accessibility behavior, existing Pest coverage, and preservation of generated output. No dependency, product, spec, configuration, generated tracked file, or commit was changed by this review.

## Findings

- No new findings across quality, security, performance, or tests.
- F-07 remains closed after fresh source re-review of both preview buttons and navigation guards at this target.
- Existing unrelated F-02 and F-06 remain open P2; neither is accepted or reclassified. No P0/P1 finding is open or fixed.

## Remaining risk

- Fresh full frontend typechecking is unavailable because generated Wayfinder helpers are stale/missing in this isolated checkout (`CustomHostnameController`, `goLive`). These unchanged consumers are outside the target delta; no generated files were repaired here.
- Fresh PHPStan and focused Pest execution are unavailable because the isolated Composer installation lacks JmesPath. Dependencies were not installed or repaired during Audit.
- `npm run build`, `composer test`, and `composer ci:check` were not rerun: their build/generation steps would alter generated tracked files, and PHP boot is already unavailable. The receipt does not replace the builder's final complete verification gate.
- No fresh browser walkthrough, motion measurement, live Publish action, or deployed customer-domain/DNS/provider check was performed. Existing spec evidence was read with its stated synthetic-browser and deployment limitations. Browser automation, security scanning, and performance profiling are not configured verification signals here.
- The roadmap explicitly preserves the earlier discrepancy between completed domain build-plan items and pending deployment-proof notes as an open question; this review does not establish that deployment proof.
