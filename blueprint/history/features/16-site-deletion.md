# Feature: Site deletion

**From build-plan:** feature 16
**Build attempt:** 1
**Branch:** feature/site-deletion
**Archive:** blueprint/history/features/16-site-deletion.md
**Status:** verified

## Goal

Let an owner permanently delete one site from its settings after confirming its name. Take the site offline immediately, cancel renewal using the existing billing policy, and finish external cleanup safely without affecting the owner's account or other sites.

## In scope

- An owner-only Delete site action and name-confirmation dialog in site settings.
- Immediate, committed revocation of public subdirectory pages, custom-domain pages, media access, editing, publishing, uploads, new checkout, and domain connection for the deleted site.
- Removal of site content and eventual removal of its uploads, custom hostname, and local billing records once external obligations are reconciled.
- A durable pending-deletion state, a first cleanup attempt, and automatic retries using the existing Laravel scheduler.
- Clear owner feedback distinguishing an offline site awaiting cleanup from completed deletion.
- Safe integration with existing account deletion, Stripe webhooks, in-flight checkout, upload, and domain reconciliation.

## Out of scope

- Undo, restore, trash retention, bulk deletion, admin deletion of other owners' sites, and changes to other sites.
- Refunds, new billing prices, immediate termination of a paid subscription, or deletion of Stripe's customer/invoice history. Existing cancellation stops renewal at period end.
- Provider/DNS account changes, deleting user-owned DNS records, actual deletion of live sites during development, deployment, and unrelated findings.

## Build loop

- Follow `workflow.stepReview: every`: implement and test one step, then pause for review. Optional checkpoint commits require approval.
- Use `feature/site-deletion` from current main in the isolated checkout at `/Users/timjossund/.codex/worktrees/complete-shared-block-styling/churchsiteapp`. The original Herd checkout contains preserved local work and an obsolete Feature 11 spec; it is not the active implementation checkout.
- Keep generated assets, routes, and dependency changes separate from source commits. Preserve unrelated local files.
- Run focused Pest checks per step and `composer ci:check` as the final automated gate.
- Independent review is required by the configured `when-sensitive` policy because this feature changes destructive operations, billing, and access revocation. Use the configured automatic isolated reviewer after final verification and explicit checkpoint approval. Audit is satisfied by that review. Live Check and try guide remain manual.
- `/complete` owns the final commit, archival, and approved merge. No remote service mutations are part of implementation or testing.

## Build steps

- [x] 1. Establish pending deletion and enforce immediate access revocation.
      Add the nullable site deletion timestamp and the internal operation that records it under owner/site locks, revokes publication/domain readiness, and removes draft page/block content. Guard public and authenticated paths and internal billing/domain work against pending deletion. Do not expose the destructive UI until cleanup is implemented. Extend dashboard data to distinguish pending sites.
      **Done when:** focused tests prove the requested site stops serving pages/media and cannot be edited, published, uploaded to, or newly billed/provisioned; other owners and other sites remain unaffected; requests already validated cannot write through the deletion boundary after acquiring their mutation lock. Existing active-site flows remain green.

- [x] 2. Implement billing, domain, and file finalization with safe retries.
      Reuse the existing account-deletion billing reconciliation contract, extracting its site-scoped cancellation routine only as needed for both callers. Reuse domain removal. Add a site finalizer and scheduled command; preserve identifiers until each cleanup is confirmed. Integrate pending site deletion into account deletion so cascading account removal cannot orphan cleanup.
      **Done when:** faked-provider tests cover unpaid sites, paid sites, unresolved checkout/payment, canceled renewals, lost responses, duplicate/reordered webhooks, domain cleanup failure, storage failure, repeated finalization, and simultaneous account deletion. Failed or uncertain cleanup keeps the site offline and retains retry information. Finalization removes only the target site's rows and files after every prerequisite is met.

- [x] 3. Add the owner-facing deletion request and confirmation UI.
      Add the authenticated DELETE route, request validation, action/controller entry, and settings dialog. Show pending cleanup on the dashboard without an Open site action. Use existing dialog/form components, error feedback, and focus behavior. Attempt finalization after the offline transaction commits; external failure never reverses the accepted deletion request.
      **Done when:** an owner can confirm a site name and return to the dashboard; wrong names and foreign IDs cannot delete anything; free sites finish when cleanup succeeds; paid or failed-cleanup sites show accurate pending status and cannot reopen. Focused endpoint tests pass and user-facing feedback distinguishes request acceptance from confirmed renewal cancellation.

