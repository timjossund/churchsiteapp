# Feature: Image and text blocks

**From build-plan:** feature 12
**Build attempt:** 1
**Branch:** feature/image-and-text-blocks
**Status:** verified

## Goal

Give owners useful image presentation choices and optional calls to action in ordinary text blocks, with matching draft previews and published pages. Existing blocks must keep their current appearance and behavior until an owner changes a setting.

## In scope

- Image ratio, vertical crop position, and corner controls for Image and Text and image blocks.
- Optional plain-text captions on those two image-bearing blocks.
- One optional link button on About, Heading and text, Plain text, and Text and image blocks.
- Owner-scoped validation, saved draft state, publication snapshots, published rendering, and focused regression coverage.
- Keep generated frontend output available to local Herd without requiring it in clean Git review checkpoints.

## Out of scope

- Hero image or button changes, extra buttons, and buttons on Service times, Contact, Image, or Video blocks.
- Image editing, focal-point coordinates, new uploads or storage behavior, rich text, site-wide button styling, or new database columns.
- Publishing draft changes automatically.

## Build loop

Work on `feature/image-and-text-blocks`. Complete the steps in order and show each step for review (`workflow.stepReview: every`). After a passing step, offer an optional checkpoint commit (`workflow.checkpointCommits: enabled`); `/complete` creates the final feature commit. Preserve the Herd checkout's generated build and Wayfinder files as local outputs, separate from source commits.

## Build steps

- [x] **1. Image presentation controls.** Validate and save ratio, crop position, and corner options only for Image and Text and image blocks. Add labeled editor controls, draft preview styling, and equivalent Blade/CSS rendering. Preserve upload, image description, save/discard, and existing layout behavior. **Done when:** an owner can select each option, save and reload it, see the same result in the draft preview and after Publish on desktop and narrow screens; absent options preserve existing blocks; invalid values and unsupported block types are rejected; focused request and publication tests pass.
- [x] **2. Image captions.** Add an optional caption field to both image-bearing blocks and render it directly under the image in preview and published output. Keep it independent of the media asset's alt text and preserve it through image replacement or removal. **Done when:** a caption saves and reloads, remains with the image in either Text and image layout, is absent from rendered output when no image is selected, and appears publicly only after Publish; invalid caption input has associated feedback and focused tests pass.
- [x] **3. Optional text-block buttons.** Reuse the existing Hero destination types and owner-scoped destination rules for one button on About, Heading and text, Plain text, and Text and image blocks. Add editor controls, preview, published rendering, and cleanup when a destination is removed. **Done when:** none, same-page section, same-site page, and HTTPS/HTTP external links work for each supported block; stale or foreign destinations cannot produce a live link; save/discard and publication isolation hold; link text and targets render safely with keyboard focus and external-link protections; focused tests and `composer ci:check` pass.
- [x] **4. Repair F-07 button-control visibility.** Remove the inert native template wrapper inside the Buttons panel without changing its supported-block condition. **Done when:** the compiled Vue template exposes the primary controls and Hero's secondary controls in rendered DOM, editor typecheck and formatting pass, and the full project gate passes again.
- [x] **5. Keep Herd assets outside Git review state.** Stop tracking generated Vite build output and Wayfinder helpers while leaving the current files on disk, ignore future output, and make standalone frontend typechecking regenerate its required Wayfinder helpers. Update the affected local/deployment instructions. **Done when:** Herd still serves the approved dashboard and current editor assets, staging's documented build remains the source of deployment assets, a build and typecheck work from generated files absent on a clean checkout, `composer ci:check` passes, and Git no longer reports generated output as source changes.
- [x] **6. Position the Text and image button with its text.** Render its optional CTA inside the text column, directly below the copy, so it stays opposite the image as the image layout changes; match the editor preview and published page. Other text-block buttons keep their current placement. **Done when:** image-left and image-right layouts place the button below the text on the text side in both preview and published HTML, and the focused rendering checks pass.

## Files / areas

