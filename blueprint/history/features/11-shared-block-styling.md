# Feature: Shared block styling

**From build-plan:** feature 11
**Build attempt:** 1
**Status:** verified
**Branch:** feature/shared-block-styling
**Archive:** blueprint/history/features/11-shared-block-styling.md

## Goal

Let owners adjust section spacing, content width, heading size, and theme-aware backgrounds per block, with matching editor and published rendering and unchanged defaults for existing blocks.

## In scope

- Extend the existing block appearance controls with the presets approved in this conversation.
- Apply spacing, width, and backgrounds to all existing block types; show heading size only for blocks with a heading.
- Keep controls within the current editor appearance panel and use existing theme tokens and form patterns.
- Save draft settings through the existing owned site/page/block endpoint. Changes reach visitors only after Publish.
- Preserve existing alignment, image-side layout, and hero image, height, overlay, motion, and button behavior.

## Out of scope

- Features 12-15: image crop/proportions/captions, new content buttons, church-information layouts, site-wide typography/colors, and rich text.
- Custom CSS, arbitrary colors or numeric input, new block types, migrations, dependencies, and site-wide defaults.
- Dashboard changes, authentication repairs, deployment, and unrelated findings.

## Build loop

- Use per-step review (`workflow.stepReview: every`). Pause for review after each working step.
- Checkpoint commits are enabled but optional and require approval; do not commit automatically.
- Run focused checks per step and `composer ci:check` as the final gate. `/complete` owns the final feature commit and archive.
- Regular audit, live check, and try guide are manual. Independent review is conditional on sensitive or unusually broad work; reassess actual scope during implementation.
- Preserve the already-approved, uncommitted dashboard change and unrelated working-tree changes. Keep regenerated build assets and Wayfinder output separate from source commits.

## Build steps

- [x] 1. Extend validated block style persistence and regression coverage.
      Reuse `UpdateSiteBlockRequest` and the current save endpoint. Accept only the enum values below, retain block-type restrictions and ownership checks, and reject invalid payloads without partial writes. Inspect the existing controller's save behavior before editing; preserve its response shape.
      **Done when:** focused Pest tests prove valid presets round-trip, omitted settings remain compatible, invalid values and heading settings on headingless blocks are rejected, and another owner's site/page/block cannot be changed.

- [x] 2. Render the new styles and expose the editor controls together.
      Implement shared CSS rules used by Vue and Blade, preserving the current appearance when settings are absent or Current. Add labeled controls using the existing preview, save, loading, error, and focus patterns. Avoid accepting styles that the UI can save but published rendering ignores.
      **Done when:** each control visibly updates the selected draft preview, survives save/reload, and appears in published HTML only after Publish; focused rendering/publication tests pass. Existing hero and block editor tests remain green.

- [x] 3. Verify compatibility and responsive behavior.
      Cover legacy snapshots, all existing block types, all three themes, image-bearing heroes, and page ownership. Check desktop/mobile layout, keyboard controls, and readable text, links, and buttons on the two new backgrounds when live UI evidence is available.
      **Done when:** `composer ci:check` passes; focused tests establish draft/publication isolation and unchanged defaults; record actual visual checks and explicitly report any unavailable browser verification.

## Files / areas

- `app/Http/Requests/UpdateSiteBlockRequest.php`: style whitelist, enums, block-type restrictions.
- `app/Http/Controllers/SiteBlockController.php`: existing owned update path; change only if required.
- `resources/js/pages/Sites/Show.vue`: style types/defaults, controls, error focus, and preview rendering.
- `resources/css/app.css`: shared style selectors and theme-aware color mappings.
- `resources/views/sites/published.blade.php`: allowlisted style rendering and content wrapper.
- `app/Actions/BuildSitePublicationSnapshot.php`: confirm settings remain in existing content snapshots/fingerprints; no new snapshot version expected.
- `tests/Feature/SiteBlockEditorTest.php`, `SitePublishingTest.php`, `PublishedSiteTest.php`, and `HeroBlockTest.php`: focused persistence, publication, compatibility, and hero regressions.

## Data / contracts

Store optional string fields in the existing `content.style` JSON object. No backfill or schema change. These concrete mappings are part of the spec for review:

| Field           | Values                                | Meaning                                                                                                                                                                |
| --------------- | ------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `spacing`       | `compact`, `current`, `spacious`      | Vertical section padding of 1.5rem, existing 3rem, or 5rem respectively; retain existing horizontal gutters.                                                           |
| `content_width` | `narrow`, `current`, `full`           | Narrow caps the content wrapper at 42rem; Current retains existing rendering; Full removes the outer content-width cap within section gutters.                         |
| `heading_size`  | `small`, `current`, `large`           | Small and Large scale the block's existing responsive heading font sizes by 0.875 and 1.25; Current preserves them. Keep heading semantics, line-height, and wrapping. |
| `background`    | `theme`, `soft`, `accent`, `contrast` | Retain existing two choices and add theme-derived accent and contrast surfaces with readable foregrounds.                                                              |