- [x] 4. Complete regression, accessibility, and independent verification.
      Exercise the full lifecycle, including pending deletions across a paid-period boundary, a webhook after deletion request, another site remaining published, scheduler retries, and account deletion. Review mutation/public-route coverage against routes and callers. Check the dialog and pending card manually or with available browser tools, then run final verification and independent review.
      **Done when:** `composer ci:check` passes; no blocker remains in the independent review receipt; observed UI evidence and any unavailable browser scenarios are documented accurately. Existing account-deletion, billing, domain, media, page, and publishing tests remain green.

## Files / areas

- New migration adding `sites.deletion_requested_at`; `app/Models/Site.php` for its immutable timestamp cast and explicit active/deleting checks.
- A focused site-deletion action/finalizer, request and controller under existing `app/Actions`, `app/Http/Requests`, and `app/Http/Controllers` conventions; `routes/web.php` for `sites.destroy`.
- `app/Http/Controllers/SiteController.php`, `resources/js/pages/Sites/Settings.vue`, and `resources/js/pages/Dashboard.vue`: settings action, dialog, and pending status.
- Existing site/page/block/media/publishing request and controller paths, `PreserveEditorPage`, and any site-route guard needed to enforce the same boundary consistently. No global model scope that hides retained sites from cleanup or webhooks.
- `PublishedSiteController.php`, `DomainProxyController.php`, `Site::hasPaidDomainAccess()`, and domain readiness/reconciliation: fail closed for pending sites.
- `DeleteAccountWhenBillingEnds.php`, `CancelSiteRenewal.php`, `StartSiteCheckout.php`, `OpenSiteBillingPortal.php`, `SiteBillingWebhookController.php`, `ReserveCustomHostname.php`, `DisconnectCustomHostname.php`, `ReconcileCustomHostname.php`, and `routes/console.php`: relevant lifecycle integration only.
- `SiteMediaController.php`, `MediaAsset`, and existing private S3 storage: inventory-based deletion and upload serialization.
- New `tests/Feature/SiteDeletionTest.php` plus targeted extensions to `DeferredAccountDeletionTest`, billing, domain, media, workspace, and published-site tests.

## Data / contracts

### Request and owner experience

- `DELETE /sites/{site}`, named `sites.destroy`, within existing auth/verified middleware; use the authenticated user's site relationship, never a submitted owner ID.
- Payload: `name`, required string, compared case-sensitively to the site's current name after the normal request trimming rules. Compare the trimmed stored name too so pre-existing surrounding whitespace cannot make confirmation impossible. Recheck under the site lock to handle concurrent rename.
- Invalid confirmation returns the existing Laravel validation response with a `name` error; foreign/mismatched or absent site returns 404; guests follow existing login behavior. Preserve CSRF protection.
- First accepted request records deletion atomically. A repeat while the owned pending row exists resumes/acknowledges the same operation without creating another. After final removal, return normal 404 without revealing past ownership.
- Redirect accepted requests to the dashboard. Display confirmed completion only if finalization finished. Otherwise say the site is offline and billing/file cleanup is pending; do not claim renewal has stopped before Stripe confirms it.
- Use a clearly labeled destructive section and a dialog explaining permanent content/file loss, immediate loss of both public addresses, and cancellation of future renewal. No additional password requirement beyond the established authenticated-owner boundary.
- Disable submit while processing, retain dialog/input on validation or network failure, associate and announce errors, focus the relevant input, clear errors when corrected, and support Escape/cancel/focus return. Do not optimistically remove a site before server acceptance.
- Active sites keep existing cards. Retained pending sites show their name and read-only deletion status without edit/open/publish actions. A finished site disappears, using the existing empty-dashboard state when appropriate. Do not expose customer IDs, provider errors, or file keys.

### Persisted lifecycle and public access

