# Feature: Embed block

**From build-plan:** feature 19
**Build attempt:** 1
**Status:** verified
**Branch:** feature/embed-block
**Archive:** blueprint/history/features/19-embed-block.md

## Goal

Let an owner embed a public Google Calendar in a church page through a simple URL field. Support this provider first with fixed security restrictions rather than arbitrary iframe HTML or permissions.

## In scope

- Add an Embed block to the existing block picker, with an editable heading and Google Calendar embed URL.
- Show concise setup instructions: use a dedicated public church-events calendar, open its desktop settings, choose Integrate calendar, and copy only the HTTPS URL from the iframe's src attribute. Do not paste the complete HTML. Explain that making a calendar public exposes its shared event details; personal/private calendars should not be used.
- Validate the URL on the server and mirror its contract in the draft preview. Construct the iframe URL from validated values on a fixed provider host.
- Render the calendar with a descriptive title, lazy loading, full container width, a 600px initial height, zero border, and normal iframe scrolling. Offer an external Open calendar link with an accessible label.
- Match unsaved preview and published Blade rendering, including shared section spacing/content-width/heading styling. Preserve existing ownership, save/discard, navigation warnings, reordering, removal, and explicit Publish behavior.
- Contain iframe permissions and restrict frame navigation through a narrowly applied frame-src Content Security Policy on shared Inertia app documents and published responses. Preserve the existing video and map frame sources. Verify custom-domain response headers too.

## Out of scope

- Arbitrary sites, raw HTML, scripts, srcdoc, user-controlled sandbox/allow attributes, Google appointment booking, or private/signed-in calendars.
- Google APIs, OAuth, calendar creation, server-side provider requests, proxying, event storage, event editing, polling, or geocoding.
- Multiple calendars in one block, custom theme/colors, auto-height messages, configurable iframe permissions, or adding more providers in this feature.
- Changes to existing video/map sandbox behavior, unrelated security headers, dependency installation, deployment, or remote settings.

## Build loop

Use configured per-step review (`workflow.stepReview: every`). Stop after each passing step for user review. Checkpoint commits are enabled but need explicit approval; `/complete` creates the work commit. Independent review is selected for this external-content security boundary.

## Build steps

- [x] **1. Define and persist safe calendar settings.** Add block creation defaults and allowlists, a PHP Google Calendar URL helper, contact-independent embed validation, and matching TypeScript parsing with shared fixtures. Use the current JSON content storage and scoped block persistence.
      **Done when:** the owner can create and save/reload an Embed block; blank URLs produce an empty block without a frame; valid public-calendar embed links produce canonical trusted URLs; hostile inputs are rejected without changing saved content; other accounts/sites/pages remain denied. PHP/Node fixtures cover parser parity and focused Pest tests pass.
- [x] **2. Add owner controls and restricted draft rendering.** Add URL/heading fields, setup guidance, accessible validation/focus/clearing, draft preview, fallback link, and the editor response's narrow frame policy. Integrate dirty/save/discard and block labels/navigation.
      **Done when:** unsaved valid input renders the restricted iframe; empty or invalid input does not load a frame; checkbox-free permissions are fixed in code; save/reload/discard and error behavior follow existing patterns; CSP allows approved calendar/video/map frames and blocks platform or other-host redirects. Frontend checks pass. Verify real public Google Calendar interaction manually; if required permissions differ, stop for review rather than broadening them silently.
- [x] **3. Publish and verify the complete feature.** Add frozen Embed rendering, published frame policy, malformed-legacy fallback, public/customer-domain tests, and final verification.
      **Done when:** saved URL changes remain private until Publish; publishing/removal updates the block; invalid legacy URLs leave the page available without a frame; public and paid-domain pages emit the fixed attributes/policy; existing videos/maps still work. `composer ci:check` passes. Record actual browser evidence for a public calendar, keyboard access, event navigation, narrow/wide layout, fallback, and blocked navigation before claiming provider compatibility. Obtain a passing independent review before completion.

## Files / areas