- `app/Http/Requests/UpdateSiteBlockRequest.php`, `app/Http/Controllers/SiteBlockController.php`, `app/Models/SiteBlock.php`, and page/block deletion paths for validated content and link cleanup.
- `resources/js/pages/Sites/Show.vue` for controls, form state, preview, error focus, and save/discard behavior.
- `resources/views/sites/published.blade.php`, `resources/css/app.css`, and the existing published-link resolution path for visitor output.
- Existing publication snapshot flow in `app/Actions/BuildSitePublicationSnapshot.php`; no schema migration is expected because block content is stored as structured JSON.
- `tests/Feature/SiteBlockEditorTest.php`, `tests/Feature/PublishedSiteTest.php`, and relevant link/publication tests.
- `.gitignore`, `package.json`, and affected build/deployment guidance for generated output ownership and verification.

## Data / contracts

- Store image options in `content.style` only for `image` and `text_image`: `image_ratio` is `original`, `landscape`, `square`, or `portrait` (labels Original, 16:9, Square, 4:5); `crop_position` is `top`, `center`, or `bottom`; `corner_style` is `current`, `square`, or `rounded`. Missing values mean `original`, `center`, and `current`. Original preserves today's contained image sizing, border, and `rounded-xl` corners; the three fixed ratios use `object-fit: cover` with horizontal center and the selected vertical position. Crop position has no visible effect in Original. Square removes rounding; Rounded uses `rounded-3xl`. The editor must make the Original behavior clear.
- Store `content.caption` as a plain string on `image` and `text_image`, with `''` as the empty/default value. Escape it in Vue and Blade. Render a visible caption only alongside an available image, inside a figure with its image; alt text remains the existing media description and is never replaced by the caption. Preserve a saved caption when an image is temporarily removed so it returns if another image is selected.
- Store one optional button on each supported text block using the existing Hero fields in `content`: `button_label`, `link_type`, `target_block_id`, `target_page_id`, and `external_url`. Missing legacy fields mean no button. For `link_type: none`, store an empty label and URL and null targets; for a selected type, require its label and destination. Section targets must be another block on the same page, page targets must belong to the same site, and external URLs must use HTTP or HTTPS. Resolve public hrefs from the published snapshot, and clear links that target deleted blocks or pages. Render labels as text; external links use `target="_blank"` with `rel="noopener noreferrer"`.
- New content stays in drafts until Publish; existing snapshot serialization carries it without a version or table change. Published pages continue showing the last published choices after later draft edits. Missing or unrecognized legacy style values fall back safely to current presentation.
- Controls use existing labels, help text, error association/announcement, error focus, loading, and save/discard patterns. Keep image content responsive and preserve readable text and usable focus states across themes.

## Testing

- Baseline `composer ci:check` passed before this spec: frontend formatting/lint and type checks, PHP format/static checks, build, and 645 Pest tests with 5,077 assertions. The first sandboxed run could not open Pint's local parallel socket; the permitted retry passed.
- Add focused request tests for allowed and rejected option values, block-type restrictions, cross-site/page destinations, stale destinations, and legacy/default data.
- Add publication tests for draft isolation, missing images, escaped captions/labels, safe external links, image geometry markers, and preview/public contract parity where testable.
- Run `composer ci:check` after implementation. Browser tests are not configured; use a manual local Herd review for image ratios/crops, narrow layouts, keyboard interaction, and the publish boundary when the feature is built.
- Final `composer ci:check` passed after all three steps: frontend formatting/lint and type checks, PHP format/static checks, build, and 697 Pest tests with 5,359 assertions. The owner-facing editor still needs a manual visual pass on the local Herd site.
- After repairing F-07, the Vue template compiler confirmed the Buttons panel has primary, page, and Hero controls without a native template wrapper; `composer ci:check` passed again with 697 Pest tests and 5,359 assertions.
- For Step 5, the exact `composer ci:check` passed after allowing Pint's local parallel worker socket. It includes frontend format/lint and type checks, PHP formatting and PHPStan, a production build, and 697 Pest tests with 5,359 assertions. Standalone `npm run types:check` also generated Wayfinder helpers before typechecking. `public/build/manifest.json` and generated Wayfinder files remain present locally and are ignored by Git.
- Step 6 was requested during review: keep the Text and image CTA in the text column below its copy, opposite the image, in editor preview and published output.
- Step 6's `TextBlockButtonTest` passed with 40 tests and 197 assertions, including both image orientations. The final `composer ci:check` passed with 699 tests and 5,376 assertions.