- Add nullable timestamp `sites.deletion_requested_at`, default null, cast `immutable_datetime`; existing sites stay active with no backfill. Existing related rows are the cleanup inventory, not a new generic job ledger.
- Deletion request is permanent. In a transaction, lock User then owned Site in the existing billing lock order; recheck name/state; set the timestamp once, clear `published_at` and `published_snapshot`, delete its pages/blocks, and mark any custom hostname `removing` with readiness revoked. Keep site identity/name/slug and billing/domain/media rows needed for cleanup.
- Site deletion is distinct from account deletion: it never marks the user for deletion or cancels another site's subscriptions.
- All new public page/media requests after commitment fail through existing unavailable/404 behavior, including direct published-render helpers and custom-domain routing. Existing response bytes or files already downloaded cannot be recalled.
- Deny site editing, page/block operations, media reads/writes, publishing, checkout, portal creation, and domain reconnection while pending. Internal cleanup and signed webhook processing must still find the retained site.
- Guard inside each relevant mutation transaction as well as request entry. Serialize uploads with the site lock from before the remote upload through its media inventory/assignment, so deletion cannot finalize ahead of an upload already in progress. Preserve upload failure compensation; test late requests and failure paths without deleting another site's files.
- Domain workers must not apply stale readiness or create responses to reactivate a deleting site. Webhooks may reconcile billing but never clear deletion state or restore routing. Keep the existing signature verification and customer matching.

### Billing and external cleanup

- Commit the offline state before remote work. Invoke a first finalization attempt after commit and retry pending sites with `sites:finalize-deletions` scheduled every five minutes using existing `withoutOverlapping` conventions. The command also supports a manual operator run; no user-provided raw provider IDs.
- Reuse the account-deletion reconciliation behavior: expire open checkout sessions, reconcile uncertain customer creation using existing idempotency/age guards, enumerate that site's remote subscriptions, confirm customer identity, and set `cancel_at_period_end` where needed. An unconfirmed checkout/payment/cancellation remains pending; never create replacement sessions or silently acknowledge a failed cancellation.
- Keep the site and local subscription mapping until remote commitments are terminal and any locally confirmed paid-through time has ended, following the existing reconciliation rules. The website remains offline during this retention. No refund or immediate cancellation API is introduced.
- Domain cleanup reuses the recorded zone/hostname/Cloudflare ID and existing removal checks. Do not drop its row on a provider failure or uncertain provisioning response. Allow local routing revocation even when the provider is disabled; uncertain external removal stays pending for operator reconciliation.
- Remove uploaded objects using the target site's MediaAsset inventory, including images no longer referenced by current content. Accept only keys under `sites/{id}/`; do not recursively delete arbitrary paths or scan other tenants. Successful deletion or confirmed absence permits removing that media row; false/exception/uncertain outcome retains its key for retry. Files can be removed while billing waits.
- Do not cascade away retry identities before remote cleanup succeeds. Hard-delete the site only under a final owner/site lock after billing is safely finished, domain row is gone, and media cleanup is complete. Database cascades remove remaining local child records.
- Repeated commands and overlapping webhook/account/site deletion requests must converge without duplicate subscriptions, restored access, orphaned provider IDs, or cross-site effects. Reuse site locks and existing remote idempotency rules rather than adding a general workflow engine.
- Account deletion must finalize or retain pending site cleanup before cascading the user. Preserve existing account deletion behavior for unaffected active sites.
- Catch only expected provider/storage failures, log safe operational context, and retain pending state. Unexpected failures remain visible to the normal error/logging path while the persisted offline state survives.

## Testing

- Step 4: `composer ci:check` passed (formatting/lint, Vue typecheck, PHP analysis, production build, and 645 Pest tests / 5,077 assertions). Lifecycle review covered paid-period retention, delayed/duplicate/reordered signed webhook events, another site remaining available, scheduler retries, and account deletion. The route inventory and focused route tests cover owner-only mutation paths. Browser appearance, keyboard, and screen-reader interaction were unavailable: no browser automation tool is configured, and the Implement workflow prohibits starting a server. The focused feature and endpoint test suite plus frontend checks passed. No P0/P1 findings were present in the existing ledger before independent review.
- Step 3: owner-only DELETE route, trimmed exact-name confirmation, post-commit finalization, settings dialog, and dashboard pending/completed feedback implemented. Focused deletion tests passed (44 tests, 306 assertions); full Pest suite passed (645 tests, 5,077 assertions). Frontend formatting/lint, Vue typecheck, production build, PHP static analysis, Pint, and diff whitespace checks passed. Browser appearance/keyboard evidence remains for Step 4; no live-site deletion or local user-database migration was performed.

