# Feature: Contact map

**Status:** verified (automated checks; manual limitations recorded below)
**From build-plan:** feature 18
**Build attempt:** 1
**Branch:** feature/contact-map
**Archive:** blueprint/history/features/18-contact-map.md

## Goal

Let an owner optionally show an OpenStreetMap map with a church location pin in a Contact block. Keep setup simple: choose the pin on OpenStreetMap and paste its full sharing link. Match editor previews and published pages while retaining existing contact details and directions links.

## In scope

- Add a Show map toggle, off by default, and a labeled OpenStreetMap link field to the existing Contact block side panel.
- Provide concise setup instructions: find the church on OpenStreetMap, choose Share, select Include marker, confirm the pin, then copy the full Link URL. Explain that the map link must be updated separately when the street address changes.
- Accept a full HTTPS OpenStreetMap sharing link containing marker coordinates. Generate a trusted map embed from those coordinates; never render pasted HTML.
- Show contact details on the left and the map in the right half of the block on medium and larger screens, stacking details above the map on smaller screens. Match this layout, responsive dimensions, and neighborhood framing in the unsaved editor preview and frozen published version.
- Keep OpenStreetMap attribution visible and offer an OpenStreetMap location link outside the iframe. Retain the existing address-based Get directions link.
- Preserve the current owner/site/page/block authorization, save/discard behavior, unsaved-change protection, styling, and explicit Publish boundary.

## Out of scope

- Automatic address lookup, autocomplete, API keys, paid map services, new dependencies, or new routes.
- A custom map picker, multiple pins, custom map themes, configurable zoom controls, or a static-image generation service.
- Replacing the existing directions provider or changing old contact blocks' appearance.
- Deployment, commit, merge, or push during implementation without the applicable authorization.

## Build loop

Use guided per-step review (`workflow.stepReview: every`). After each passing step, present its result and wait for approval before continuing. Optional checkpoint commits are enabled, but require approval and passing verification. `/complete` creates the final feature commit. Offer the final read-only walkthrough separately from manual product verification.

## Build steps

- [x] **1. Validate and persist optional contact map settings.** Add a small PHP OpenStreetMap URL helper and extend Contact block update validation. Store optional map settings in the existing content JSON. Preserve legacy blocks and protect other block types from map fields. Add focused parser and persistence tests.
      **Done when:** an owner can save/reload valid enabled or disabled map settings; invalid or forged map inputs leave the saved block unchanged; another account cannot change the map; old blocks remain valid. Focused Pest tests, PHP formatting, and PHP type checks pass.
- [x] **2. Add the editor controls and faithful draft preview.** Add the toggle, link field, instructions, validation feedback, and iframe preview to the existing Contact block editor. Integrate map values with dirty detection, block selection, saving, and discarding. Add a small TypeScript parsing helper using the same accepted URL contract and fixtures as PHP.
      **Done when:** enabling a valid map shows its pin in the draft preview; disabling it hides the map; save/reload and discard work; invalid input shows useful feedback without loading an untrusted iframe. Keyboard controls have labels, errors are associated and announced, focus follows the existing save-error pattern, and editing clears stale errors. Frontend lint/format/type checks pass; manually verify desktop/mobile preview and unsaved-change protection.
- [x] **3. Render frozen contact maps and verify the complete feature.** Use the PHP helper in published rendering, add the map and attribution to Blade, and test the publication boundary and hostile legacy inputs. Use the established publication pipeline for both free subdirectory pages and paid customer domains.
      **Done when:** a published contact map shows the chosen pin; later draft changes do not affect it until Publish; disabling and publishing removes it; malformed legacy map settings omit the map safely while preserving contact details. Focused Pest coverage verifies ownership, publication, and customer-domain rendering. `composer ci:check` passes. Record a manual browser check of the real iframe, attribution, directions, keyboard access, and narrow/wide layouts; report unavailable external checks honestly.

## Files / areas