- `app/Http/Requests/StoreSiteBlockRequest.php`, `UpdateSiteBlockRequest.php`, and `app/Http/Controllers/SiteBlockController.php`: block type, defaults, and validated persistence.
- A small provider-specific helper under `app/Support/` and matching preview helper under `resources/js/lib/`, reusing the established map/video parser pattern and shared test fixtures.
- `resources/js/pages/Sites/Show.vue`: picker/type/content, labels, controls, preview, state, and error handling.
- `app/Http/Controllers/SiteController.php`, `PublishedSiteController.php`, and the existing page-editor response path: narrow frame policy and safe block rendering. Confirm the exact response hook during Step 2, avoiding global policy changes.
- `resources/views/sites/published.blade.php` and existing shared styles: published Embed block.
- Existing publication snapshot and customer-domain transport paths; focused tests under `tests/Feature`, `tests/Unit`, and shared PHP/Node parser fixtures.

## Data / contracts

- New block type `embed`, content `{ heading: string, url: string, style?: existing shared block style }`; creation uses empty heading and URL. Heading uses the existing plain-text escaped rendering/fallback convention with fallback Calendar. URL is trimmed. Blank is allowed for unfinished blocks. A nonblank URL must be valid. No migration.
- Proposed input contract: at most 8192 characters; HTTPS, exact `calendar.google.com`, exact `/calendar/embed` path, no credentials, fragments, nonstandard port, backslashes, control characters, malformed encoding, or path normalization tricks. HTTPS port 443 is allowed and removed when canonicalizing. Ordinary calendar view/share links and complete HTML receive a specific correction message.
- Require exactly one scalar `src` query value. Decode once and require a nonempty printable calendar identifier with no whitespace/control or HTML delimiters. Reject duplicate/array parameters. Multiple-calendar links get a clear single-calendar message.
- Preserve only `src`, optional scalar `ctz` (IANA time zone validated on both runtimes), and optional `mode` in MONTH/WEEK/AGENDA. Default to AGENDA for a compact event list. Ignore other display metadata when building the trusted URL and explain this in help text. All generated values are safely URL-encoded; never pass the original URL through to the iframe.
- Fixed iframe source is `https://calendar.google.com/calendar/embed?...`. Its external Open calendar link uses this same canonical URL, opened with `noopener noreferrer`. Visible text and attributes are escaped; never use raw Blade output or v-html.
- Proposed fixed sandbox is `allow-scripts allow-same-origin`. These capabilities support Google's interactive renderer and normal provider origin; they do not mean the calendar shares the parent origin. Omit top-navigation, popups, popup-escape, forms, downloads, storage-access grants, and other optional capabilities. No camera/microphone/geolocation/payment permissions are granted. Use `referrerpolicy="no-referrer"` and a descriptive title. Compatibility is a manual gate, not an assumed fact.
- Apply frame-src to the shared Inertia app documents (including their entry pages) and public-page responses only, using fixed hosts needed by existing features: `https://calendar.google.com`, `https://www.youtube-nocookie.com`, `https://player.vimeo.com`, and `https://www.openstreetmap.org`. Do not include self, broad Google wildcards, data:, or blob:. Reject incompatible existing policy composition instead of replacing security headers blindly. This policy limits initial and redirected frame navigation; scripts plus same-origin must never be allowed to reach a platform or customer-page origin.
- No server fetch means no URL-driven server-side requests. No iframe DOM access or postMessage integration is introduced. Provider pages still receive visitor network information and may use Google cookies; say in owner guidance that Google supplies the content.
- Ownership/site/page scoping comes from the authenticated user's existing relations; no client owner/site/type reassignment. Publication snapshots freeze the embed configuration, not Google's event data: edits made in Google Calendar can appear live without publishing again. Explain this distinction to owners.
- Cross-origin frame load/error events do not reliably prove calendar success. Show an empty placeholder for missing input and useful validation for invalid input; keep the external link/help available for blocked, private, or unavailable calendars. Do not claim to detect public visibility automatically. Malformed persisted URLs suppress the frame without taking the site offline.

## Testing

- Baseline `composer ci:check` passed on 2026-09-30: 853 Pest tests, 6157 assertions, frontend formatting/lint/types, PHP formatting/static analysis, and production build.
- Shared PHP/TypeScript fixtures: encoded calendar IDs, mode/time-zone/defaults, missing/duplicate/array src, hostile hosts, credentials, schemes, ports, fragments, controls, malformed encoding, traversal, HTML, and non-embed links.
- Feature coverage: create/update/clear/reload, cross-owner/site/page denials, prohibited arbitrary HTML/iframe settings, publication freeze/removal, malformed saved fallback, escaped attributes, fixed sandbox/referrer/permission attributes, and frame-src on editor/public/customer responses. Exercise frame redirect prevention in actual browser verification; header assertions alone do not prove it.
- Regression coverage for existing video/map rendering and permitted frame hosts; final `composer ci:check`. Browser runner is not configured; do not install one. If browser verification is unavailable, record that limitation and stop before claiming the provider integration is proven.

