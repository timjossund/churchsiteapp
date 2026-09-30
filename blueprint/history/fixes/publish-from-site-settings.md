# Fix: Publish directly from site settings

**Type:** Fix
**Status:** verified
**Branch:** fix/publish-from-site-settings

## The problem

Owners can save shared site settings and upload logos or favicons, but must open
an individual page editor to publish those changes. Site settings offer no
publish action, making the saved-draft versus public distinction hard to follow.

## The fix

- Add a prominent Publish site button near the existing settings header actions,
  preserving Live settings / Go Live and See live site.
- Show whether the site is unpublished, has saved unpublished changes, or is
  up to date, using the existing publication timestamp and fingerprint status.
- Reuse the existing whole-site publish endpoint and Wayfinder helper. Publish
  all saved site and page changes and remain on site settings afterward.
  Explain that publication includes all saved pages and shared settings.
- Keep Save and Publish separate. If settings or page names have unsaved edits,
  explain that they must be saved before publishing; do not discard or silently
  publish without those edits. Logo and favicon uploads already save as drafts.
- Prevent overlapping publishing, saves, uploads, page operations, and deletion
  using the existing busy guards. Show Publishing while the request runs and
  accessible success or error feedback afterward.
- Explain when the owner needs to choose and save a shareable address first.
  Preserve the server's existing validation and atomic publication behavior.
- Keep page-editor publishing, ownership checks, live-domain links, and draft
  versus published content behavior unchanged. No new publishing endpoint,
  automatic publishing, or save-all workflow is needed.

## Build steps

- [x] Add publishing controls, status, and feedback to Site Settings using the
      existing page-editor and settings patterns. Add focused regression coverage
      for publishing saved shared settings from the settings workflow.
      **Done when:** an owner can save shared settings or upload a logo/favicon,
      publish from settings, stay on settings, and see the updated publication;
      unsaved changes and concurrent operations are guarded; missing-address and
      publication validation errors are clear; existing editor publishing works.

## Verify

- Use existing Pest publishing/media tests to cover settings-origin publishing,
  redirect back to settings, frozen content before Publish, updated shared
  content after Publish, and atomic failures. Reuse existing ownership coverage.
- Run `composer ci:check` for frontend lint/format and typecheck, PHP formatting
  and static analysis, production build, and the full Pest suite.
- In Settings, save a shared setting or upload a logo, confirm unpublished
  status, then Publish site and confirm success without opening a page editor.
  Check the visitor page for the updated content.
- Try publishing with unsaved settings, a missing shareable address, and an
  active upload/save. Confirm explanatory feedback and no discarded edits or
  overlapping requests. Check keyboard focus and narrow-screen controls.

## Implementation evidence

- `php artisan test --compact tests/Feature/SitePublishingTest.php tests/Feature/SiteFaviconTest.php tests/Feature/MultiPagePublishingTest.php`: 59 tests passed.
- `composer ci:check`: passed frontend lint/format, Vue typecheck, Pint,
  PHPStan, production build, and all 975 Pest tests.
- Settings-origin requests prove publication returns to Settings, shared footer,
  logo and favicon changes remain frozen before Publish, and update afterward.
- Publishing uses the existing authenticated endpoint without changing server
  ownership, snapshot validation, or publication rules. Unexpected HTTP errors
  retain Inertia's error path; validation and network failures have local feedback.
- Browser verification is unavailable in this session. Manual checks remain for
  button layout, keyboard focus, unsaved-input feedback, and concurrent-operation
  guards. No browser behavior is claimed from the production build.
- Regular Audit, Check, and try guide are manual. Independent review is not
  selected for this settings UI change; server security and persistence logic
  are unchanged. No independent-review request exists.
- Existing open P2 findings F-02 (muted text contrast) and F-06 (ownership DNS
  name length) remain outside scope. There are no P0/P1 blockers.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":4416,"specSha256":"190c80934d9ceb89f547ed19d4d49d8f67c8dd7d92c91b424b74697b3eb65733","branch":"refs/heads/fix/publish-from-site-settings","head":"18b64bde7bf8ad124fff503de37e845500e3e9e1","baseRef":"refs/heads/main","baseCommit":"18b64bde7bf8ad124fff503de37e845500e3e9e1","sourceTree":"070f7132804068149c20fff3c511aa108f319476","absentOptional":[]} -->