- `app/Http/Requests/UpdateSiteBlockRequest.php`: contact-only validation and field allowlist.
- `app/Support/OpenStreetMapUrl.php` (new): validated marker extraction and trusted embed/location URL generation.
- `app/Http/Controllers/SiteBlockController.php`: existing validated content persistence; change only if normalization needs it.
- `resources/js/pages/Sites/Show.vue`: Contact controls, content types, draft/save/discard state, and preview.
- `resources/js/lib/open-street-map.ts` (new): matching preview URL derivation.
- `app/Http/Controllers/PublishedSiteController.php` and `resources/views/sites/published.blade.php`: safe published map rendering.
- `app/Actions/BuildSitePublicationSnapshot.php`: existing content snapshot pipeline; change only if needed.
- Focused unit/feature tests and public non-secret parser fixtures under `tests/`, reusing `ChurchInformationBlockTest.php` and `CustomerDomainTransportTest.php` where appropriate.

## Data / contracts

- No database migration. Contact content may contain `map: { enabled: boolean, url: string }`. Missing map means disabled. The saved URL is trimmed; blank is allowed only when disabled. A nonblank URL must be valid even when disabled, so a previously selected pin can be retained safely.
- Keep `heading`, `email`, `phone`, `address`, and existing `style` fields unchanged. Map data is optional for older clients and blocks, and prohibited on other block types. The map may be shown even when the optional street-address field is empty; the owner explicitly chooses its location.
- Accept only HTTPS URLs on exactly `openstreetmap.org` or `www.openstreetmap.org`, at the root path, without credentials or nonstandard ports. Require exactly one scalar `mlat` and one scalar `mlon`, with finite decimal latitude in [-90, 90] and longitude in [-180, 180]. Reject malformed encoding, control characters, duplicate marker parameters, array parameters, HTML, deceptive hosts, shortened links, and embed URLs. Other sharing metadata must not affect generated URLs or introduce another resource host.
- A sharing fragment may carry map-view metadata, but the pin comes solely from `mlat`/`mlon`. Use one consistent neighborhood viewport around the pin in both runtimes, bounded to valid map coordinates; choose its reversible internal calculation during implementation and verify parity with shared fixtures. Custom view controls are outside this feature.
- Construct iframe URLs only on `https://www.openstreetmap.org/export/embed.html`, using the standard map layer, a generated bounding box, and the validated marker. Construct the external location link on the same trusted OpenStreetMap host. Escape all rendered values; do not inject arbitrary user HTML, directly embed the pasted URL, or fetch a supplied URL on the server.
- Use native lazy iframe loading, a descriptive title, responsive full width, rounded framing, and the existing preview/published styling. Preserve the provider's attribution and include an accessible OpenStreetMap credit/link outside the iframe.
- Provider content loads directly from OpenStreetMap. Keep the text address, directions link, and external map link outside the iframe, usable even if it fails. Do not claim cross-origin iframe load events prove map success. Invalid unsaved links render no iframe; malformed published legacy data suppresses the map rather than taking the entire site offline.
- Map settings follow the current draft and frozen-publication boundary. Neither address edits nor save actions trigger geocoding or public provider calls on the server. Existing blocks and published snapshots with no map remain unchanged.

## Testing

- Baseline: `composer ci:check` passed with 777 Pest tests and 5,865 assertions, including frontend checks, PHP formatting/static analysis, and the production build. An initial attempt found formatting in the edited project plan; formatting was corrected before the successful rerun.
- Parser cases: full marker links, both allowed hosts, coordinate boundaries, negative and zero coordinates, malformed/duplicate/array marker parameters, missing markers, deceptive hosts, unsafe schemes, HTML, credential URLs, shortened links, and invalid saved data. Verify PHP/TypeScript output parity using non-secret shared fixtures.
- Feature cases: enable, disable while retaining a valid pin, clear a disabled map, reload, legacy omitted settings, non-contact rejection, cross-owner/page/block rejection, publication freeze, removal on republish, malformed publication fallback, and paid-domain content rendering.
- No browser runner is configured. Use manual browser evidence without installing one. Mocked/local tests do not establish live OpenStreetMap availability or visual pin accuracy.

## Notes for the AI