## Notes for the AI

- User approved provider-restricted URL-only embedding and selected Google Calendar as the first provider on 2026-09-30. This spec proposes the exact fields, URL limits, defaults, and permissions for review before implementation.
- Feature 19, build attempt 1; planned branch and archive were checked for collisions and prior use. Freeze identities after approval.
- Plans were updated and overview regenerated for this approved addition; no new plan disagreements found. Preserve unrelated generated dependency cache work and previously retained stash.
- Sources: Google Calendar's Add a calendar to your website documentation (`https://support.google.com/calendar/answer/41207`) and public calendar guidance (`https://support.google.com/calendar/answer/37083`); MDN iframe sandbox/referrer documentation (`https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/iframe`) and frame-src (`https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/frame-src`).

## Step 1 review evidence

- Added the Embed block type and empty heading/URL defaults to the existing owner-scoped creation and update paths. Nonblank URLs are trimmed and validated; blank URLs are retained for unfinished blocks. No database migration, new route, dependency, server fetch, or iframe rendering was introduced.
- PHP and TypeScript helpers generate only canonical calendar.google.com embed URLs, retaining a single calendar identifier, recognized time zone, and supported view mode. Other display metadata is ignored; IDs and time-zone values are safely encoded.
- Focused Pest checks passed: 48 tests, 101 assertions. Node checks passed: 39 shared parser fixtures. Changed PHP formatting, PHP static analysis, frontend formatting/lint, and TypeScript checks passed.
- Ownership/site/page denial and rejection of hostile URLs, HTML, extra iframe attributes, and oversized input are covered. Actual Google rendering, browser containment/CSP, controls, and publication rendering remain Steps 2 and 3.
- Time-zone validation uses each runtime's existing IANA support; case-insensitive zone names and standard church time zones are covered. Include alias/runtime compatibility in independent review; no Google integration compatibility is claimed from parser tests.

## Approved Step 2 policy adjustment

- On 2026-09-30 the user approved extending the fixed frame-source restriction to builder pages. An editor-only AJAX response cannot set the browser document policy during Inertia navigation. Apply the policy to shared Inertia app documents, including guest entry documents that may navigate into the builder, plus their Inertia responses. This changes only frame-src; it does not add other global CSP directives or replace existing security policies. Published-page policy remains Step 3.
- Preserve existing CSP by intersecting an additional policy. A stricter upstream policy can still block calendars; report that conflict during real browser verification rather than relaxing it.

## Step 2 review evidence

- Added the Embed picker entry, heading and URL controls, concise Google setup/public-sharing guidance, URL-only errors, draft preview, descriptive title, fixed sandbox and denied device/payment permissions, no-referrer policy, lazy loading, and external Open calendar fallback. Reused existing URL draft/save/discard state and included Embed URL edits in dirty detection; no text body field is exposed.
- Shared Inertia document responses carry the fixed frame-source restriction, including guest entry documents that can navigate into the builder. Existing CSP headers are retained as intersecting policies. Focused tests verify dashboard/settings/editor and Inertia responses, initial app entry, and policy preservation.
- Focused Embed tests passed: 11 tests, 78 assertions. Frontend formatting/lint, TypeScript, production build, changed PHP formatting, and PHP static analysis passed.
- Step 2 remains unchecked until operator confirmation of a real public Google Calendar under the sandbox. No browser runner/tool is available in this session. Loading/events navigation, save/discard interaction, keyboard access, narrow layout, and actual redirect prevention have not been observed in a browser. Published rendering remains Step 3.

- Operator reported the calendar preview works well on 2026-09-30 and approved continuing with published rendering. User-facing block name is now Calendar; feature 19 branch/archive identities and internal `embed` type remain unchanged. A provider-expanded block is separate future work, not included here.

## Final implementation evidence

