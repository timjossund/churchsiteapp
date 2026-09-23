# Feature: Editor foundation

**From build-plan:** feature 2a
**Build attempt:** 1
**Branch:** feature/editor-foundation
**Status:** verified

## Goal

Turn an owned blank site into a usable single-page editor. A user can add about, plain-text, and heading-and-text blocks, select a block to edit its fields beside a visible page preview, remove blocks, and freely reorder them. Content and order survive a reload. Parent Feature 2 stays open until 2b and 2c are complete.

## In scope

- Keep the existing authenticated site URL and rename flow. Replace the blank-site message with a block list, page preview, and selected-block side panel; a site with no blocks remains a clear empty state.
- Use the built-in top navigation layout for the workspace, with a My sites link and account menu.
- Add, select, edit, remove, and reorder the three text-based block types. Show each type distinctly in the preview. Keep the interface usable on narrow screens and by keyboard.
- Save block content and page order in MySQL as private editable site data. Show pending, saved, validation, and unexpected-failure feedback without discarding typed edits.
- Render user text as text, preserve intentional line breaks, and use the existing workspace styling and light/dark behavior.

## Out of scope

- Hero buttons and section links, service times, and contact details (2b).
- Image, text-and-image, and video blocks (2c); image uploads and IONOS storage (Feature 4).
- Themes, header/footer editing, public preview, publishing, SEO, billing, domains, and multiple pages (later features).
- Rich-text formatting, HTML input, block templates, collaboration, and version history.

## Build loop

- Implement the steps in order. Guided review requires approval after each step before continuing.
- After an approved, passing step, offer the configured optional checkpoint commit. Complete creates the final work commit.
- Keep each step working and verify its new behavior with focused tests and available frontend checks. Review the running editor at desktop and narrow widths before completion; do not claim browser evidence until observed.

## Build steps

- [x] **1. Persist ordered, owned blocks.** Add a site_blocks table, SiteBlock model and factory, and a Site::blocks() relationship. Store the owning site_id, stable block ID, type, zero-based position, JSON content, and normal timestamps. Cascade when a site is deleted. Support only about, plain_text, and heading_text in this build. **Done when:** migration and focused database tests pass for multiple ordered blocks on one site, isolation between two sites, JSON round trips, and cascade deletion; the existing site tests still pass.
- [x] **2. Add blocks and show the editor.** Scope block reads and creates through the signed-in user's site. A new block gets empty fields and appends after the last block. Replace the blank view with a responsive block list and page preview, an add control for the three types, selection, and a field panel shell; preserve site rename access. **Done when:** an owner can add each type, reload to see the same order and a selected editable block, and see a useful empty state before adding; a guest follows login and another owner gets 404; focused HTTP tests, TypeScript, and build pass.
- [x] **3. Edit content in the side panel.** Provide labeled fields and an explicit Save action. About and heading-and-text have heading and body; plain text has body. Preview current field input while editing and distinguish unsaved changes from saved content. Validate the type-specific shape on the server and save only the selected block under the owned site. Protect unsaved text when switching blocks or leaving the editor. **Done when:** edits save and survive reload, invalid fields show associated errors and retain input, unknown fields/types are rejected, another site's block ID returns 404, and handled HTTP/network failures show neutral feedback without an Inertia raw-error dialog; focused HTTP tests and a responsive manual walkthrough pass.
- [x] **4. Remove and freely reorder blocks.** Add a confirmed Remove action and a persist-order action. Offer pointer reordering where usable plus visible keyboard-operated move controls. Re-sequence positions after removal and require a complete permutation of the site's current block IDs for reorder. **Done when:** moving any block to any position and removing one persist across reload, an empty result restores the empty state, invalid/foreign/stale order payloads leave existing order untouched, ownership checks return 404 for cross-owner direct actions, and desktop/narrow keyboard and pointer walkthroughs plus focused tests and build pass.
- [x] **5. Repair independent-review findings.** Reject keyed or sparse reorder arrays before writes, prevent drafts entered while add or remove is pending from being silently replaced, and format the editor with the configured formatter. **Done when:** focused HTTP and delayed-response UI checks prove the failures are fixed, formatting passes for the editor, and the feature checks still pass.