- Approved scope: Feature 18 and the full sharing-link workflow, on 2026-09-30. The user approved implementation by invoking `/implement` on 2026-09-30.
- Feature 18 has no prior archive or build record. Build attempt 1, the exact archive path, and branch availability were checked before writing this spec. Preserve these identities.
- Existing plan edits removing rich text and generated Vite cache changes are already present. Preserve them; do not include dependency cache changes in a feature commit.
- Reuse native iframe rendering and the existing block editor/save/publication paths. Avoid an API service or general embedding abstraction for this single provider.
- Source: https://wiki.openstreetmap.org/wiki/Export_tab documents full sharing links, Include marker, and the standard iframe embed. Keep attribution intact.
- Step 1 implemented: trusted PHP map URL helper, contact-only validation, persistence tests, and shared parser fixtures. Focused Pest checks passed: 70 tests, 221 assertions; PHP formatting and static analysis passed. No browser/live map verification has been performed.
- Viewport calculation: a 0.02-degree longitude by 0.01-degree latitude neighborhood rectangle, shifted within geographic bounds at edges; generated coordinates use six decimal places. Step 2 must match the shared fixtures.

### Step 2 review evidence

- Editor controls and unsaved map preview are implemented and approved by the user. Save/reload, discard, dirty detection, field errors, focus, and legacy defaults include map settings.
- `node --test tests/open-street-map.test.mjs`: 38 shared parser fixtures passed. Focused PHP map tests: 60 tests, 133 assertions passed. Frontend formatting/lint, TypeScript checks, and production build passed.
- Manual review: open a Contact block locally, paste an OpenStreetMap full sharing link with Include marker selected, and enable Show map. Confirm the pin and attribution; test save/reload, toggle off/on, discard, invalid links, keyboard focus, and a narrow viewport. Published rendering now uses the same layout.
- No browser automation tool is available in this session. The user reported a working editor preview; published iframe behavior and visual accuracy remain unverified in a browser.

- Step 2 operator report: the supplied map-view-only URL had no marker, and the corrected marker link produced a working preview. The user requested a map in the right half of the block; the preview now uses equal columns at the medium breakpoint and stacks on smaller screens. Save/reload, discard, and mobile review still need explicit confirmation.

### Step 3 review evidence

- Published contact maps now use the same right-half desktop layout and stacked mobile layout as the editor. Invalid legacy map settings omit the iframe while retaining contact details.
- Publication and customer-domain tests verify frozen map settings, changes only after Publish, map removal, escaped output, attribution, existing directions links, and customer-domain availability checks.
- Final `composer ci:check` passed: frontend formatting/lint and TypeScript, PHP formatting/static analysis, production build, and 846 Pest tests with 6,079 assertions. An initial run exposed a missing slug in the new test fixture; the fixture was corrected before the successful rerun.
- `node --test tests/open-street-map.test.mjs workers/domain-proxy/*.test.mjs` passed all 157 tests.
- Manual limitations: the operator confirmed the editor preview works and approved the layout. Published external iframe loading, keyboard interaction, save/discard flows, and narrow-screen appearance have not been independently observed in a browser.
- Independent review is selected for the persisted external-URL validation boundary. Await explicit approval for a local immutable review checkpoint; exclude and preserve generated Vite dependency cache changes.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":13406,"specSha256":"3542a843a42d2662958c46d9ff83383413833fd5b97f7b606e591e2cec34f4ae","branch":"refs/heads/feature/contact-map","head":"847798b72dcd450beaf95d24e3b6a627b8cda964","baseRef":"refs/heads/main","baseCommit":"7dbb225f90ec2266521f2a0af52565fa8286499b","sourceTree":"afb5d909e1790e2a7ec64d1108fcff337635be29","absentOptional":[]} -->

## Independent review

# Independent Review

**Status:** passed
**Target commit:** 847798b72dcd450beaf95d24e3b6a627b8cda964
**Base commit:** 7dbb225f90ec2266521f2a0af52565fa8286499b
**Base ref:** origin/main
**Spec hash:** 3542a843a42d2662958c46d9ff83383413833fd5b97f7b606e591e2cec34f4ae
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-30T14:33:53.985990+00:00
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-30T14:36:47.131250+00:00
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Handoff

Review the active spec and the complete `7dbb225f90ec2266521f2a0af52565fa8286499b..847798b72dcd450beaf95d24e3b6a627b8cda964` delta in a fresh
session or isolated subagent without the builder conversation. Run all Audit lenses from scratch.
Run Check when required above. Do not edit product code, accept findings, or
reuse the existing findings as the review scope.

## Commands

