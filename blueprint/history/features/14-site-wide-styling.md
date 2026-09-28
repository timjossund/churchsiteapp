# Feature: Site-wide styling

**From build-plan:** feature 14
**Build attempt:** 1
**Branch:** feature/site-wide-styling
**Status:** verified

## Goal

Let owners choose a coordinated font pairing, custom accent color, and button shape across every page of their site. Preserve existing appearance by default and keep editor previews faithful to the published site.

## In scope

- Shared appearance controls in site settings alongside the existing theme selector.
- Theme default plus four approved font pairings, downloaded through the existing font build pipeline where supported.
- Optional custom accent color with readable derived shades, and theme default, rounded, pill, or square button corners.
- Owner-scoped saving, draft preview, explicit publication, and safe defaults for existing sites and snapshots.

## Out of scope

- Arbitrary font uploads, a font browser, custom CSS, per-block font overrides, or independently configurable font weights.
- Changing content, page/block order, rich text editing from Feature 15, theme backgrounds, or application workspace styling.
- Additional button fill/outline variants, new link behavior, or external font requests from site visitors.

## Build loop

Follow configured `workflow.stepReview: every`: implement one step, verify it, and stop for approval. Optional checkpoint commits are enabled but require explicit approval. `/complete` creates the final feature commit. Offer a code walkthrough after the final review packet.

## Build steps

- [x] **1. Persist shared appearance settings.** Add the minimal site storage, validation, settings controls, owner-scoped serialization, and publication snapshot fields for the approved choices. Include save/loading/error feedback and the existing unsaved-change guard. **Done when:** owners can save, reload, reset to theme defaults, and discard unsaved changes; other users cannot read or change these settings; invalid keys and colors fail with associated errors; saved changes remain unpublished until Publish; existing snapshots remain valid; focused Pest tests and `composer ci:check` pass.
- [x] **2. Apply the approved typography and button shapes.** Load the approved downloadable fonts and apply site-scoped typography and corner styles consistently in the page editor and published pages. **Done when:** all four pairings render their named heading font at 700 and body font at 400, body emphasis remains available, theme default preserves existing styling, all existing content call-to-action buttons share the chosen shape, all pages agree, font assets resolve in the production build, and focused tests and `composer ci:check` pass. Manually inspect loaded fonts, long headings, narrow layouts, and fallback behavior without claiming browser evidence until observed.
- [x] **3. Apply readable accent colors.** Derive safe color pairs from the saved accent and apply them to existing accent text, links, action buttons, and accent sections while preserving contextual hero/section contrast overrides. Explain shade adjustment beside the control. **Done when:** representative light, dark, saturated, black, and white inputs produce readable text/button pairs across all themes and section backgrounds; preview and published styles agree; clearing the override restores theme colors; publication isolation remains intact; deterministic contrast tests and final `composer ci:check` pass. Review keyboard focus and preview/published parity manually when browser access is available.

## Files / areas

- `app/Models/Site.php` and one additive migration in `database/migrations/` for site appearance overrides.
- `app/Http/Requests/SiteSettingsRequest.php`, `app/Http/Controllers/SiteController.php`, and `app/Http/Controllers/SitePageController.php` for validation, owner-scoped writes, and settings/editor props.
- `app/Actions/BuildSitePublicationSnapshot.php` and `app/Http/Controllers/PublishedSiteController.php` for snapshot inclusion, legacy defaults, and visitor rendering.
- `resources/js/pages/Sites/Settings.vue`, `resources/js/pages/Sites/Show.vue`, `resources/views/sites/published.blade.php`, and `resources/css/app.css` for controls and matching presentation.
- `vite.config.ts` and the existing font pipeline for bundled font assets; retain licenses with any checked-in font files.
- `tests/Feature/SiteAppearanceSettingsTest.php`, `tests/Feature/SitePublicationSettingsTest.php`, `tests/Feature/PublishedSiteTest.php`, and focused contrast unit coverage.

## Data / contracts

### Approved font pairings

| Stored key  | Label         | Heading font / weight  | Body font / weight     |
| ----------- | ------------- | ---------------------- | ---------------------- |
| theme       | Theme default | Existing theme default | Existing theme default |
| traditional | Traditional   | Lora / 700             | Source Sans 3 / 400    |
| modern      | Modern        | Montserrat / 700       | Inter / 400            |
| editorial   | Editorial     | Playfair Display / 700 | Source Sans 3 / 400    |
| classy      | Classy        | Cinzel / 700           | Georgia / 400          |