- Step 2: focused deletion, cleanup, billing, and account-deletion tests passed (80 tests, 491 assertions). Full `php artisan test --compact` passed (634 tests, 4,989 assertions); `composer types:check`, Pint, and `git diff --check` passed. Coverage includes missing files, storage false/exception/uncertain existence, invalid storage keys, paid-period retention, lost checkout and cancellation responses, recovered customer identity, domain failure/unknown creation, duplicate/reordered signed webhooks, cleanup-time billing updates, repeated finalization, and account cascade prevention. All provider/storage mutations used fakes; no live cleanup ran.

- Step 1: `php artisan test --compact` passed (612 tests, 4,856 assertions); `composer types:check` passed; Pint and `git diff --check` passed. The focused deletion/workspace/upload/billing/domain/publication run passed 86 tests before the broader regression run.
- Step 1 creates the migration but has only applied it to the isolated SQLite test database. No user's site, subscription, domain, or uploaded object was deleted. The deletion action is internal until Step 3 exposes the route and UI.

- Planning baseline: `composer ci:check` passed with 598 tests and 4,750 assertions, including frontend lint/format/typecheck, PHP formatting/static analysis, and production build.
- Test authenticated owner, unauthenticated and foreign users, wrong/empty name, concurrent rename, repeat request, absent ID, and unrelated site/account preservation.
- Test published home/secondary pages, public/private media and custom domains becoming unavailable immediately; block stale mutations and signed billing/domain events from restoring access.
- Test cleanup with no billing/domain/assets, all three resources present, mixed success/failure, uncertain checkout/provisioning, storage false/exception/missing file, invalid foreign storage prefix, and expiry of retained paid time using the existing controllable clock/test fakes.
- Prove scheduler/manual finalizer retries and account deletion cannot remove cleanup identities prematurely. Add a focused upload/deletion interleaving regression at the existing transaction seam.
- Use Stripe mocks, fake Cloudflare/DNS boundaries, and `Storage::fake('s3')`. Never cancel a real subscription, remove a real hostname, or delete a user's real uploads for verification.
- Browser tests are not configured. Reuse existing native controls and dialog components; document actual keyboard, loading, error, and empty-state evidence without claiming it from PHP/build output.

## Notes for the AI

- The user approved adding Feature 16 before Feature 12 and chose immediate offline deletion with renewal cancellation and retained cleanup records.
- Critique separated durable access revocation from provider success, preserved identities for retries, included checkout/upload/webhook races, and kept existing account deletion from bypassing site cleanup.
- Exact archive path is absent, no prior feature 16 archive was found, no available Git history uses that archive path, and `feature/site-deletion` is valid and unused. Freeze build attempt 1.
- Existing planning drift about completed Worker features versus pending transport proof and final deployment remains outside this work. It does not authorize deployment or live-provider tests.
- Keep this one coherent deletion feature. The sole justified shared extraction is the existing site-scoped billing cancellation/reconciliation code needed by both account and site deletion.
- This packet is for review. Do not implement, migrate the user's database, commit, merge, or push from the Feature skill.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":19175,"specSha256":"9abea7c06f6800d5316ec5b5c3ebe76790c89540a3779511739d4a3845afe36c","branch":"refs/heads/feature/site-deletion","head":"c6ef0aba71b6b1cd6b809071950f7d99714f79aa","baseRef":"refs/heads/main","baseCommit":"65bc31d6b50175d03c53ba711bfa72d834b508dc","sourceTree":"39949a53a709968d4965ed635fcce027f45f4d07","absentOptional":[]} -->

## Findings

No findings were resolved by this feature. F-02 and F-06 remain open in the live ledger.

## Independent review

**Status:** passed
**Target commit:** c6ef0aba71b6b1cd6b809071950f7d99714f79aa
**Base commit:** 65bc31d6b50175d03c53ba711bfa72d834b508dc
**Base ref:** main
**Spec hash:** 9abea7c06f6800d5316ec5b5c3ebe76790c89540a3779511739d4a3845afe36c
**Prepared by:** codex
**Builder model:** unknown (runtime did not expose exact model)
**Requested reviewer:** codex
**Requested model:** gpt-6-astra
**Requested execution:** automatic
**Requested at:** 2026-09-28T15:43:15Z
**Workflow:** regular
**Check required:** no