- Heading-bearing types are `hero`, `about`, `heading_text`, `service_times`, `contact`, and `text_image`. Prohibit `heading_size` for `plain_text`, `image`, and `video`; never invent headings for them.
- Missing new fields mean Current. Missing background remains Soft for About and Theme otherwise. Preserve existing valid values, including legacy content without a style object.
- Current width must preserve existing per-block text/media limits, not silently widen old content. Narrow/Full change the outer wrapper only; retain inner readability/media constraints, image ratios, and text-image layout. Use the same explicit width rules in both renderers.
- Accent uses the theme's existing action surface and action foreground. Contrast uses the theme's existing ink as its surface and background as its foreground. Scope foreground, muted text, links, borders, and action overrides to the section. For these surfaces, use foreground-colored borders/links and reverse the surface/foreground pair for buttons so controls remain visible. Preserve nested media placeholder legibility. Verify actual contrast in each theme.
- Hero images/overlays and their existing readable text panel take precedence over section background colors when an image is present. Background choice remains saved for when the image is removed. Spacing changes padding without overriding hero minimum height, centering, or reduced-motion behavior.
- The server validates enums and resolves the authenticated user's site, page, and block before saving. Do not trust client IDs to bypass ownership. Retain existing request and error response shapes.
- Render only allowlisted selectors; never interpolate arbitrary user-provided CSS. Escape user text through existing Vue/Blade behavior. Unknown legacy style values fall back safely to existing defaults.
- Styling remains draft content, participates in the existing snapshot fingerprint, and publishes atomically with the page/site content. Existing published snapshots must render without migration or republishing.

## Testing

- Final verification: `composer ci:check` passed on the final implementation (598 tests, 4,750 assertions); `git diff --check` passed.
- Tim reported the UI works after Step 2. Specific mobile, keyboard, and cross-theme browser scenarios were not independently observed; no automated browser coverage is claimed.
- Shared background color pairs calculated from source tokens: Warm accent 6.73:1 / contrast 13.12:1; Clean 6.70:1 / 16.96:1; Bold 8.52:1 / 15.24:1. This is numeric palette evidence, not a screenshot measurement.
- Hero regression now combines shared styles with a published image, full height, light overlay, fixed motion, and two links, retaining draft/publication isolation.
- Configured gate outcome: audit, live Check, and try guide remain manual. Conditional independent review is not selected: this is a bounded styling extension using existing validation and ownership paths, with no new security boundary, dependency, payment behavior, or sensitive-data handling. No existing review request is pending.
- Existing findings F-02 and F-06 remain open P2 and outside this feature. There are no open/fixed P0/P1 findings in the ledger.

- Step 2: TypeScript, production build, targeted frontend lint/format, and PHP formatting passed. Focused block editor, published site, publishing, and hero suites passed (112 tests). Browser interaction and visual parity remain unverified; cover them in Step 3 when available.

- Step 1: `php artisan test --compact tests/Feature/SiteBlockEditorTest.php` passed (62 tests, 562 assertions). Focused Pint check passed for the request and test file.

- Baseline `composer ci:check` passed before spec creation: frontend formatting/lint, TypeScript, PHP formatting/static analysis, build, and 553 Pest tests with 4,214 assertions.
- The separately approved prerequisite repair removed one trailing blank line from `blueprint/context/findings.md`; no finding content changed.
- Add focused Pest coverage for accepted/rejected enum values, wrong types/unknown style keys, headingless restrictions, legacy omissions, owner isolation, saved content, publish snapshots, and draft changes not leaking into published pages.
- Cover all themes and image/no-image heroes in renderer regressions; verify default output and hero options remain compatible.
- In the editor, save disables during processing; validation errors associate with their controls, announce appropriately, focus the first relevant field, and clear on correction. Network/server failure preserves draft choices and offers retry through existing feedback. Empty pages retain their existing state.
- Browser tests are not configured. Do not install a browser runner for this feature or claim visual evidence from PHP tests. Record a manual walkthrough or actual live evidence separately.

## Notes for the AI

- Critique tightened Current semantics to prevent accidental restyling and clarified how shared controls interact with hero settings and headingless blocks.
- Reuse installed components and shared CSS. Do not add a styling service, theme engine, new endpoint, or compatibility framework.
- Existing repository evidence shows Vue and Blade have different default wrapper structures. Preserve those defaults; apply consistent explicit preset rules without broad layout cleanup.
- Build attempt 1 has no matching prior feature record. The exact archive path is absent with ordinary directory parents, has no use in available Git history, and the configured branch is valid and unoccupied.
- Tim approved the spec and both implementation review steps; implementation is now verified and ready for `/complete`. No feature commit or merge has been made.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":11936,"specSha256":"25bfef8af3fd14bddc962ff1527c62c796e6948b2f3d639a5ae751f23273ed0e","branch":"refs/heads/feature/shared-block-styling","head":"332afee4dbc974ade39c7d09d1f5a0f7512ca430","baseRef":"refs/heads/main","baseCommit":"332afee4dbc974ade39c7d09d1f5a0f7512ca430","sourceTree":"9cbebc34672038e81d1d00143cc9faa7110db933","absentOptional":[]} -->
