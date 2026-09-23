# Feature: Site workspace

**From build-plan:** feature 1
**Build attempt:** 1
**Branch:** feature/site-workspace
**Status:** verified

## Goal

Let a signed-in user create, list, open, and rename multiple blank sites. Each site belongs to exactly one user, and only that user can see or change it. A new site is a real saved workspace even before page blocks exist.

## Design reference

- Use the approved `prototypes/dashboard.html` and `prototypes/theme.css` for the workspace's calm, light visual direction. Port shared tokens before building the feature UI.
- Adapt the prototype's site cards to the data available now. Publishing, domain, billing, and preview statuses belong to later features; do not show them as if they work.

## In scope

- Replace the starter dashboard content with an owner-only site list, a clear empty state, and a create-site form.
- Open a site at an authenticated site route with a useful blank-site state that becomes the entry point for Feature 2's block editor.
- Rename a site from its workspace view. Show the updated name in both the view and dashboard.
- Provide useful pending, success, validation, and unexpected-failure feedback for create and rename; preserve entered text on failure and prevent duplicate submissions while a request is pending.
- Keep site names and other user-controlled text safely escaped in the Vue UI.

## Out of scope

- Deleting, transferring, or sharing site ownership; inviting collaborators.
- Blocks, themes, uploads, public previews, publishing, subscriptions, domains, SSL, or multipage content.
- A public site slug or URL. Feature 5 can define that contract when publishing is built.

## Build loop

- Implement the steps in order. The configured Guided cadence requires a review and approval after **each** step before continuing.
- After an approved, passing step, offer the configured optional checkpoint commit. `/complete` creates the final feature commit after its final gates.
- Keep each step's tests focused on the behavior it adds; record actual results and any manual observations without claiming unrun checks.

## Build steps

- [x] **1. Persist owned sites.** Add a `sites` table, `Site` model and factory, and the `User` relationship. Store a generated primary key, a required `user_id` foreign key, and a site `name`; use the project's normal timestamps. Ensure deleting a user cannot leave an orphaned site under the chosen foreign-key rule. **Done when:** migration and focused model/database tests pass, including one user with multiple sites and distinct owners.
- [x] **2. List and create sites.** Keep the existing authenticated dashboard as the workspace, query only the signed-in user's sites, and add a server-validated create action. Port the prototype's shared tokens, then build the list, empty state, and create form. A valid create saves an owned blank site and opens it; the site route must render a minimal working blank view at this step. **Done when:** a new account can create two sites and see both; a second account sees neither; blank or whitespace-only and overlong names return field errors without saving; pending, failed, and empty states are observable; relevant feature tests and frontend build pass.
- [x] **3. Open and rename an owned site.** Make dashboard cards open the blank-site view and provide an accessible rename form there. Scope reads and updates to the signed-in user's sites, returning 404 for another owner's site; unauthenticated requests follow the existing login flow. Update the visible name after success and provide clear field errors and unexpected-failure feedback. **Done when:** the owner can open and rename a site and sees the new name on return to the dashboard; another account cannot open or rename it by changing the route ID; invalid input preserves the current name; focused feature tests, frontend build, and a manual responsive walkthrough pass.

- [x] **4. Repair F-01 failure handling.** Cancel Inertia's default HTTP and network error handling after showing neutral feedback in both create and rename forms. **Done when:** simulated HTTP and network failures suppress default error handling, preserve entered text, and finish the pending state; TypeScript, build, and existing PHP tests pass.

## Files / areas

- `database/migrations/` for site persistence; `app/Models/Site.php`, its factory, and `app/Models/User.php` for the relationship.
- `routes/web.php` and a site controller/request under `app/Http/` for authenticated, owner-scoped actions and validation.
- `resources/js/pages/Dashboard.vue` for the workspace; a new site page under `resources/js/pages/` for the blank view and rename form; existing app layout/sidebar only where needed to make navigation coherent.
- `resources/css/app.css` or its existing token entry point for approved prototype colors, type, and spacing.
- `tests/Feature/` for persistence, validation, authentication, and cross-owner access.

## Data / contracts

- Site identity is the database-generated primary key. Authenticated routes may use that key; a public URL is not assigned in this feature.
- `sites.user_id` is a non-null foreign key to `users.id`; ownership is assigned from the authenticated server-side user, never accepted from form input. A user can own multiple sites. Site queries for dashboard, detail, and update start from that user's `sites()` relationship.
- `sites.name` is a required, trimmed Unicode string of at most 255 characters. Whitespace-only input is invalid. Names need not be unique; identity and authorization use the site ID. Store and render the validated name as plain text, without interpreting HTML.
- Create accepts only `name`; rename changes only `name`. Validation errors attach to the labeled name field, are announced and focusable, and clear when corrected or after success. Forbidden cross-owner IDs yield the same 404 result as missing IDs, without exposing whether a site exists.
- Requests use existing session authentication and CSRF behavior. Failed submissions must not create a site or change the saved name. Unexpected failures show a neutral message without exposing internals or discarding the typed value.

## Testing