- Published Calendar blocks use escaped headings, canonical provider-only URLs, the same fixed sandbox/permissions/referrer policy as the editor, and external fallback links. Published responses, including customer-domain transport, carry the fixed frame-source policy. Empty/malformed saved URLs omit the iframe and preserve page availability.
- Tests prove frozen configuration, updates and removal only on Publish, escaped output, restrictive attributes, malformed snapshots, and paid-domain output. Existing video/map sources remain allowed by the frame policy.
- Final `composer ci:check` passed: formatting/lint, TypeScript, PHP formatting/static analysis, production build, and 910 Pest tests with 6324 assertions. Node Calendar fixtures passed all 39 cases. Initial final PHP analysis caught a response return-type mismatch in the policy helper; changed the helper to mutate headers without returning a widened response and reran analysis and the complete gate successfully.
- Operator confirmed the editor calendar works. Published provider interaction, mobile/keyboard flows, and actual redirect blocking remain unobserved in a browser; automated header assertions are not proof of browser enforcement. No completed provider-wide compatibility claim is made.
- Independent review is required for this iframe/security boundary. Await approval for the exact local checkpoint before launching the fresh reviewer. Existing nonblocking P2 findings remain unchanged.

## Review repairs

- [x] Step 4: Repair F-07, F-08, and F-09. Preserve intersecting origin CSP policies through customer HTML GET/HEAD responses; expose Calendar Heading and URL without Text; share one supported time-zone list between preview and server. Done when focused regressions and the combined verification gate pass. Fresh independent review remains required before completion.

## Review repair evidence

- F-07: successful customer HTML GET/HEAD responses preserve the trusted origin CSP, including comma-separated intersecting policies; asset responses still filter it. Actual Worker regressions pass.
- F-08: Calendar now exposes the existing Heading control and hides Text. Offline evaluation of the actual Vue template conditions confirms heading=true and body=false for embed. Existing heading persistence/publication tests pass; browser save/reload remains unobserved after this repair.
- F-09: one checked-in lowercase time-zone registry, generated from PHP ALL_WITH_BC, is shared by PHP and TypeScript. Shared ACT, AET, US/Pacific-New, and Factory cases now agree. The PHP helper reads the repository resource without requiring a booted Laravel application, preserving standalone unit testing.
- Focused Pest: 81 tests, 476 assertions. Node Calendar plus both Worker suites: 163 tests passed. Final composer ci:check: 914 Pest tests, 6328 assertions; frontend formatting/lint/types, PHP formatting/static analysis, and production build passed. git diff --check passed.
- No dependencies or remote changes. Production customer-domain protection requires deploying the updated Worker; no deployment is authorized or performed here.
- F-07/F-08/F-09 marked fixed, awaiting closure by a fresh reviewer of a newly approved immutable checkpoint. Existing unrelated P2 findings remain open.

- [x] Step 5: Repair F-10 by formatting the active spec with the installed formatter. Done when the combined project check passes. No product behavior changes.

- F-10 repair: active spec formatted; composer ci:check passed with 914 Pest tests and 6328 assertions, plus frontend formatting/lint/types, PHP formatting/static analysis, and production build. No product source changes. Fresh independent receipt required for the revised tracked spec.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":18939,"specSha256":"93ca4f9b01916dbd8ed7090a0ccaa95e22eb33daa485eb42cdf133467ff88305","branch":"refs/heads/feature/embed-block","head":"5ba081a98582c913caeb69b975562ae45b8533a6","baseRef":"refs/heads/main","baseCommit":"261f2fcf5632148fa01479e277e91c9514526fc6","sourceTree":"66cbb3f27e6c7ca6a9ce90b1e57dc0b747ccbabe","absentOptional":[]} -->

## Findings

### 19/F-07 [P1] closed - Preserve the frame policy through the customer-domain Worker

**File:** workers/domain-proxy/worker.mjs:226
**Found:** 2026-09-30 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** Calendar publication now relies on a frame-src CSP to constrain navigation of the scripts-plus-same-origin iframe, including on customer domains. Laravel attaches that header, but the customer Worker constructs a new allowlisted header set without Content-Security-Policy. A successful customer HTML response therefore reaches the browser without the required frame restriction. The new CustomerDomainTransportTest checks the Laravel endpoint before this header-dropping hop. An offline call to the actual forwardRequest function, with an upstream Calendar policy, returned status 200 and a null Content-Security-Policy for both GET and HEAD. This confirms the missing required security boundary, not a demonstrated provider exploit.
**Suggested fix:** Explicitly preserve the trusted origin's Content-Security-Policy on successful customer HTML responses in the existing Worker response filter, including multiple intersecting policies. Keep filtering unrelated origin headers. Add a focused Worker regression for GET and HEAD with the Calendar policy and an existing stricter policy. No broad proxy/header redesign is needed.
**Resolution:** Confirmed at 8884b651aa49c6fa95aff4619c4c80df262a681f; source inspection and injected offline upstream responses reproduce the dropped header. No live customer-domain request or deployment was performed.

