# Feature: Church information blocks

**From build-plan:** feature 13
**Build attempt:** 1
**Branch:** feature/church-information-blocks
**Status:** verified

## Goal

Give owners a choice of service-time layouts and let each Contact block show one address with a directions link. Draft previews and published pages should agree, while existing blocks keep their current appearance until an owner selects the new layout or adds an address.

## In scope

- Offer the current service-time list and a compact two-column grid. Keep the list as the default for existing blocks; collapse the grid to one column on narrow screens.
- Add one optional, multiline plain-text address to each Contact block and a directions link that opens Google Maps for that address.
- Reuse the existing block editor, owner-scoped update route, structured block content, and publication snapshot. Keep email and phone links, service-time entry editing, shared block styles, and draft/publish behavior intact.
- Match service entry order, text, links, and responsive behavior between the Vue preview and published Blade page.

## Out of scope

- Multiple addresses per Contact block, geocoding, address verification, embedded maps, custom map providers, or owner-entered directions URLs.
- Changing the existing service-time day/time/label format, recurring-event rules, contact forms, or site-wide styling from Feature 14.
- A migration or new dependency; block content is already stored as structured JSON and copied into publication snapshots.

## Build loop

Work on `feature/church-information-blocks`. Complete the steps in order, show each step for review (`workflow.stepReview: every`), and offer an optional checkpoint commit after an approved step (`workflow.checkpointCommits: enabled`). `/complete` creates the final feature commit. Keep generated Vite and Wayfinder output local and ignored.

## Build steps

- [x] **1. Service-time layouts.** Add a labeled layout control to Service Times, validate and save the list/grid choice, and render the selected layout in the editor preview and published page. Keep existing entries and shared style settings unchanged. **Done when:** an owner can switch layouts, save, reload, discard an unsaved choice, and publish it; list remains the default for legacy or unrecognized saved values; the grid keeps entry order and becomes one column on narrow screens; unsupported values or block types are rejected; focused request/publication tests and the declared checks pass.
- [x] **2. Contact address and directions.** Add a labeled multiline address field with editor error feedback, render escaped address text in the preview and published page, and derive a safe Google Maps directions link only when a nonblank address exists. Preserve email and phone behavior. **Done when:** an owner can add, edit, clear, save, reload, and discard one address; the preview updates from the draft while the public page changes only after Publish; empty addresses show no address or directions link; malicious or non-string input cannot become markup or a live unsafe URL; focused request/publication tests and `composer ci:check` pass.

## Files / areas

- `app/Http/Requests/UpdateSiteBlockRequest.php` for block-type-specific validation and owner-scoped writes through the existing route.
- `resources/js/pages/Sites/Show.vue` for editor state, controls, save/discard handling, error association and focus, and live preview.
- `resources/views/sites/published.blade.php`, `app/Http/Controllers/PublishedSiteController.php`, and `resources/css/app.css` for published layout, address, and directions rendering.
- Existing snapshot flow in `app/Actions/BuildSitePublicationSnapshot.php` carries block content without a new version or schema; inspect its behavior, but change it only if a focused test proves a gap.
- Focused coverage in `tests/Feature/SiteBlockEditorTest.php` and `tests/Feature/PublishedSiteTest.php` or adjacent feature tests.

## Data / contracts

- Service Times stores its choice in `content.style.layout` as `list` or `grid`, reusing the existing block-specific layout key. Permit those values only on Service Times while retaining `image_left` and `image_right` only for Text and image. Missing or unrecognized saved service layout renders as `list`; a submitted unsupported value fails validation. The list keeps its current markup and appearance. The grid presents the same ordered entries in two compact columns at wider widths and one column on narrow screens, with day, formatted time, and optional label readable in each entry.
- Contact stores one optional address as `content.address`, a plain string. Missing legacy data and an empty or whitespace-only value mean no address and no directions link. Allow multiline text, preserve meaningful internal line breaks for display, and trim surrounding whitespace when deriving the destination. Reject non-string input. Display the address as escaped text with preserved line breaks; do not treat it as HTML.
- Derive, rather than store, the directions URL from the address: `https://www.google.com/maps/dir/?api=1&destination=<URL-encoded trimmed address>`. The owner cannot submit a URL for this link. Label it “Get directions”; open it in a new tab with `rel="noopener noreferrer"`, and keep it keyboard focusable. Do not contact Google while editing, saving, or rendering.
- The existing authenticated owner boundary controls block updates. New content remains a draft until the owner publishes; visitor rendering uses the saved publication snapshot. Render absent and malformed legacy snapshot values safely without a PHP or Vue error. Keep existing email and telephone links independent of the address.
- New controls follow the editor's current disabled/loading behavior. Associate validation messages with their fields, announce errors, focus the first invalid field, and clear stale field errors as the owner edits.

## Testing

- Baseline `composer ci:check` passed before this spec: frontend format/lint and TypeScript checks, PHP format/static checks, production build, and 699 Pest tests with 5,376 assertions.
- Step 1 focused `ChurchInformationBlockTest` passed with 7 tests and 45 assertions. `composer ci:check` passed with frontend format/lint and TypeScript checks, PHP format/static checks, production build, and 706 Pest tests with 5,421 assertions. Responsive layout and editor save/discard still need manual browser review.
- Step 2 focused `ChurchInformationBlockTest` passed with 11 tests and 89 assertions. Final `composer ci:check` passed with frontend format/lint and TypeScript checks, PHP format/static checks, production build, and 710 Pest tests with 5,465 assertions. No live browser interaction was run; address editing, keyboard focus, and narrow layout still need manual review.
- Add focused tests for valid and invalid service layouts, unsupported block types, legacy defaults, retained entry order, and owner isolation through the existing update route.
- Add focused tests for optional and invalid address values, escaped multiline rendering, encoded Google Maps destinations, empty-address behavior, unchanged email/phone links, and draft-versus-published isolation.
- Run the focused tests and `composer ci:check` during implementation. Browser tests are not configured; manually review both layouts at narrow and desktop widths, keyboard focus on directions, and preview/published parity on the local Herd site. Do not claim live browser evidence until it is observed.

## Notes for the AI

- Reuse the existing block content/style, editor state, and publishing paths. Build only these two additions; do not introduce mapping APIs, a new link type, or a shared styling system.
- The user chose the current list plus a compact two-column grid, Google Maps directions, and one address per Contact block during Feature 13 planning.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":7864,"specSha256":"2bde6dc1a87b3cd7b55d65cd2c66835ec5d5771ad8015a70670a7d190e1f7459","branch":"refs/heads/feature/church-information-blocks","head":"b241f964c09381251797faa1c9d3836ec2d76a19","baseRef":"refs/heads/main","baseCommit":"b241f964c09381251797faa1c9d3836ec2d76a19","sourceTree":"dd02afe96c34539b686e2f21659deb2d3e46ddff","absentOptional":[]} -->
