# Fix: Dashboard site card status and published links

**Type:** Fix
**Status:** verified
**Branch:** fix/dashboard-site-card-status

## The problem

The My sites cards in the main workspace/dashboard show static "Getting started",
"Blank site, ready for your story", and "Your workspace is ready" copy even after
content is added or the site is published. Published sites have no direct viewing
action. The dashboard currently receives only each site's ID, name, and deletion
timestamp.

## The fix

Use persisted publication state and the presence of draft blocks across all site
pages to render accurate card copy. A site with at least one block is no longer
blank; a published site always receives published copy, even if its current draft
has no blocks. Draft edits after publication do not remove its Published badge.

| Site state              | Badge            | Card top text                    | Supporting text                                | Actions                   |
| ----------------------- | ---------------- | -------------------------------- | ---------------------------------------------- | ------------------------- |
| Unpublished, no blocks  | Getting started  | Blank site, ready for your story | Your workspace is ready                        | Open site                 |
| Unpublished, has blocks | Draft            | Your site is taking shape        | Keep building, then publish when you are ready | Open site                 |
| Published               | Published        | Your site is published           | Your published site is ready to view           | Open site; View published |
| Deletion pending        | Deletion pending | This site is offline             | Existing cleanup message                       | None                      |

- Show "View published" as an ordinary public navigation link styled as a button.
  Use the site's HTTPS custom-domain homepage when its existing domain readiness
  and paid-access rules allow it to serve. Otherwise use the published platform
  URL at `/s/{slug}`. Do not link to the editor's draft preview.
- Place View published on the left as a solid workspace-green button and Open
  site on the right as a text action. Justify the actions to the card edges; Open
  site remains right-aligned when it is the only action.
- Open View published in a new tab and include that behavior in its accessible
  label.
- Reuse `CustomHostname::isReadyToServe()` and existing publication routes rather
  than duplicating readiness rules. Do not call Stripe or Cloudflare while loading
  the dashboard. Return only the card data needed by the frontend.
- Preserve owner-scoped listing, ordering, creation, empty state, and Open site
  behavior. Deletion pending takes precedence over every other card state.
- Use existing components and workspace styles, with usable keyboard focus and
  mobile layout. No database migration or new dependency is required.

## Build steps

- [x] Add the minimal dashboard card data in `SiteController::index`, update
      `Dashboard.vue` to render the state-specific copy and viewing action, and add
      focused Pest coverage for the data and URL selection.
      **Done when:** blank, nonblank draft, published, and deletion-pending cards match
      the table; View published uses a serving custom domain or falls back to the
      platform publication; other users' sites remain absent.

- [x] Style View published with the workspace green and place it at the left
      edge, with Open site at the right edge.
      **Done when:** actions appear in that order, remain keyboard accessible, and
      can wrap on narrow cards without overflowing.

## Verify

- Cover blank sites, blocks on Home or another page, published sites with later
  draft edits, and deletion-pending sites in the existing Pest suite.
- Cover no domain, a ready domain with paid access, and domains that are pending,
  disconnected, or lack paid access. Only a serving domain becomes the viewing
  URL; unpublished and deletion-pending sites have no viewing URL.
- Run `composer ci:check` for the combined frontend and backend checks.
- In the running dashboard, inspect the card states, click Open site and View
  published, and confirm keyboard access and narrow-screen layout. Confirm the
  public link shows the published version rather than unpublished edits.

## Implementation evidence

- Follow-up styling places the solid green View published button on the left and
  Open site on the right. View published opens in a new tab with an accessible
  announcement and `noopener noreferrer`.
- `composer ci:check` passed again after the alignment change. `npm run check`
  and `npm run build` passed after adding the new-tab behavior.

- Focused card, workspace, and deletion tests: 40 passed, 370 assertions.
- `composer ci:check`: passed frontend formatting/lint, Vue TypeScript, Pint,
  PHPStan, production build, and all 790 Pest tests (6,042 assertions).
- `git diff --check`: passed.
- Live browser clicks, keyboard behavior, and mobile appearance remain unverified;
  no browser tool is available in this session.
- Regular Audit, Check, and try-guide gates are manual and were not requested.
  Independent review is not selected: this small fix reuses the existing domain
  serving checks and owner query without changing access or billing behavior.
- Existing open P2 findings F-02 (muted workspace text contrast) and F-06
  (ownership-record length) remain outside this fix. No P0/P1 blockers or active
  independent-review request are recorded.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":5545,"specSha256":"a6fcd0a9c9f9c8b894085be8ff85d3730c4f7c1d5e9eecc161528beda20f60ee","branch":"refs/heads/fix/dashboard-site-card-status","head":"a8f55633d22e60f0852e8ef0fe1e4e1dfaba3be2","baseRef":"refs/heads/main","baseCommit":"a8f55633d22e60f0852e8ef0fe1e4e1dfaba3be2","sourceTree":"e4c8aa7ad4129cccc22815db60cc3ac504088313","absentOptional":[]} -->