**Repair evidence (builder, 2026-09-30):** Worker now forwards trusted HTML CSP for GET and HEAD while filtering assets. The actual Worker regression verifies intersecting policies; all 163 Calendar/Worker Node tests pass. Marked fixed only; fresh independent review must confirm closure.

**Independent closure evidence:** Fresh independent review by codex / gpt-6-astra at `844823f23af1376fe44f0a9c4c79f59afde14b96` on 2026-09-30 confirms trusted origin CSP is preserved through actual Worker HTML GET and HEAD responses, including intersecting policies, while asset filtering remains intact. Reviewed the complete proxy validation/response path and Laravel customer transport. The offline Worker regression passes in the 163-test Node run; the customer transport regression passes in the 914-test Pest suite. Original defect removed; no new defect found in this repair. Closed. Production Worker deployment and real browser enforcement remain unverified.

### 19/F-08 [P1] closed - Show the Calendar heading control instead of an unsaved Text control

**File:** resources/js/pages/Sites/Show.vue:3277
**Found:** 2026-09-30 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The new embed exclusion was added to the Heading control's condition, while the Text textarea's condition at line 3327 still includes embed blocks. Every Calendar therefore hides its required editable heading and exposes a body field that is absent from both the preview and the save payload at line 1219. Typing into Text sets the dirty flag; a successful Save copies that draft into savedBody and reports success even though the server never received it, so the text disappears on reload. Evaluating the actual parsed Vue template conditions with type embed gives heading=false and body=true. This contradicts the heading-plus-URL contract and the spec's claim that no body field is exposed.
**Suggested fix:** Remove the embed exclusion from the existing Heading condition and add it to the existing Text condition. Verify Calendar exposes Heading and URL only, and that heading edits preview, save, reload, and publish correctly. Use the existing controls and save path.
**Resolution:** Confirmed at 8884b651aa49c6fa95aff4619c4c80df262a681f by the Vue compiler AST conditions and the existing dirty/save-success/payload paths. This was an offline source-level reproduction, not a browser interaction.

**Repair evidence (builder, 2026-09-30):** Existing Calendar Heading condition restored and Text condition excludes embed. Offline actual Vue template conditions give heading=true and body=false; focused persistence/publication tests pass. Browser interaction remains unobserved. Marked fixed only; fresh independent review must confirm closure.

**Independent closure evidence:** Fresh independent review by codex / gpt-6-astra at `844823f23af1376fe44f0a9c4c79f59afde14b96` on 2026-09-30 confirms the actual parsed Vue template exposes Heading and Calendar URL and hides Text for embed. Reviewed draft initialization, preview, dirty detection, reset/discard, save payload, success/error handling, shared styling, and frozen publication. The corrected offline condition probe gives heading=true, body=false, calendar-url=true; persistence/publication tests pass in the 914-test Pest suite. Original defect removed; no new defect found in this repair. Closed. Browser save/reload and keyboard interaction remain unobserved.

### 19/F-09 [P2] closed - Align Calendar time-zone validation between preview and server

**File:** resources/js/lib/google-calendar.ts:41
**Found:** 2026-09-30 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** Successful Intl.DateTimeFormat construction is not the same allowlist as PHP timezone_identifiers_list(ALL_WITH_BC) in GoogleCalendarUrl.php:38. On the installed runtimes, ctz=ACT, ctz=AET, and ctz=US%2FPacific-New produce a canonical iframe URL in TypeScript but null in PHP, so the owner sees a valid preview and then a Save error. Conversely, ctz=Factory is accepted by PHP but rejected by TypeScript. The 39 shared fixtures pass because they do not exercise these differences. This is a narrow validation/parity defect, not a host or iframe-security bypass.
**Suggested fix:** Establish one authoritative supported-zone set or canonicalization contract and use it on both sides, reusing PHP's existing zone data and the current frontend data path if practical. Add shared alias/boundary fixtures, including ACT and Factory, so unsupported values cannot be presented as saveable. Avoid an additional timezone dependency or a full timezone abstraction.
**Resolution:** Confirmed at 8884b651aa49c6fa95aff4619c4c80df262a681f with direct calls to the actual PHP and TypeScript helpers. Standard America/Chicago, UTC, EST, and America/Coyhaique cases agreed; the listed aliases did not. No provider request was made.

