# Feature: Themes and site shell

**From build-plan:** feature 3
**Build attempt:** 1
**Branch:** feature/themes-and-site-shell
**Status:** verified

## Goal

Let a site owner choose one of three visual themes and preview a simple site shell around the existing ordered blocks. The existing site name appears as the church name, section navigation follows block order, and the owner can save one optional footer line. These are draft editor features; public rendering and publishing remain in Feature 5.

## In scope

- Add the warm/traditional, clean/minimal, and bold/contemporary themes, with warm selected for new and existing sites by default.
- Use the existing editable `Site.name` as the church name in the preview header; do not add a duplicate display-name field.
- Generate accessible section links from the ordered blocks and their existing preview anchors, without changing block content or order.
- Let an owner edit and save one optional single-line footer text value.
- Apply the selected theme to the private page preview without restyling the editor workspace.
- Keep settings owner-scoped, responsive, keyboard accessible, and consistent with existing save and validation feedback.

## Out of scope

- Logo selection or upload; Feature 4 owns uploaded site imagery.
- Public Blade rendering, publishing, public preview URLs, SEO fields, and custom domains; Feature 5 and later features own these.
- Editing navigation labels or order separately from block content and order.
- New page types, changes to block contracts, new routes, or dependencies.

## Build loop

- Implement the steps in order. Guided cadence requires review and approval after each step.
- After an approved, passing step, offer the configured optional checkpoint commit. `/complete` creates the final feature commit.
- Keep the existing block editor and rename behavior working throughout.

## Build steps

- [x] **1. Persist site theme and footer settings.** Add the `theme_key` and structured `footer` site settings using the existing Laravel model, migration, Form Request, controller, and owner-scoped `PATCH /sites/{site}` route. Keep site creation and the existing name-only rename request working. Default new and existing sites to `warm` and `{ "text": "" }`. Reject unknown theme keys, malformed footer shapes, extra footer keys, and multiline footer text without changing saved settings. Add focused Pest coverage for defaults, owner updates, invalid updates, and owner boundaries. **Done when:** the owner can load and save valid settings, invalid data leaves stored settings unchanged, existing rename behavior still passes, and focused PHP tests pass.
- [x] **2. Add site appearance controls.** Add accessible theme selection and a single-line footer field to the existing site editor, alongside the current site-level name control. Make the rename and theme/footer triggers read clearly as dropdowns, and place them in a right-aligned group that stacks at narrow widths and sits side by side on wider screens, so each panel stays with its trigger without shifting the editor layout or clipping. Save settings explicitly through the existing site update route. Preserve entered values after validation or unexpected request failures; associate errors with their controls, announce status, and focus the first invalid field using the editor's existing pattern. **Done when:** theme and footer edits save and reload for the owner, errors retain drafts and are announced, and TypeScript plus the focused Vue check pass.
- [x] **3. Render the themed shell in the private preview.** Render `Site.name` in the header, generate section navigation in current block order using each preview section's visible heading or a type label when it has no editable heading, and render the saved footer line when nonempty. Link to the existing `block-{id}` anchors. Apply the three theme styles only inside the preview; do not mutate block content or positions. Keep the empty-site state, anchors, contrast, keyboard use, and narrow layouts usable. **Done when:** saved theme, header name, block navigation, and footer appear correctly after reload; navigation follows reordered blocks and targets their sections; selecting a theme changes only preview styling; and existing block editing still works.
- [x] **4. Run final feature checks.** Run `composer test`, `npm run types:check`, `npm run build`, and `node_modules/.bin/vp check resources/js/pages/Sites/Show.vue`. Manually inspect theme selection and reload, header name, empty and populated navigation, footer save/clear, keyboard access, narrow layout, and existing block order. **Done when:** the listed checks pass and the manual walkthrough confirms saved settings do not alter block content or order.

## Files / areas

- `app/Models/Site.php`, a new sites-table migration, `app/Http/Controllers/SiteController.php`, and `app/Http/Requests/SiteNameRequest.php` or its replacement for persisted settings and owner-scoped updates.
- `resources/js/pages/Sites/Show.vue` for site settings and the private shell preview; `resources/css/app.css` only if scoped preview theme tokens belong there.
- `tests/Feature/SiteWorkspaceTest.php` and a focused site-appearance feature test for defaults, validation, persistence, and ownership.
- Existing block preview anchors and ordering in `Sites/Show.vue`; no public renderer or new route is expected.

## Data / contracts