- Use the existing Laravel feature test command for database, validation, guest redirects, owner-only listing, owner create/open/rename, and cross-owner 404s. Keep tests against real HTTP routes and database state.
- Run the existing frontend build after UI changes. Use the project's existing checks during implementation and `/complete`; no browser test command is configured, so manually inspect desktop and narrow layouts, keyboard focus, labels, pending states, and the blank-site flow.
- No live app, browser, or integration result has been observed during specification.

### F-01 repair verification (2026-09-23)

- Executed both actual form submission functions with installed Inertia useForm and HTTP/network failure handlers: six simulated cases (500, 419, network failure for create and rename) passed. Neutral feedback remained, default error handling was suppressed, entered names remained, and onFinish cleared processing. This is isolated execution evidence, not a live browser walkthrough.
- `npm run types:check`, `npm run build`, and `composer test` passed (Pint, PHPStan, 56 tests / 235 assertions). Build regenerated the repository-managed production assets.
- The previous audit reported broad formatting failures in `composer ci:check`; that unrelated formatting debt remains.
- F-01 awaits independent re-review against a new approved checkpoint.

## Notes for the AI

- The approved prototype includes future site statuses. Use its layout and tokens while representing only actual blank sites in this feature.
- Existing self-service registration and authenticated dashboard routing are already present. Reuse Fortify, Inertia, the app layout, and current request/test conventions; add no new package or service.
- Keep the blank-site route stable for the upcoming block editor, but do not build editor behavior now.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":8020,"specSha256":"fde4aa8d66f50b5bbc5bf26e82cd47d4b99b5d7e2b9a021459b3827410ef3bea","branch":"refs/heads/feature/site-workspace","head":"4bcbb62a261585585cd249e7a1ec3dff7405b729","baseRef":"refs/heads/main","baseCommit":"ee98a12c0eb63ee9fd015255092e6e18fbe003f3","sourceTree":"f14fbee0002861b74d087447600fd3cc95dd6ecb","absentOptional":[]} -->

## Findings

### 1/F-01 [P1] closed - Suppress Inertia's raw error modal after handling form failures

**File:** resources/js/pages/Dashboard.vue:36; resources/js/pages/Sites/Show.vue:40
**Found:** 2026-09-23 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** Both `onHttpException` callbacks set a neutral message but return `undefined`. The installed Inertia 3 response handler continues to `dialog_default.show(response.data)` for a non-Inertia error unless this callback returns `false`. A create or rename that receives an HTML 500/419 response therefore still opens the raw error page over the workspace, contrary to the spec's neutral unexpected-failure feedback contract; a debug response can include server internals. A read-only Node reproduction using the actual callbacks and installed handler confirmed `neutralMessageSet: true` and `rawErrorModalShown: true` for both pages. No application-wide cancellation handler exists.
**Suggested fix:** Return `false` after assigning the handled HTTP-error message in both pages. Also cancel the handled network-error default by returning `false`, then verify both forms against a simulated server error and network failure while preserving the typed value and clearing the pending state. Use the existing Inertia callbacks; no dependency is needed.
**Resolution:** Fixed by `/implement` on 2026-09-23: both HTTP and network error callbacks now return false after assigning neutral feedback. Six isolated executions using the actual submission functions, installed useForm, and installed core failure handlers passed for HTTP 500, HTTP 419, and network failure on both forms; entered names survived and onFinish cleared processing. TypeScript, production build, Pint, PHPStan, and 56 Pest tests / 235 assertions passed. Closed by fresh independent Codex/gpt-6-astra review on 2026-09-23 at checkpoint 4bcbb62a261585585cd249e7a1ec3dff7405b729: reviewed the complete feature delta and independently executed both actual submission functions through installed useForm and extracted installed Inertia core HTTP/network handlers. HTTP 500, HTTP 419, and network failures for both forms suppressed default handlers, retained typed names and neutral feedback, and cleared processing through onFinish. No defect introduced by the repair was found.

## Independent review

# Independent Review

**Status:** passed
**Target commit:** 4bcbb62a261585585cd249e7a1ec3dff7405b729
**Base commit:** ee98a12c0eb63ee9fd015255092e6e18fbe003f3
**Base ref:** main
**Spec hash:** fde4aa8d66f50b5bbc5bf26e82cd47d4b99b5d7e2b9a021459b3827410ef3bea
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-23T12:28:26.830280+00:00
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-23T12:31:44.957863+00:00
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Handoff

Review the active spec and the complete `ee98a12c0eb63ee9fd015255092e6e18fbe003f3..4bcbb62a261585585cd249e7a1ec3dff7405b729` delta in a fresh
session or isolated subagent without the builder conversation. Run all Audit lenses from scratch.
Run Check when required above. Do not edit product code, accept findings, or
reuse the existing findings as the review scope.

## Commands