**Repair evidence (builder, 2026-09-30):** PHP and TypeScript now consume one checked-in supported time-zone registry; shared ACT/AET/US-Pacific-New/Factory regressions agree. Focused Pest 81 tests/476 assertions and full gate 914 tests/6328 assertions pass. Marked fixed only; fresh independent review must confirm closure.

**Independent closure evidence:** Fresh independent review by codex / gpt-6-astra at `844823f23af1376fe44f0a9c4c79f59afde14b96` on 2026-09-30 confirms PHP and TypeScript consume the same checked-in 598-entry lowercase registry, which matches the installed PHP ALL_WITH_BC identifiers. All 43 shared fixtures pass in both runtimes, including rejected ACT/AET/US/Pacific-New and accepted Factory. Reviewed the complete parsers and registry-loading path. Original time-zone allowlist mismatch removed; no new defect found in this repair. Closed.

### 19/F-10 [P2] closed - Restore the required combined verification gate

**File:** blueprint/context/current-feature.md:112
**Found:** 2026-09-30 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The exact approved checkpoint fails `composer ci:check` at its first `npm run check` step. Vite Plus reports formatting issues in the active spec, which contains an extra blank line before Review repair evidence. The spec requires the combined gate to pass, so its earlier passing claim does not prove this checkpoint. Application tests and targeted frontend checks pass independently; this is a verification/doc-formatting defect rather than a product behavior defect.
**Suggested fix:** Format only the active spec with the existing formatter, rerun `composer ci:check`, and prepare a new approved checkpoint/request with the resulting spec hash. Do not waive the check or rewrite this receipt to represent different bytes.
**Resolution:** Confirmed at `844823f23af1376fe44f0a9c4c79f59afde14b96`; reviewer left the spec unchanged. Combined command exits 1; targeted changed frontend checks, TypeScript, PHP formatting/static analysis, production build, 914 Pest tests/6328 assertions, and 163 Node tests pass independently.

**Repair evidence (builder, 2026-09-30):** Formatted the active spec using the installed formatter. Combined composer ci:check passed: 914 Pest tests/6328 assertions, frontend formatting/lint/types, PHP formatting/static analysis, and production build. Marked fixed pending fresh independent review.

**Independent closure evidence:** Fresh independent review by codex / gpt-6-astra at `5ba081a98582c913caeb69b975562ae45b8533a6` on 2026-09-30 reviewed the exact tracked spec and complete feature delta. `composer ci:check` passed after approved local socket access for parallel PHP tooling: frontend formatting/lint/types, PHP formatting/static analysis, production build, and 914 Pest tests with 6328 assertions. The original spec-formatting failure is gone; no new defect found in the repair. F-10 closed.

## Independent review

# Independent Review

**Status:** passed
**Target commit:** 5ba081a98582c913caeb69b975562ae45b8533a6
**Base commit:** 261f2fcf5632148fa01479e277e91c9514526fc6
**Base ref:** origin/main
**Spec hash:** 93ca4f9b01916dbd8ed7090a0ccaa95e22eb33daa485eb42cdf133467ff88305
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-30T17:26:07.275506+00:00
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-30T17:29:48.996726+00:00
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Commands

- `composer ci:check`: passed with approved local socket access. Frontend formatting/lint/types, PHP formatting/static analysis, production build, 914 Pest tests, 6328 assertions. Initial sandbox run stopped at parallel Pint with local TCP EPERM; the complete approved rerun exited 0.
- `node --test tests/google-calendar.test.mjs workers/domain-proxy/content.test.mjs workers/domain-proxy/worker.test.mjs`: passed, 163 tests, no skipped/cancelled/todo tests. Includes all 43 shared Calendar fixtures and actual Worker GET/HEAD CSP regressions.
- Offline Node probe using installed Vue compiler AST: passed; Calendar Heading=true, Text=false, Calendar URL=true from the actual template conditions.
- Offline PHP registry comparison: passed; 598 lowercase entries exactly match installed `DateTimeZone::ALL_WITH_BC` identifiers.
- `git diff --check`: passed.
- Git HEAD/default-ref/merge-base/status checks and raw SHA-256 comparison: passed before and after review. Target, base and tracked spec bytes unchanged; only review/findings evidence differs from target.
- Targeted test-marker inspection: no skipped, focused, or placeholder markers found in the changed tests and relevant Worker suites.