- Persist `theme_key` as a string with exactly `warm`, `clean`, and `bold` accepted. `warm` is the default for new and already-existing sites. The theme descriptions are warm/traditional, clean/minimal, and bold/contemporary.
- Persist `footer` as exactly `{ "text": string }`. The empty string is the default and hides the footer in the preview. The text is a single line, trimmed at its edges, rendered as escaped text, and has no HTML behavior.
- Use the existing `Site.name` as the church name in the header and retain its current dashboard/editor rename behavior. Do not persist a second copy of that name.
- Derive section navigation from the current ordered block props. Each link targets the existing `block-{id}` anchor and uses the visible preview heading, falling back to the block type label. Do not store a separate navigation order or modify block data.
- Header and navigation are derived from the existing site name and ordered blocks; footer and theme are draft settings. No visitor-facing rendering is added here.
- Only an authenticated, verified owner can read or update settings through the existing site routes. Keep the current owner-scoped query and do not accept owner IDs from the client. Invalid partial updates preserve all saved settings.
- Keep theme styles scoped to the private preview so site theme selection does not alter the account workspace's light/dark appearance. Vue renders all site text as text, not HTML.
- Logo assets, upload authorization, storage, and alt text remain Feature 4 work.

## Testing

- Add Pest tests for default theme/footer values, persisted theme/footer updates, invalid or extra settings preserving saved values, name-only rename regression, guest access, and cross-owner isolation.
- Run `composer test`, `npm run types:check`, `npm run build`, and the focused Vite Plus check for `Sites/Show.vue`.
- Browser automation is not configured. Inspect theme switching, save/reload, section links and order, empty/populated states, footer clearing, keyboard use, and narrow layouts manually; record only observed results.

## Notes for the AI

- The approved shell scope reuses the current site name, derives navigation from page blocks, adds one optional footer line, defaults to the warm theme, and defers logo uploads to Feature 4.
- The editor currently exposes only the site's `name` and ordered block data; no theme, header, or footer persistence exists yet. Reuse the existing protected Inertia route rather than adding a new API.
- Public Blade rendering and published snapshots are later work. Do not imply this feature changes a visitor-facing site.
- The project has no `Verify` command entry and no browser test command. Use the exact checks listed under Testing; do not install a runner.
- Final-check note: `composer test` passed with local execution permission, including Pint, PHPStan, and 106 Pest tests (699 assertions). `npm run types:check`, `npm run build`, and the focused Vue check passed; generated build artifacts were restored. Earlier sandboxed aggregate attempts hit Pint socket and parallel PHPStan memory limits before the final permitted run passed. The focused Vue runtime check preserved same-site appearance drafts after a props refresh and loaded saved values after switching sites. The user confirmed Rename opens below its own trigger and Theme & footer opens below its trigger. Browser geometry at 320px and 375px was not measured separately.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":8849,"specSha256":"e50a41754d292394a479acdca92e6c424029f1a3b43abef05502a3953ff60195","branch":"refs/heads/feature/themes-and-site-shell","head":"eb0aff9ef186c2e4293bc0b3eb9b0224a92c8c6d","baseRef":"refs/heads/main","baseCommit":"6eb548ed0a0f1647155b8d08c0a93c5b195b4d52","sourceTree":"faf0e98dd449be89093536089950ce59d6bf5f71","absentOptional":[]} -->

## Findings

### 3/F-03 [P1] closed - Preserve appearance drafts when Inertia refreshes site props

**File:** resources/js/pages/Sites/Show.vue:875
**Found:** 2026-09-24 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The watch source returns a new array whenever the `site` prop is replaced. Inertia replaces the page props on a validation redirect even when the saved theme and footer values have not changed. The callback then overwrites both entered values and calls `defaults()`. For example, choosing Bold and entering footer text containing U+2028 produces a validation error, but the redirect replaces both drafts with the old saved values. Saving a block or renaming the site also silently discards an unsaved appearance draft. This violates the explicit requirement to retain entered values after validation failures. A read-only reproduction with the installed Vue `reactive`, `watch`, and `nextTick` APIs confirmed that replacing a site object with identical saved values resets the edited theme and footer. The installed Inertia Vue adapter confirms that preserved-state visits still replace page props.
**Suggested fix:** Synchronize the appearance form only after its own successful save, using returned saved values. Alternatively, watch the two primitive values as separate sources and guard synchronization when drafts must be retained. Add focused regression evidence for validation failure and an unrelated successful editor request; both must preserve the appearance draft.
**Resolution:** Replaced the watcher over theme/footer values with a watcher on the site ID, so same-site Inertia prop refreshes do not overwrite drafts. A successful appearance save still resets form defaults, and switching to a different site loads that site's settings. The focused Vue runtime check preserved drafts on a same-site props refresh and loaded new defaults after a site switch. Independent re-review on 2026-09-24 at a0d9d784cb06a06c85e589978e636c20f603a2bd inspected the complete feature delta and executed the actual site-ID watcher extracted from Show.vue with Vue reactive/watch/nextTick. Both a simulated validation redirect and an unrelated same-site editor refresh preserved the edited theme and footer without resetting defaults; a different site ID loaded saved settings. The original draft-overwrite defect is closed. This is component-state evidence, not a browser test. Re-executed the actual watcher at 56d3a3e9b74ad30a77ba7dcc77202c22d5d78b40: both same-site refresh scenarios still preserve drafts, and changing site ID resets to the saved settings. Remains closed. A fresh execution of the extracted site-ID watcher at eb0aff9ef186c2e4293bc0b3eb9b0224a92c8c6d again passed same-site validation refresh, unrelated same-site save, and different-site reset checks.