## Files / areas

- database/migrations/, app/Models/SiteBlock.php, its factory, and app/Models/Site.php for block persistence and ordering.
- app/Http/Controllers/ and app/Http/Requests/ for owned block actions and validation; routes/web.php for protected routes alongside the existing site routes.
- resources/js/pages/Sites/Show.vue and small reusable components under resources/js/components/ only where they keep the editor manageable; resources/css/app.css for existing workspace tokens and preview styling.
- tests/Feature/ for database, HTTP, validation, ownership, ordering, and deletion behavior.

## Data / contracts

- site_blocks.id is the generated integer identity. site_id is a required foreign key to sites.id with cascade delete. type is one of about, plain_text, or heading_text in 2a. position is a zero-based nonnegative integer; blocks are returned by position, then ID, and successful mutations keep positions contiguous within their site.
- content is a JSON object with exactly the allowed string fields: heading and body for about and heading_text, body for plain_text. A new block starts with empty strings. Empty text remains valid while a draft is being assembled. Reject unknown content keys and non-string values. No HTML or rich-text markup is interpreted; the Vue preview escapes text and preserves line breaks. Do not store editor-only placeholders as content.
- All block routes use the existing auth and verified middleware. Resolve the site from the signed-in user's sites relationship first, then resolve a block from that site's relationship. Never accept a client-supplied site_id or position in a content update. Foreign and missing site/block IDs return 404 without exposing ownership. Validate type-specific input with Laravel; malformed content cannot change saved data.
- Append, remove, and reorder update positions atomically for one site. Reorder accepts exactly the current set of that site's block IDs once each, including an empty set, and rejects duplicates, omissions, foreign IDs, and stale lists without a partial write. Use a site-scoped transaction and lock for order mutations so two requests cannot leave duplicate or skipped positions. An invalid order returns a field error the editor can display and prompts a refresh of the block list.
- The side panel uses an explicit Save button for text edits. Typed changes appear in the editor preview before save but are marked unsaved. Switching selection or leaving with dirty fields requires an explicit choice so edits are not silently lost. Add, save, remove, and reorder disable duplicate submissions while pending and show a neutral retry message for unexpected failures. Validation messages associate with labeled fields, announce errors, focus the first invalid field, and clear when corrected or after success.
- This feature stores editable site content only. Feature 5 will define a published snapshot and visitor-facing route; no block content becomes public in 2a.

## Testing

- Add focused Pest feature tests through real database and HTTP routes for content shape, owner isolation, guests, empty values, multiple sites, add/edit/remove/reorder, invalid input, atomic order rejection, and cascade deletion. Use the existing composer test suite.
- Run npm run types:check and npm run build after UI steps. No browser test command is configured. Manually inspect the running editor at desktop and narrow widths, including empty and saved states, selection, keyboard controls, focus, dark mode, errors, and the existing rename path.
- No live editor or browser result has been observed during specification.
- The user confirmed the live editor's save, block switching, responsive layout, top navigation, pointer and keyboard ordering, and removal through the empty state.

## Notes for the AI

- Reuse the current SiteController ownership pattern, Inertia forms, site route, Laravel validation, and existing workspace tokens. Do not add a drag-and-drop dependency for this scope; simple pointer behavior and accessible move controls are sufficient.
- Keep block type names and persisted content shapes stable for 2b/2c and later Blade publishing. Add only the three current types now; later features extend the allowlist and preview.
- The overview still summarizes parent Feature 2 as one outcome. The approved lettered plan items refine build order without changing that product goal.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":9485,"specSha256":"4a8548c95307fe304e662f046108e3ef00a09242d955c18b378998aa06cb7af5","branch":"refs/heads/feature/editor-foundation","head":"f4b8727b4a1dc33cc781e963feb0d14a071ec127","baseRef":"refs/heads/main","baseCommit":"b18e4644eac7aa50532202f07ed261a3dca3d47a","sourceTree":"e56bf11ee89b79a6e4261adda73f6ed164c49033","absentOptional":[]} -->

## Findings

### 2a/F-03 [P1] closed - Reject associative reorder payloads before persisting positions