## Evidence

- Fresh codex / gpt-6-astra subagent with no builder transcript reviewed the complete `261f2fcf5632148fa01479e277e91c9514526fc6..5ba081a98582c913caeb69b975562ae45b8533a6` delta across all four lenses. Base ref `origin/main` is the locally recorded remote default; active branch is `feature/embed-block`.
- Reviewed every changed application/test file: StoreSiteBlockRequest, UpdateSiteBlockRequest, SiteBlockController, PublishedSiteController, HandleInertiaRequests, EmbedFramePolicy, GoogleCalendarUrl, the TypeScript Calendar helper and full shared time-zone registry, Sites/Show.vue changes, published Blade, EmbedBlockTest, CustomerDomainTransportTest additions, GoogleCalendarUrlTest, Calendar fixtures/Node runner, and Worker implementation/content regression changes.
- Reviewed active spec, plan/overview changes, and nearby contracts: authenticated routes, bootstrap middleware, SiteController editor payload, scoped block creation/update/removal/reordering, publication snapshot action, shared block styling, draft initialization/reset/dirty/save/error paths, DomainProxyController/DomainProxyResponse, and full Worker validation/response filtering.
- Quality: follows existing Form Request, scoped persistence, provider helper, shared block style and publication patterns. No dependency or migration added. Calendar controls expose the intended saved fields; existing draft/discard and explicit Publish paths include the URL.
- Security: fixed-host canonical URL generation, escaped rendering, prohibited iframe settings, site/page ownership, frozen publication, fixed sandbox/permissions/referrer attributes, Inertia entry-document frame policy, intersecting CSP preservation, and customer Worker GET/HEAD propagation reviewed. No server-side provider request introduced.
- Performance: bounded URL parsing, one cached PHP time-zone registry, shared frontend Set, lazy frame loading, existing eager snapshot relationships and rendering path. No new query loop, polling, or unbounded feature-specific work found. No profiling claim.
- Tests: regression assertions cover saved-state protection, owner/site/page denial, malformed publication fallback, frozen settings/removal, header composition and actual Worker filtering. Existing video/map regression coverage passed in the complete PHP suite; allowed hosts remain present in the frame policy. Header assertions are not browser-enforcement proof.
- Applicable standards: project AGENTS.md, coding-standards.md, ai-interaction.md, config, verified feature contract, and project-local independent-review record contract. Generated build/Wayfinder/cache output, dependencies, and third-party provider contents excluded from source review. Request/findings files were evidence, never the code-review checklist.
- Reconfirmed F-07/F-08/F-09 repairs through complete source review and passing regression/probe results. Those findings remain closed. F-10 closed against this exact checkpoint's passing combined command.

## Findings

- No new findings or open/fixed P0/P1 blockers found in this complete current-work review.
- F-10: closed after independent verification of the formatted active spec and full combined gate.
- F-07, F-08, F-09: existing closed status confirmed; Worker CSP forwarding, Calendar controls, and shared time-zone validation repairs remain sound.
- F-02 and F-06: pre-existing open P2 findings retained unchanged; no acceptance or unrelated repair performed.

## Remaining risk

- No browser runner is configured and no browser verification was performed in this review. Public Google Calendar interaction, event navigation, keyboard/narrow-layout behavior, live fallback, and redirected-frame blocking remain unobserved. The spec records an operator report that editor preview works; that is not independent provider-wide compatibility evidence.
- Production customer-domain protection requires deployment of the updated Worker. No deployment, live customer request, remote change, or external provider call was performed.
- Initial sandbox `composer ci:check` was unavailable at parallel Pint because local TCP sockets were denied; this limitation was resolved by the approved complete rerun. No automated verification command remains blocked.
- F-02/F-06 remain open nonblocking P2 findings. No current dependency vulnerability scan or runtime performance profile was run.