### 3/F-04 [P2] closed - Keep dropdown panels within the content area when headers wrap

**File:** resources/js/pages/Sites/Show.vue:1044
**Found:** 2026-09-24 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** Both panels now correctly use their own `details.relative` trigger as the containing block, but the right-aligned group does not constrain the first panel to the content bounds. When a long title moves the group onto its own row and both controls still fit together, Rename's right edge is left of the content's right edge by the Theme & footer trigger width plus the gap. Its `right-0 w-80` panel extends a further 20rem to the left. For content width C, second-trigger width B, gap G, and panel width P, the first panel starts at C - B - G - P relative to the content's left edge; this is negative whenever C < B + G + P, even though both triggers can still fit. The `100vw` maximum does not account for the second trigger or the desktop sidebar. The enclosing AppSidebarLayout still uses `overflow-x-clip`. This is a source-derived layout defect; exact clipping widths and viewport geometry have not been measured in a browser.
**Suggested fix:** Preserve each panel's own trigger anchor and constrain or shift the panel when its left edge would cross the content bounds, using an existing installed collision-aware popover component or a content-aware layout. Inspect both controls together on the wrapped row as well as separate rows, with a long site name and the expanded sidebar.
**Resolution:** The right-aligned controls group now stacks below `lg` and sits side by side on wider screens. At narrow widths, each trigger aligns to the content area's right edge; at wider widths, the available row space keeps the first panel within the content bounds. Both panels remain positioned relative to their own triggers. TypeScript and the focused Vue check pass. Independent review on 2026-09-24 at eb0aff9ef186c2e4293bc0b3eb9b0224a92c8c6d reviewed the complete feature delta and confirmed closure of the original offset defect: `flex-col items-end` aligns both triggers to the content right edge below lg; `lg:flex-row` starts at 1024px, where the 16rem expanded sidebar, inset margin, and 5rem page padding leave about 680px for the controls and their panels. At mobile widths, each panel is capped to viewport width minus the matching 40px page padding. Both panels keep their own relative details anchor and absolute positioning, so opening them does not move the editor. No new defect was found in this repair. This is source-derived layout evidence; browser geometry, including 320px and 375px, remains unmeasured.

## Independent review

### Review receipt

**Status:** passed
**Target commit:** eb0aff9ef186c2e4293bc0b3eb9b0224a92c8c6d
**Base commit:** 6eb548ed0a0f1647155b8d08c0a93c5b195b4d52
**Base ref:** refs/remotes/origin/main
**Spec hash:** e50a41754d292394a479acdca92e6c424029f1a3b43abef05502a3953ff60195
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-24T19:29:47Z
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-24T19:32:01Z
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Commands