**Reviewer adapter:** codex
**Reviewer model:** gpt-6-astra
**Reviewer context:** fresh subagent
**Actual execution:** automatic
**Reviewed at:** 2026-09-28T15:49:31Z
**Scope:** current
**Lenses:** quality, security, performance, tests
**Verdict:** passed
**Check result:** not-required

## Commands

- `git status --short`, `git rev-parse HEAD`, `git merge-base main HEAD`, and `shasum -a 256 blueprint/context/current-feature.md`: passed before review and immediately before this receipt; target, base, tracked verified spec, and evidence-only differences match the request.
- `git diff --check 65bc31d6..HEAD`: passed.
- `php artisan test --compact --filter='SiteDeletion|SiteBillingLifecycle|DeferredAccountDeletion|CustomerDomainTransport|CustomerDomainProvisioning|SiteImageUpload'`: initial sandbox attempt unavailable for test storage writes; permitted rerun reached 110 tests, with 101 passing and nine failures/errors caused by missing generated Vite entries. Superseded by the passing full suite below.
- `php artisan test --compact --filter='SiteDeletionCleanup|DeferredAccountDeletion|SiteBillingLifecycle'`: 64 of 67 passed before rebuilding; three rendering failures referenced missing Vite entries. Superseded by the passing full suite below.
- `composer ci:check`: initial attempt passed frontend lint/format but stopped at missing generated Wayfinder types. After the build below, rerun passed frontend lint/format/typecheck, PHP format/static analysis, production build, and all 645 Pest tests with 5,077 assertions.
- `npm run build`: passed, regenerating the checkpoint's frontend assets and Wayfinder output before the successful combined check.
- Source-token luminance calculation for F-02: 4.16:1 on white, 3.86:1 on the workspace background, and 3.97:1 on the soft surface.

## Evidence

- Reviewed the complete 32-file base-to-target delta across all four lenses, including the active verified spec, planning updates, migration, deletion request/finalizer, shared billing cancellation extraction, account-deletion integration, webhook changes, active-site guards, public/media routes, upload transaction boundary, scheduler, Vue dialog/dashboard integration, and every added or changed test.
- Followed relevant existing billing, domain provisioning/removal, public rendering, model/cascade, dialog, navigation-guard, and media-compensation contracts. Checked owner scoping, exact confirmation, durable offline commit, retained provider/storage identities, retry convergence, paid-period retention, and cross-site isolation against the project standards.
- Full automated verification independently passed with fake Stripe/Cloudflare/storage boundaries and isolated SQLite tests. Logs: `/private/tmp/site-deletion-independent-build.log` and `/private/tmp/site-deletion-independent-ci.log`. No live provider cleanup or application database migration was performed.
- Existing generated build and Wayfinder output was restored by the builder after verification; reviewer rechecked that only the review evidence differed from the target before writing this receipt. Dependencies, generated files, caches, and compiled assets were excluded from application-code review.
- No skipped, focused, or placeholder tests were found in the changed test set. Reviewed adversarial validation, offline route denial, stale mutations, provider/storage failures, duplicate/reordered webhooks, and account-cascade prevention.
- The runtime spawned this generic child with exact model `gpt-6-astra` and `fork_turns: none`, without the builder transcript; the parent confirmed those spawn parameters. Phase B changed only the findings ledger and this receipt.

## Findings

- No new findings and no open/fixed P0 or P1 findings.
- F-02 remains open P2: the unchanged muted-text token is used by the new deletion copy and pending dashboard state; independent contrast calculation reconfirmed the existing issue. Its Resolution was updated; it was not accepted or closed.
- F-06 remains unchanged and outside this feature's repair scope.

## Remaining risk

- Browser appearance, keyboard/focus behavior, and screen-reader announcements were not observed. No browser harness is configured; source inspection and PHP/build checks do not prove these interactions. Live Check was not required by this request.
- The SQLite tests and simulated interleavings do not provide live MySQL lock-contention evidence for simultaneous upload, deletion, webhook, and cleanup workers.
- The initial storage-permission and generated-asset verification failures were resolved for the successful full check. Repeating checks from the restored checkout requires regenerating frontend/Wayfinder output first; no command remains unavailable for that reason.