## Notes for the AI

- Reuse the current image upload, media ownership, Hero link, and publication mechanisms. Do not add a dependency or a second link format.
- Keep the Text and image layout choice independent of its image ratio and caption. Keep Service times and Contact enhancements in Feature 13 and site-wide button styling in Feature 14.
- The current Herd checkout has generated build/Wayfinder changes from serving the previous feature; stage only intentional source/spec changes at any checkpoint.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":10739,"specSha256":"4dc78269a944527b847bd06b51951460fada7ecca0ffaab38a9ddb2ece81ace1","branch":"refs/heads/feature/image-and-text-blocks","head":"069973b71542ed6898039622076fb7b7b9ee70cb","baseRef":"refs/heads/main","baseCommit":"b0fddda7d6d77ce8dd5e39d1aa566ec5b6c3a25a","sourceTree":"741afb7b43a643c802e66ffdc1ab24fb825a3235","absentOptional":[]} -->

## Findings

### 12/F-07 [P1] closed - Remove the native template hiding every button control

**File:** resources/js/pages/Sites/Show.vue:4506
**Found:** 2026-09-28 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** Replacing the conditional wrapper with a bare `<template>` makes Vue emit a native HTML template element rather than a fragment. All controls inside the Buttons section, including the existing HeroOptions component, are inside this non-rendered element. Owners therefore cannot configure any of the new text-block buttons, and existing Hero button editing regresses. The installed Vue compiler confirms the exact current Show.vue emits `_createElementVNode("template", null, [...])` immediately inside the Buttons section. The PHP request/publication tests do not render this editor and cannot catch the regression.
**Suggested fix:** Remove this wrapper and its closing tag, letting the existing conditional details element govern visibility, or use an ordinary rendered container. Verify that opening Buttons exposes usable fields on Hero, About, Heading and text, Plain text, and Text and image, including the Hero secondary button and page destination controls. No dependency or new abstraction is needed.
**Resolution:** Confirmed at `bab970e4880e4b5251232a10bbeb12afc6983e2a` by compiling the current SFC in memory with installed `@vue/compiler-sfc`. Implement removed the native wrapper; the same compiler check now finds primary, page, and Hero controls in the Buttons panel without `_createElementVNode("template", null, [...])`. `composer ci:check` passed after the repair with 697 Pest tests and 5,359 assertions. Marked fixed pending independent re-review.

Independent re-review of F-07 at `9c3278b7ee89a7fa8aa7fe1cb869f9019f88aee3` on 2026-09-28 examined the complete feature delta. The Buttons panel now contains ordinary rendered div elements under its supported-block condition; the installed Vue SFC compiler parses and compiles Show.vue without errors, emits no native template vnode, and includes primary, section, external, page, and HeroOptions controls. HeroOptions remains Hero-only. The original defect is gone and no new defect was found in the repair. F-07 is closed. This is compiler/source evidence, not a browser interaction claim.

Independent review at `069973b71542ed6898039622076fb7b7b9ee70cb` re-examined the Buttons panel source and ran the declared frontend check and TypeScript check successfully. The panel remains visible under its supported-block condition; F-07 stays closed. This pass did not claim browser interaction evidence.

Fresh isolated codex / gpt-6-astra Phase B review at `069973b71542ed6898039622076fb7b7b9ee70cb` on 2026-09-28 re-examined the complete feature delta and compiled Show.vue with the installed Vue SFC compiler: no parse/compile errors, no native template vnode, and primary, page, Hero, and caption controls present. `composer ci:check` passed with 699 tests and 5,376 assertions. F-07 remains closed; no new finding was introduced by its repair. This remains source/compiler evidence rather than live browser acceptance.

## Independent review

**Status:** passed
**Target commit:** 069973b71542ed6898039622076fb7b7b9ee70cb
**Base commit:** b0fddda7d6d77ce8dd5e39d1aa566ec5b6c3a25a
**Base ref:** main
**Spec hash:** 4dc78269a944527b847bd06b51951460fada7ecca0ffaab38a9ddb2ece81ace1
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-28T18:32:25Z
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-28T18:34:42Z
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Commands