**File:** app/Http/Controllers/SiteBlockController.php:77
**Found:** 2026-09-23 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** OrderSiteBlocksRequest validates `order` as an array but does not require a list. A request with the correct `expected_order` and `order` keyed by 10 and 20 passes both validation and the membership check. The controller then persists those client-controlled keys as positions. An isolated SQLite reproduction through the real Form Request and controller saved positions `[10, 20]` for a two-block site. This violates the verified spec's zero-based contiguous-position and invalid-input contracts and breaks the editor's position-based numbering and move-button state. Existing tests only supply dense lists.
**Suggested fix:** Require Laravel's `list` rule on the order arrays so associative and sparse input receives validation errors before any write. Keep positions derived from a validated dense list; add a focused HTTP regression proving malformed keys leave existing positions unchanged.
**Resolution:** Re-reviewed at f4b8727b4a1dc33cc781e963feb0d14a071ec127. Both order arrays now require Laravel list validation before the transaction. The full passing Pest suite includes keyed desired/expected-order regressions that assert unchanged IDs and contiguous positions. The original defect is closed.

### 2a/F-04 [P1] closed - Protect edits entered while adding a block

**File:** resources/js/pages/Sites/Show.vue:230
**Found:** 2026-09-23 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** With an existing block selected, Add begins a request but leaves the heading/body fields editable because they are disabled only by `saveForm.processing`. Text entered while Add is pending becomes dirty. The Add success callback unconditionally selects the last block, and the selection watcher replaces the draft without another discard choice. Executing the actual component script with Vue reactivity and a controlled form callback changed the draft from `Typed during the pending Add request` with dirty=true to an empty draft with dirty=false. The original block remains saved without that text. This is a reachable silent-loss path on a slow connection and violates the spec's protection of typed edits. Pending removal can similarly replace a newly edited draft.
**Suggested fix:** Disable block editing during pending add/remove operations that will switch selection, or retain the dirty draft and require an explicit choice before the success callback changes selection. Apply the same pending-state protection consistently to controls that can launch competing mutations. Verify with a delayed Add/Remove response while attempting to edit.
**Resolution:** Re-reviewed at f4b8727b4a1dc33cc781e963feb0d14a071ec127. Both content inputs are disabled while Add, Remove, Save, or Reorder is pending; block selection and competing block mutation handlers also guard pending operations. An in-memory check of the actual Vue template disabled expressions confirmed both fields are disabled for each pending mutation and enabled while idle. The original pending Add/Remove draft-loss path is closed; this pass did not perform a live delayed-response browser walkthrough.

### 2a/F-05 [P2] closed - Format the editor with the configured project formatter

**File:** resources/js/pages/Sites/Show.vue:1
**Found:** 2026-09-23 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The rewritten editor uses formatting that fails the configured Vite Plus check. `node_modules/.bin/vp fmt --check resources/js/pages/Sites/Show.vue` exits 1. `composer ci:check` also stops at frontend formatting, so the declared combined local gate is currently red. Its full output reports 89 files, including existing workflow and generated files; this finding concerns the changed product component, not an instruction to reformat unrelated files.
**Suggested fix:** Format the changed editor using the existing project formatter and rerun the focused check. Report any remaining baseline failures separately when rerunning the combined check.
**Resolution:** Re-reviewed at f4b8727b4a1dc33cc781e963feb0d14a071ec127. The focused vp fmt --check for Sites/Show.vue passes, as does focused lint for the editor, header, and layout. The editor formatting defect is closed. npm run check still fails on 88 workflow, configuration, and generated files; that broader formatting failure remains disclosed in the receipt.

## Independent review

# Independent Review

**Status:** passed
**Target commit:** f4b8727b4a1dc33cc781e963feb0d14a071ec127
**Base commit:** b18e4644eac7aa50532202f07ed261a3dca3d47a
**Base ref:** main
**Spec hash:** 4a8548c95307fe304e662f046108e3ef00a09242d955c18b378998aa06cb7af5
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-23T18:46:46.783Z
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-23T18:49:38.831825Z
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Commands

