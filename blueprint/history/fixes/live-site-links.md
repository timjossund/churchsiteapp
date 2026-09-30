# Fix: Show live site links after domain connection

**Type:** Fix
**Status:** verified
**Branch:** fix/live-site-links

## The problem

Site settings always show Go Live, even after a client's custom domain is live,
and provide no adjacent button to visit it. The page editor's View page link
always opens the platform subdirectory publication instead of the live domain.

## The fix

- When the site has a fully live custom domain, rename the settings Go Live
  button to Live settings and add See live site beside it. Live settings keeps
  opening the existing domain and billing settings; See live site opens the
  custom hostname's HTTPS homepage in a new tab.
- Use the existing domain readiness, paid-access, rollout, and publication
  rules to determine whether the site is live. Do not infer readiness from the
  presence of a saved hostname or make provider calls while rendering settings.
- For a published page on a live domain, View page opens its HTTPS custom-domain
  URL: Home at `/`, other pages at their published snapshot paths.
- Preserve platform publication links when the custom domain is not live.
  Pages absent from the publication keep no View page link. Draft path edits
  do not change visitor links until Publish.
- Keep existing domain setup, billing, ownership, and public routing behavior.
  Expose only the minimal live-link information needed by settings and the editor.

## Build steps

- [x] Add live URL selection to the existing settings/editor response and update
      the settings buttons, with focused Pest coverage for URL selection.
      **Done when:** a live site shows Live settings and See live site; View page
      opens the correct custom-domain published path; a pending, unpublished,
      disabled, removing, or unpaid domain retains Go Live and platform links;
      unpublished pages have no visitor link and draft path changes retain the
      published URL.

## Verify

- Cover live Home and secondary-page URLs, draft path changes, unpublished pages,
  and fallback behavior in the existing domain summary and publishing tests.
- Run `composer ci:check` for frontend checks, PHP formatting and type checks,
  the production build, and Pest.
- In site settings for a live domain, confirm both adjacent buttons and their
  destinations. Open published Home and another page in the editor and confirm
  View page opens the corresponding live URL in a new tab.
- Confirm a site without a live domain keeps Go Live and its platform View page
  destination. Check keyboard access and narrow-screen button layout.

## Implementation evidence

- Focused domain summary and multi-page publishing suite: 50 tests passed,
  including live/fallback URLs, unpublished pages, and draft path stability.
- Full `composer ci:check`: passed, including frontend lint/format, Vue
  typecheck, Pint, PHPStan, production build, and all 973 Pest tests.
- Live browser verification is not available in this session. Button layout,
  keyboard focus, and actual custom-domain navigation remain manual checks.
- Regular audit, Check, and try guide are configured as manual. Independent
  review is not selected: this change adds owner-facing links without changing
  authentication, billing enforcement, hostname routing, or stored data.
- Findings ledger: no P0/P1 blockers. Existing open P2 findings F-02 (muted
  text contrast) and F-06 (ownership DNS name length) remain outside this fix.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":3426,"specSha256":"ea2bf2fac2459b57c9b57d305ac194df08406cfa9bbb148420d0ba56aa428f44","branch":"refs/heads/fix/live-site-links","head":"9b1fe34e68b450a477b295869eacbfce304861cd","baseRef":"refs/heads/main","baseCommit":"9b1fe34e68b450a477b295869eacbfce304861cd","sourceTree":"8c2fabf260a4edf940c27d89b257ca98361ce044","absentOptional":[]} -->
