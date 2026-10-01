# Fix: Site presentation controls

**Type:** Fix
**Status:** verified
**Branch:** fix/site-presentation-controls
**Archive:** blueprint/history/fixes/site-presentation-controls.md

## The problem

1. Image-backed Hero blocks always show a dark panel behind their text. Owners want to hide that panel while retaining the image overlay.
2. The site header always shows the site name alongside the logo. Owners want a logo-only header.
3. Image and text-and-image blocks have a solid border around their image frame. Set that border width to 0px.

## The fix

- Add a labeled Show text background checkbox to Hero appearance controls. Default on, including existing blocks without the setting. Save it in the existing Hero content style JSON. Off removes the text panel background while preserving layout, white text, buttons, and the image overlay. Heroes without images keep their existing presentation.
- Add a labeled Show site title checkbox in site settings, default on for existing sites and snapshots. Save it through existing site appearance JSON and validation, with no migration. Off hides the visible header title, retaining the logo and an accessible site identity. Keep the actual site name and page metadata. If no logo is available, show the site name as a fallback so header identity remains usable; explain this beside the checkbox.
- Set image-frame borders to 0px in both the editor preview and published Image and Text and image blocks, including their empty placeholders. Preserve image proportions, crop, rounded corners, captions, and buttons. Other section, card, map, and form borders retain their existing styles.
- Match preview and published rendering. Saved Hero/header changes remain drafts until Publish, and existing save/discard and unsaved-change protection include both toggles.

No new dependencies, routes, migrations, provider calls, or general styling abstractions.

## Build steps

- [x] **1. Make the Hero text background optional.** Add the Hero-only validated style setting, defaults, labeled checkbox, draft preview, and published attribute/CSS.
      **Done when:** existing image Heroes retain their panel; toggling off removes only its background; saving/reloading retains the choice; discard restores it; published output changes only on Publish; toggling on restores the panel. Other block types reject the Hero-only field. Focused Hero tests and frontend checks pass.
- [x] **2. Allow a logo-only site header.** Add the site appearance setting and settings checkbox, include it in save/discard state, and match header rendering in editor and publication.
      **Done when:** defaults show the title; disabling with a logo hides the visible title and retains accessible identity; without a logo, the name remains visible as a fallback; save/reload and discard work; public headers change only on Publish; site name/metadata remain intact. Settings and publication tests pass.
- [x] **3. Remove image borders and verify all fixes.** Apply 0px image-frame borders consistently to Image and Text and image blocks in preview and publication.
      **Done when:** image frames have no solid outline while crop/corners/captions/buttons remain intact; other borders remain unchanged; `composer ci:check` passes. Record available browser/operator evidence and report unobserved checks honestly.

## Files / areas

- `app/Http/Requests/UpdateSiteBlockRequest.php`, Hero style types and `tests/Feature/HeroBlockTest.php`.
- `app/Http/Requests/SiteSettingsRequest.php`, existing site appearance helpers/types and persistence, `resources/js/pages/Sites/Settings.vue`, and settings/publication tests.
- `resources/js/pages/Sites/Show.vue`, `resources/views/sites/published.blade.php`, and `resources/css/app.css`.
- Existing snapshot builder and published snapshot validation only where the new site appearance setting requires it.

## Verify

- Focused Hero, appearance/settings, and publication tests; frontend formatting/lint and typecheck; final `composer ci:check`.
- In the editor, toggle the Hero panel off/on, save/reload, and discard. Confirm the image overlay remains.
- With a logo uploaded, hide the header title, save, and view the page editor. Confirm logo-only branding; check the no-logo fallback.
- Publish and compare public output with the draft on desktop and mobile. Inspect Image and Text and image blocks for zero-width borders.
- Browser automation is not configured. Use available operator evidence without installing a browser runner.

## Review cadence

Use the configured guided per-step review. Stop after the spec for approval before implementation. Each passing build step gets user review; commits, merge, push, and deployment require their applicable authorization.

## Step 1 evidence

- Added Show text background in Hero appearance settings, defaulting on for existing blocks. Disabling removes the panel background while retaining the image overlay, white text, padding, and buttons. Existing style save/discard and dirty detection include the setting.
- Focused Hero tests passed: 37 tests, 184 assertions. Frontend formatting/lint, TypeScript, production build, and changed PHP formatting passed. Publication tests confirm the saved choice is frozen until republishing.
- Browser interaction remains for operator review: select an image-backed Hero, toggle Show text background, save/reload, discard, and Publish. Steps 2 and 3 await step approval.

## Step 2 evidence

- Added Show site title to site settings. Existing sites default on; turning it off with a logo keeps the title available to screen readers and shows only the logo visually. Without a logo, the visible title remains as a fallback. The stored site name and page metadata are unchanged.
- Reused site appearance JSON, validation, defaults, dirty/discard behavior, error association/focus, snapshot copying, and preview/public rendering. No migration needed.
- Focused appearance/publication checks passed: 60 tests, 455 assertions. Frontend formatting/lint, TypeScript, production build, and changed PHP formatting passed. Tests cover saving/reloading, legacy default, invalid boolean input, frozen publication, restoring visibility, and no-logo fallback.
- Operator review still needed for the settings checkbox, discard, and desktop/mobile visual appearance. Step 3 awaits approval.

## Final evidence

- Image and Text and image frames now use `border-0` in preview and published output. Rounded-corner classes and corner presets remain unchanged, along with crop, proportions, captions, and buttons. Other borders were not changed.
- Final `composer ci:check` passed: frontend formatting/lint, TypeScript, PHP formatting/static analysis, production build, and 853 Pest tests with 6,157 assertions. `git diff --check` passed.
- User approved Hero and header steps. Browser visual inspection of border removal and mobile/save/discard interaction remains for operator review; automated output is not browser evidence.
- Regular Audit, Check, and try guide gates are manual and were not requested. Conditional independent review is not selected for these bounded presentation settings: existing ownership and security boundaries are unchanged, and there are no external side effects or personal-data changes. No pending independent review exists. Existing open P2 findings F-02 and F-06 remain unchanged; no blocking P0/P1 findings are recorded.
- Ready for `/complete` after final user review. No commit, merge, push, or deployment performed for this fix.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":7499,"specSha256":"065b0df077115e568ac006811f02b370d11b8afaa1aeec10a2672d38af330719","branch":"refs/heads/fix/site-presentation-controls","head":"1dd9eaac4c907b824f49ff6615e06454bd2b98b5","baseRef":"refs/heads/main","baseCommit":"1dd9eaac4c907b824f49ff6615e06454bd2b98b5","sourceTree":"13f4b9ccd903e8511bb2851bc8df03d729255fe2","absentOptional":[]} -->