- `git status --short`, `git rev-parse HEAD`, `git merge-base main HEAD`, and SHA-256 of the exact spec bytes: pass; only the two permitted evidence paths differ from the checkpoint.
- `git diff main..HEAD` with targeted source, test, configuration, and caller reads: reviewed the complete feature delta across all four lenses.
- `composer test`: unavailable as a combined command; its parallel Pint stage cannot bind a local TCP socket in this sandbox (EPERM).
- `vendor/bin/pint --test`: pass using the serial formatter check.
- `vendor/bin/phpstan analyse --no-progress --debug`: pass, zero errors.
- `php artisan test`: pass, 76 tests and 371 assertions.
- `npm run types:check`: pass.
- `node_modules/.bin/vp fmt --check resources/js/pages/Sites/Show.vue`: pass.
- `node_modules/.bin/vp lint resources/js/pages/Sites/Show.vue resources/js/components/AppHeader.vue resources/js/layouts/AppLayout.vue`: pass.
- `npm run check`: fail; formatting issues in 88 workflow, configuration, and generated files. The repaired editor is absent from this failure list.
- In-memory Node/Vue SFC compilation and evaluation of the actual disabled bindings: pass; both fields disable during each pending block mutation and enable while idle.
- `npm run build`: not run; it regenerates tracked output and Wayfinder files outside Phase B's permitted evidence writes.

## Evidence

- Fresh isolated Codex reviewer with explicitly selected gpt-6-astra and no builder transcript. Target, main merge base, tracked spec hash, verified status, and allowed working-tree differences were checked before review and again before writing this receipt. No snapshot field exists or is needed for the tracked spec.
- Reviewed the full `b18e4644eac7aa50532202f07ed261a3dca3d47a..f4b8727b4a1dc33cc781e963feb0d14a071ec127` source delta: block controllers and requests, site/block models, migration and factory, route and middleware changes, editor, top navigation/layout, planning changes, and all three block test files. Followed the existing SiteController and header-layout callers and inspected generated route contracts. Generated/minified build assets and third-party dependencies were excluded as code-review subjects under the Audit scope rules.
- Ownership is resolved through the authenticated user's site and then its blocks; protected routes retain auth/verified middleware. Content is validated by persisted type, rendered through escaped Vue interpolation, and remains private. Append/remove/reorder use the owning site row lock and one transaction; stale and invalid complete-order requests are rejected before writes.
- Pest exercises all three content shapes, empty values, malformed and extra content, owner/guest/foreign isolation, multiple sites, reorder/removal, invalid/foreign/stale/keyed orders, JSON persistence, and cascade deletion. No skipped, focused, or placeholder tests were found in the new block suites.
- Reviewed explicit saves, input retention on handled errors, draft-discard guards, selection transitions, disabled pending controls, pointer and keyboard order controls, and responsive/dark style usage against the spec and local coding standards. The template probe is source-level evidence, not live browser evidence.

## Findings

- F-03 [P1] closed: keyed order arrays are rejected and the HTTP regressions pass.
- F-04 [P1] closed: pending Add/Remove can no longer accept field edits that their success callbacks would discard.
- F-05 [P2] closed: the changed editor passes the configured formatter.
- F-02 [P2] remains open: the carried workspace muted-text contrast concern has not been repaired or accepted.
- No new confirmed findings across quality, security, performance, or tests. No open or fixed P0/P1 findings remain.

## Remaining risk

- `composer test` cannot complete its parallel Pint stage in this sandbox because local TCP binding is denied. Its constituent checks passed through serial Pint, debug-mode PHPStan, and the complete Pest suite.
- `npm run check` remains red on 88 workflow, configuration, and generated files. This receipt does not waive that combined frontend gate or authorize completion through it.
- `npm run build` was not rerun because its tracked generated-file writes exceed the reviewer's permitted scope. TypeScript, focused lint/format, and in-memory editor compilation passed; a fresh production bundle was not independently verified here.
- No live desktop/narrow browser, delayed-response, or history-navigation walkthrough was performed in this reviewer context. The active spec records prior user confirmation; no browser test command is configured. Check is not required by this request.
- SQLite test coverage does not exercise concurrent MySQL lock scheduling. The site-row locking strategy was reviewed statically; no production-scale or concurrency benchmark was performed.
- F-02 remains an open nonblocking accessibility finding. No dependency vulnerability scan was run.