- `git status --short`, `git rev-parse HEAD`, `git merge-base main HEAD`, and `shasum -a 256 blueprint/context/current-feature.md`: passed before and after verification; target, base, and spec match the request, and only the two permitted evidence files differ.
- `git diff main...HEAD` with bounded source and test paths: reviewed the complete feature delta, including generated-output removal and its build/deployment contract.
- `composer ci:check`: initial sandbox execution stopped at Pint's localhost worker socket with EPERM; the permitted retry passed frontend formatting/lint, generated Wayfinder helpers, Vue TypeScript checking, Pint, PHPStan, production build, and all 699 Pest tests with 5,376 assertions.
- In-memory `node --input-type=module` using installed `@vue/compiler-sfc` to parse and compile `resources/js/pages/Sites/Show.vue`: passed with no parse or compile errors, no native template vnode, and primary, page, Hero, and caption controls present.
- `git ls-files` and `git check-ignore` for `public/build`, `resources/js/actions`, `resources/js/routes`, and `resources/js/wayfinder`: passed; generated paths are no longer tracked and their current outputs are ignored.
- `rg` for skipped, focused, or placeholder tests in the four changed test files: none found.
- Offline Node relative-luminance calculation for the existing workspace muted token: reproduced F-02's 4.16:1, 3.86:1, and 3.97:1 ratios.
- `git diff --check`: passed.

## Evidence

- Fresh isolated Codex reviewer received only the project-local Phase B handoff, not the builder conversation. Runtime model is gpt-6-astra. Reviewed `b0fddda7d6d77ce8dd5e39d1aa566ec5b6c3a25a..069973b71542ed6898039622076fb7b7b9ee70cb` against the exact verified spec and project standards.
- Reviewed all changed application paths: UpdateSiteBlockRequest, SiteBlockController, SitePageController, PublishedSiteController, SiteBlock, Show.vue, app.css, and published.blade.php; followed existing publication snapshot, media, Hero-link, and form/error-state contracts where needed. Reviewed all additions in SiteBlockEditorTest, PublishedSiteTest, SiteMediaUploadTest, and TextBlockButtonTest.
- Image presets are allowlisted by block type, legacy values fall back, captions remain plain escaped text separate from alt text, and publication tests demonstrate draft isolation and caption preservation through image clearing/replacement. Shared CSS and Vue/Blade markers agree for ratios, crop positions, and corners.
- Text buttons reuse owner-scoped Hero validation, recheck destinations under the existing site lock, clear deleted destinations, resolve public links from the snapshot, and escape labels with external-link protections. Both text-image orientations keep the CTA within the text column. No new database query in the public block loop or new network request was introduced by the feature.
- F-07 was independently re-examined through the complete editor delta and fresh compiler output. Rendered containers replace the inert native template; HeroOptions remains Hero-only. The repair remains valid.
- Reviewed .gitignore, package.json, AGENTS.md, coding standards, and Plesk deployment guidance. Production build and standalone typecheck lifecycle regenerate ignored output. Generated/minified output content, dependencies, caches, and unrelated project areas were excluded from source-quality review; generated file removals and regeneration configuration were included.

## Findings

- No new findings across quality, security, performance, and tests. No open or fixed P0/P1 findings remain.
- F-07 remains closed after this independent re-review.
- F-02 remains open P2; the existing muted-text token and its new editor help-text uses retain the documented contrast issue.
- F-06 remains open P2 from earlier work; its unrelated hostname boundary was not re-reviewed or accepted here.

## Remaining risk

- Browser tests are not configured. This review did not perform a live browser or local Herd interaction pass, so desktop/narrow image geometry, keyboard interaction, and save/discard UX have source/compiler evidence rather than browser acceptance. Check was not required by the request.
- The clean-checkout absence scenario was not destructively repeated; helper regeneration and production build passed with current ignored local output present.
- Existing F-02 and F-06 P2 findings remain unresolved. No current dependency vulnerability scan or runtime performance profile was performed; no such verification command is declared for this scope.
- The initial sandbox restriction on `composer ci:check` was resolved by the permitted retry; no declared verification command remains unavailable.