- `git rev-parse HEAD`, `git symbolic-ref refs/remotes/origin/HEAD`, `git merge-base origin/main HEAD`, `shasum -a 256 blueprint/context/current-feature.md`, and `git status --short`: passed; target, permitted base, exact spec hash, and clean target except review evidence verified before receipt.
- `git diff --check 7dbb225f90ec2266521f2a0af52565fa8286499b..847798b72dcd450beaf95d24e3b6a627b8cda964`: passed.
- `php artisan test --compact tests/Unit/OpenStreetMapUrlTest.php tests/Feature/ContactMapTest.php tests/Feature/CustomerDomainTransportTest.php tests/Feature/ChurchInformationBlockTest.php`: passed, 99 tests and 598 assertions.
- `node --test tests/open-street-map.test.mjs`: passed, 38 tests; no skipped or todo tests.
- `npm --ignore-scripts run types:check`: passed; existing generated helpers used without regeneration.
- `composer lint:check`: unavailable in this sandbox; parallel Pint could not open its localhost coordination socket (EPERM).
- `vendor/bin/pint --test app/Support/OpenStreetMapUrl.php app/Http/Requests/UpdateSiteBlockRequest.php app/Http/Controllers/PublishedSiteController.php tests/Feature/ContactMapTest.php tests/Feature/CustomerDomainTransportTest.php tests/Unit/OpenStreetMapUrlTest.php`: passed; serial fallback covers every changed PHP file.
- Targeted search for skipped, focused, incomplete, and placeholder tests in changed test files: none found.
- Offline Node relative-luminance calculation using current workspace tokens: reproduced F-02 contrast ratios of 4.16:1, 3.86:1, and 3.97:1.

## Evidence

- Independently reviewed the complete recorded base..target delta: all 15 changed files, including the planning/spec changes, PHP and TypeScript parsers, request validation, editor state and controls, published rendering, shared fixtures, and PHP/Node tests. Also traced existing ownership/persistence in SiteBlockController, publication snapshot copying, editor selection/discard/save/error behavior, and customer-domain rendering. Excluded dependencies, generated helpers, Vite caches/build output, and unrelated source from code review.
- Quality: changes reuse the existing content JSON and save/publication paths; no new dependency, route, migration, or generalized embedding service. Reviewed repository PHP/Vue/TypeScript, styling, accessibility, and proportionality standards.
- Security: only finite bounded coordinates feed fixed HTTPS OpenStreetMap resource URLs; pasted HTML or arbitrary hosts are not rendered or fetched server-side. Contact-only nested field allowlists, strict boolean handling, ownership/page boundaries, escaped output, and malformed snapshot fallback were inspected and exercised by focused tests.
- Performance: URL parsing is local and linear in input length, introduces no database query or server-side provider call, and embeds use native lazy loading. Preview parsing repeats a small number of times per contact render; no measured performance defect identified.
- Tests: shared parser fixtures cover allowlisted hosts, coordinates, hostile inputs and rounding; feature tests cover persistence/reload, legacy data, ownership, public freeze/republish/removal, escaped rendering, and customer-domain availability. Existing contact behavior remains covered by ChurchInformationBlockTest. No skipped, focused, or placeholder tests found in the reviewed test files.
- The verified spec records the builder's passing combined check (846 Pest tests, 6,079 assertions) and 157 Node tests. These broader results were inspected as recorded evidence, not claimed as independently rerun. The operator's editor-preview report is recorded in the spec, not independently observed here.

## Findings

- No new findings in the complete current-work review; no open or fixed P0/P1 findings.
- F-02 remains open P2. Re-examined unchanged muted text token and new map help-text use; appended fresh source contrast evidence. No repair or user acceptance claimed.
- F-06 remains open P2, preserved unchanged and outside this change's scope.

## Remaining risk

- Live OpenStreetMap iframe availability, visual pin accuracy, published narrow/wide layout, and browser keyboard/save/discard interactions were not independently observed. No browser harness is configured; Check was not required.
- `composer lint:check` could not run its parallel worker socket in the sandbox. The serial Pint fallback passed for all changed PHP files; the entire repository's parallel lint command was not independently rerun.
- Existing nonblocking F-02 and F-06 remain open. No vulnerability scan, live customer-domain transport, or provider request was performed.