- `git status --short`, `git status --porcelain --untracked-files=all`, `git branch --show-current`, `git rev-parse HEAD`, `git merge-base main HEAD`, and `git ls-files --stage -- blueprint/context/current-feature.md`: passed; feature branch, exact target/base, tracked spec, and only permitted evidence changes confirmed.
- `shasum -a 256 blueprint/context/current-feature.md`: passed; exact recorded spec hash matched. No snapshot field is present or needed for this tracked spec.
- `git diff --stat main...HEAD`, `git diff --name-status main...HEAD`, targeted complete source diffs, and `git diff --check main...HEAD`: passed; full feature delta enumerated and reviewed, no whitespace errors.
- `npm run types:check`: passed.
- `npm run check`: failed; 89 formatting files reported, predominantly existing Blueprint/prototype files and generated Wayfinder output. The command stops at formatting, so later lint stages are not claimed passed.
- `composer test`: unavailable as a complete command in this sandbox; parallel Pint cannot bind its loopback socket (EPERM). No application test failure was produced by this invocation.
- `vendor/bin/pint --test`: passed; serial fallback.
- `vendor/bin/phpstan analyse --debug --no-progress`: passed; zero errors.
- `php artisan test`: passed; 56 tests, 235 assertions.
- `node --input-type=module` with a read-only heredoc extracting the actual createSite/renameSite functions, installed useForm callbacks, and installed Inertia core failure handlers: passed six simulations (HTTP 500, HTTP 419, network failure for each form). Asserted neutral feedback, default-handler suppression, typed-value retention, pending start, and pending completion.
- `node --input-type=module` with a read-only relative-luminance calculation: confirmed F-02 ratios for all three light backgrounds.
- `rg -n '(skip\(|only\(|todo\(|markTestSkipped)' tests`: inspected; only the existing conditional Fortify skip helper found. No focused or skipped feature tests ran; existing Unit/ExampleTest.php is a scaffold placeholder outside this delta.

## Evidence

- Reviewed the complete `ee98a12c0eb63ee9fd015255092e6e18fbe003f3..4bcbb62a261585585cd249e7a1ec3dff7405b729` delta on `feature/site-workspace`, against local `main`, across quality, security, performance, and tests. Fresh child received no builder transcript; exact declared runtime identity is Codex/gpt-6-astra.
- Source scope: SiteController, SiteNameRequest, Site/User models, SiteFactory and sites migration, routes/web.php, HandleAppearance, Dashboard.vue, Sites/Show.vue, AppSidebar.vue, useAppearance.ts, app.css, app.blade.php, SitePersistenceTest and SiteWorkspaceTest. Reviewed generated route/action changes for route consistency; followed nearby bootstrap middleware, Inertia sharing/layout setup, installed failure handlers, and existing dashboard tests.
- Applied AGENTS.md proportionality, coding-standards.md Laravel ownership/validation, Vue text escaping and feedback, existing authentication/CSRF conventions, and the verified feature spec. All site reads/writes start from the current user's relationship; ownership cannot be mass assigned; payloads expose only site ID/name; cascade deletion prevents orphans. User-controlled names are rendered as text. Feature queries do not introduce N+1 work.
- Database/HTTP tests exercise multiple sites, owner isolation, foreign-owner 404s, guests, invalid create/update preservation, Unicode whitespace trimming, ownership override rejection, and successful rename/listing. No new dependency or speculative service was introduced.
- F-01 repair was independently reproduced through the real form submission functions and installed Inertia handlers; returning false cancels defaults and onFinish clears processing in all six simulations.
- Exclusions: generated/minified `public/build/assets/*` and build manifest internals were enumerated but not treated as hand-authored code; no rebuild was run because it would mutate tracked assets. Third-party dependencies were excluded except targeted installed Inertia code needed to validate F-01. Review/findings records were excluded from product review. No external scanner, network action, commit, or source edit occurred.
- Revalidated HEAD, base, spec bytes, and all tracked/untracked paths immediately before writing this receipt. Only findings.md and review.md were written by the review.

## Findings

- F-01 [P1] closed: repaired failure handling independently confirmed, with no new defect introduced by the repair.
- F-02 [P2] open: muted light-mode text has insufficient contrast. This is non-blocking under the independent receipt contract.
- No open or fixed P0/P1 findings remain. No confirmed security, performance, or test defect was found in the reviewed delta.

## Remaining risk

- `composer test` could not complete in its configured parallel form because sandbox loopback binding was denied. Its checks passed independently using serial Pint, PHPStan debug mode, and the full Pest suite.
- `npm run check` remains failing on 89 formatting files, including generated routes/actions. This receipt does not waive that failure or assert the combined local check passed.
- No independent live browser walkthrough, screenshot, keyboard interaction, or production MySQL run occurred. PHP integration tests use isolated in-memory SQLite; failure simulations are isolated execution, not a live browser flow. Check is not required by this request.
- No production build was rerun because generated production assets are tracked and review cannot modify them; generated/minified asset correctness is not independently established here.
- F-02 remains an open non-blocking accessibility finding; suggested next repair is the single existing muted-color token.
- Dashboard loads all sites for the current owner. No workload size is established and no runtime profiling was performed, so this is not a confirmed performance defect or a demand for pagination.