- Use a nullable `appearance` JSON column on Site with only `font_pairing`, `accent_color`, and `button_shape` keys. Null or missing values resolve to theme defaults. Save explicit choices as allowlisted strings; store a custom accent as lowercase six-digit `#rrggbb`, or null for theme default. Do not store derived shades. Normalize empty color input to null. Reject malformed types, unknown keys, arbitrary CSS, alpha colors, and unsupported preset values.
- Partial settings requests that omit appearance must preserve it. The new editor submits the complete appearance object. Include it under snapshot `site.appearance`; publication freezes the values for all pages. Keep legacy version 1/2 snapshots without appearance usable and normalize missing or unrecognized legacy choices safely to theme defaults. Confirm existing unpublished-change detection includes these fields.
- Font choices and button shapes are independent of theme. Switching themes preserves selected overrides; selecting Theme default removes that specific override. Scope all font/color/shape rules to site content, not the authenticated workspace. Heading typography covers the site title and block headings; body typography covers paragraphs, navigation, contact details, and content buttons.
- Button shapes use keys `theme`, `rounded`, `pill`, and `square`. Theme retains existing corners; rounded uses the existing rounded-lg radius, pill uses fully rounded corners, and square uses zero radius. Apply to hero primary/secondary and text-block action buttons. Navigation, inline contact links, and menu controls retain their established roles and behavior.
- Use serif/sans-serif fallback stacks appropriate to each pairing and `font-display: swap`. Georgia uses the system font with a serif fallback. Load needed heading 700 and body 400/600/700 weights through the existing font asset integration where supported. Verify provider availability during implementation; do not silently substitute fonts. Reuse bundled assets across pairings and avoid downloading fonts at visitor request time.
- The accent picker accepts any valid six-digit color and retains the selected value. Derive rendered shades deterministically for their actual backgrounds, targeting at least 4.5:1 for normal text and button labels and 3:1 for meaningful focus indicators. Preserve the selected hue where possible, adjusting toward black or white as needed. Existing hero image and contrast-section foreground overrides take precedence where needed for readability. Use one small shared source of derived palette values for Vue and Blade to avoid divergent calculations; prefer server-derived presentation data using the same helper for draft props and snapshots.
- Keep existing owner-scoped route lookup and transaction behavior. No new permissions or public write routes. Validate server-side before persisting and render only allowlisted families/shapes and validated or derived color tokens.
- Label every control and show font previews. Associate validation errors with controls, announce errors, focus the first invalid field, and clear stale errors on edits. Disable saves during pending writes, retain edits after failures, and reuse existing successful-save feedback and unsaved-change confirmation. Do not reset to defaults after an unexpected error. With no custom overrides, show Theme default and retain the existing site appearance.

## Testing

- Repair verification: user confirmed the fonts look correct after restoration. Final `composer ci:check` passed again with 748 tests and 5,788 assertions, plus frontend checks, PHP formatting/static analysis, and production build. This user confirmation covers the reported font regression, not the full browser checklist.

- Post-review font-loading repair: restored the current development font manifest after checkpoint cleanup had replaced it with the tracked Instrument Sans-only version. Added `/public/fonts-manifest.dev.json` to `.gitignore` and removed it from the index while preserving its local bytes. Every manifest font URL returned HTTP 200 from the running local Vite server. This change makes the earlier review receipt stale; a new approved checkpoint and independent review are required before completion.

- Step 3/final: focused appearance/contrast tests passed (46 tests, 329 assertions before the final publication test). Final `composer ci:check` passed: 748 tests, 5,788 assertions, frontend format/lint/TypeScript, PHP formatting/static analysis, and production build. Contrast coverage exercises all three theme background pairs with seven extreme/representative colors; integration coverage checks preview/publication palette equality, draft isolation, retained input, and reset. Hero and contrast-section CSS overrides were inspected, not browser-tested. Independent review is required for the migration and remains pending checkpoint approval.

- Step 2: `composer ci:check` passed with 725 tests and 5,619 assertions, plus formatting, static analysis, TypeScript, and production build. Verified all six downloaded font families have existing built assets and `font-display: swap`; publication tests cover every pairing and shape across home and secondary pages, draft isolation, and malformed legacy fallback. No live browser verification performed.

- Step 1: `php artisan test tests/Feature/SiteAppearanceSettingsTest.php` passed (19 tests, 134 assertions). `composer ci:check` passed (720 tests, 5,539 assertions), including frontend checks, PHP formatting/static analysis, and production build. The additive appearance migration was applied to the local database. No live browser interaction was performed.

- Planning baseline `composer ci:check` passed in this session: frontend formatting/lint and TypeScript, PHP formatting/static analysis, production build, and 710 Pest tests with 5,465 assertions.
- Add focused tests for valid presets, invalid types/keys/color injection, normalization, owner isolation, omitted-field preservation, default/reset behavior, and save/reload serialization.
- Prove publication isolation and consistent appearance on home and secondary pages, plus legacy snapshot fallback without appearance fields.
- Test deterministic contrast calculations against the backgrounds actually used across themes, soft/accent/contrast sections, and hero overrides. Include extreme colors and verify computed ratios rather than merely matching implementation output.
- Verify built font files and stylesheet references. Browser tests are not configured. Manual review should cover each pairing, resolved font faces, narrow screens, save/discard, error focus, all button shapes, custom accent changes, and draft/published parity. No live browser evidence has been captured during planning.
- Run focused tests after each step and `composer ci:check` for the required step and final gates. Do not start a dev server while planning.

## Notes for the AI