- `git rev-parse HEAD`, `git merge-base refs/remotes/origin/main HEAD`, `git symbolic-ref refs/remotes/origin/HEAD`, `shasum -a 256 blueprint/context/current-feature.md`, and `git status --porcelain=v1`: passed before review and freshness reconfirmed before recording the receipt. Only the two permitted evidence paths differ from the target; branch is `feature/themes-and-site-shell`.
- `git diff --stat`, `git diff --numstat`, and complete file diffs for the recorded base through target: inspected all 11 changed paths, with nearby callers, layout, routes, validation, and tests.
- `git diff --check 6eb548ed0a0f1647155b8d08c0a93c5b195b4d52 HEAD`: passed.
- `npm run types:check`: passed.
- `node_modules/.bin/vp check resources/js/pages/Sites/Show.vue`: passed formatting and lint with no warnings.
- `vendor/bin/pest tests/Feature/SiteAppearanceSettingsTest.php tests/Feature/SiteWorkspaceTest.php tests/Feature/SiteBlockTest.php --do-not-cache-result`: initial invocation failed because the final filename does not exist; corrected after listing actual tests, no tests ran in that attempt.
- `vendor/bin/pest tests/Feature/SiteAppearanceSettingsTest.php tests/Feature/SiteWorkspaceTest.php --do-not-cache-result`: passed, 22 tests and 147 assertions.
- `vendor/bin/pest --do-not-cache-result`: passed, 106 tests and 699 assertions.
- `vendor/bin/pint --test app/Http/Controllers/SiteController.php app/Http/Requests/SiteSettingsRequest.php app/Models/Site.php database/migrations/2026_09_24_000001_add_theme_and_footer_to_sites_table.php tests/Feature/SiteAppearanceSettingsTest.php`: passed.
- `vendor/bin/phpstan analyse --no-progress --memory-limit=512M`: passed, zero errors.
- Read-only `node --input-type=module` heredoc executing the actual site-ID watcher extracted from Show.vue with installed Vue reactive/watch/nextTick and strict assertions: passed validation refresh, unrelated same-site refresh, and site-switch cases. Also recalculated workspace-muted contrast.
- Targeted `rg` searches of related feature tests: no skipped, focused, or placeholder tests found.

## Evidence

- Reviewed the complete feature from the recorded base, independently of prior findings: SiteController, SiteSettingsRequest, Site model, migration, preview CSS, all changed Show.vue behavior, three generated Wayfinder files, appearance tests, and the active spec. Generated Wayfinder changes were inspected and are line-reference comments only. Dependencies, build artifacts, unrelated source, and review records were excluded from product scope.
- Applied AGENTS.md, coding standards, interaction policy, config, project overview, and approved spec. No new routes or dependencies; changes remain in private draft editing. Auth and verified route middleware, owner-scoped model lookup, validated partial updates, footer shape enforcement, and escaped Vue rendering preserve the existing security boundary.
- Model defaults and migration cover new and existing sites. Tests cover defaults, saving, trimming, invalid shape/theme/multiline rejection without partial persistence, and footer clearing. Existing workspace tests cover name-only rename, guest access, and cross-owner rejection on the shared update route.
- Theme CSS is scoped to `.site-preview`; saved props select the theme and footer. Navigation follows ordered block props and uses the same draft-aware heading helper as the visible section heading, targeting existing block IDs. No block persistence or order changes are introduced by appearance saves. Rendering adds linear navigation work without new database or network requests.
- F-04 closed: below lg the right-aligned column aligns both triggers to the available content edge; at lg the expanded sidebar and page padding still leave approximately 680px of content at the breakpoint, enough for the two controls and first 320px panel. Panel width is capped against the mobile page padding. Each panel retains its trigger-relative anchor and stays outside document flow. This resolves the original first-trigger left-offset defect by source inspection, without claiming browser measurements.
- F-03 remains closed: actual watcher execution preserves edited theme/footer and does not reset defaults on either same-site refresh case; a different site ID loads saved values and resets defaults/errors.
- F-02 remains open P2: unchanged workspace-muted token calculates to 4.16:1, 3.86:1, and 3.97:1 on its documented backgrounds. No new confirmed findings and no open or fixed P0/P1 findings.

## Findings

- F-02 [P2] open: existing workspace muted-text contrast issue reconfirmed; nonblocking under the review contract.
- F-03 [P1] closed: draft-preservation repair reconfirmed.
- F-04 [P2] closed in this review: dropdown offset repair confirmed.

## Remaining risk

- Browser automation is not configured and browser interaction was unavailable to this reviewer. Exact dropdown geometry at 320px/375px, keyboard interaction, and simultaneous open-panel behavior were not observed. Layout closure is based on source and surrounding sidebar constraints.
- `npm run build` was not rerun in this read-only Phase B because its configured Wayfinder/build plugins write generated product artifacts and may fetch fonts. The active spec records a passing builder build; this receipt independently ran typecheck and focused Vue checks instead. No independent compilation/bundle verification is claimed.
- `composer test` itself was not rerun because it clears application configuration state; the independent Pest suite, relevant Pint check, and PHPStan command all passed as recorded above.
- Tests use SQLite in memory; production MySQL migration execution and a current external dependency vulnerability scan were not performed. No dedicated local security or performance command is declared.
- There is no browser regression harness for the new appearance form; runtime watcher evidence covers component-state refresh behavior only. Existing shared-route ownership tests cover the access boundary, but do not separately submit each new appearance field as a guest or different owner.