- User approved this exact font lineup, downloadable fonts, default/rounded/pill/square corners, and automatic readable shade adjustment.
- Critique tightened theme-default compatibility, publication isolation, contextual contrast precedence, and a shared color derivation path. Keep the implementation proportional: no new typography framework or dependency unless the existing font integration cannot fulfill the approved requirement.
- This feature adds persisted appearance data and a migration; assess the configured independent-review gate during implementation/completion.
- Build attempt 1 and archive `blueprint/history/features/14-site-wide-styling.md` are reserved by this spec. No prior feature 14 archive or conflicting branch was found. Do not create the branch or implement until the spec is approved.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":12850,"specSha256":"243dc87710f4dbc482810dd8fca657229851cf36134fa010d584a7f256e29ca2","branch":"refs/heads/feature/site-wide-styling","head":"11d3b762ef496ce6227e46b6ea9c2751bde1b0c6","baseRef":"refs/heads/main","baseCommit":"3923444b909fe8eb39e28bd280b0e1fa4b41b61b","sourceTree":"d994272f64811c5b0900ff82e9e6a7533373cacf","absentOptional":[]} -->

## Independent review

# Independent Review

**Status:** passed
**Target commit:** 11d3b762ef496ce6227e46b6ea9c2751bde1b0c6
**Base commit:** 3923444b909fe8eb39e28bd280b0e1fa4b41b61b
**Base ref:** refs/heads/main
**Spec hash:** 243dc87710f4dbc482810dd8fca657229851cf36134fa010d584a7f256e29ca2
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-28T20:07:49.573429+00:00
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-28T20:10:39.318958+00:00
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Commands

- `git rev-parse HEAD`, `git merge-base refs/heads/main HEAD`, `shasum -a 256 blueprint/context/current-feature.md`, and `git status --short`: passed; target, base, exact spec hash, and permitted dirty paths match the request.
- `git diff main...HEAD` with focused source/caller reads: reviewed the complete feature delta across all four lenses, excluding the review evidence files.
- `composer ci:check`: passed on the approved elevated rerun; frontend formatting/lint, TypeScript, Pint, PHPStan, production build, and 748 Pest tests with 5,788 assertions. The initial sandbox attempt stopped at Pint's local worker socket with EPERM; the rerun resolved that environment limitation.
- `git diff --check`: passed.
- Focused test-source search for skipped, focused, or placeholder tests: none found in the changed tests.
- Offline Python font-manifest inspection: passed; development and production manifests contain all six approved downloaded families, heading weight 700, body weights 400/600/700, and `font-display: swap`; all 12 production font URLs resolve to local files.
- Python HTTP checks against the existing local Vite server: passed; all 12 development font URLs return HTTP 200 with non-empty bodies.
- `git check-ignore public/fonts-manifest.dev.json` and `git ls-files -- public/fonts-manifest.dev.json`: passed; the restored development manifest exists locally, is ignored, and is untracked.
- Offline relative-luminance calculation: reproduced F-02's 4.16:1, 3.86:1, and 3.97:1 workspace contrast ratios.

## Evidence

- Reviewed the additive nullable migration, model cast/fillable configuration, owner-scoped request validation and transactional updates, normalized editor props, publication snapshots/fingerprints, and published rendering. Appearance remains a draft until explicit publication; legacy values normalize safely and CSS output is constrained to allowlisted choices and valid derived hex values.
- Reviewed settings defaults, save/error/discard/unsaved-state handling, scoped typography and button selectors, contextual hero and section overrides, shared palette derivation, Vite font configuration, both rendering entry points, and the existing custom-domain PublishedAssets manifest/asset path. Palette work is bounded and adds no database queries or visitor font-provider requests.
- Integration tests cover normalization, malformed input, ownership, omitted appearance preservation, resets, unpublished-change detection, publication isolation, legacy fallback, all pairings/shapes, and home/secondary pages. Unit tests independently calculate contrast for three theme background pairs and seven representative colors.
- Font repair is present in the reviewed checkpoint: the stale generated manifest is removed from version control and ignored while the working development copy retains all approved families. Development manifest SHA-256 after verification is `3cee451344eb6930ab17dbda896a93c26eb3887db7e52553d56b57e28fe81489`.
- Applied AGENTS.md proportionality and ownership rules plus the local Laravel/Vue, validation, styling, and test standards. Dependencies, generated assets, caches, and Wayfinder output were excluded from source review; font build output was inspected only as verification evidence. Generated files and the local Herd build were preserved. No unexpected dirty state appeared.

## Findings

- No new confirmed findings and no open or fixed P0/P1 findings.
- F-02 remains open P2: unchanged muted workspace text contrast was re-examined and reproduced. No repair or acceptance is claimed.
- F-06 remains open P2 from earlier domain work; it is outside this feature's changed/reachable scope and was not re-reviewed.

## Remaining risk

- Browser interaction and visual checks for loaded font faces, fallback behavior, narrow/long layouts, keyboard focus, save/discard/error feedback, and visual preview/publication parity were not performed by this reviewer. HTTP asset checks and source inspection do not establish visual correctness. No browser test command is configured; Check was not required by this request.
- The existing optional-fontaine build warning remains; the production build succeeds with the declared fallback stacks. No dependency was installed.
- No declared verification command remains unavailable after the successful elevated rerun.
