# Feature: Public homepage and branding

**From build-plan:** feature 17
**Build attempt:** 1
**Branch:** feature/public-homepage-and-branding
**Status:** verified

## Goal

Give Church Site App a welcoming public homepage and a coherent product identity that matches its builder. Replace the Laravel starter welcome screen at `/` and carry the approved doorway/arch logo into the dashboard and authentication pages.

## Design reference

Create an original design using the existing builder as its visual reference: workspace tokens in `resources/css/app.css`, dashboard in `resources/js/pages/Dashboard.vue`, and shared settings layout in `resources/js/pages/Sites/Settings.vue`. Use forest green (#2c6749), dark green (#21563c), warm off-white (#f5f7f4), white cards, subtle borders, rounded corners, and spacious typography. This is not a pixel-for-pixel replication. No external reference image has been supplied.

The approved logo direction is a simple doorway/arch symbol with its doorway restored and a small cross above the arch plus the exact wordmark **Church Site App**, with all three words at the same size on one line. Favor a clean, balanced SVG silhouette that remains readable in a small sidebar and auth header. Use the builder's serif-heading/sans-body rhythm for the homepage, with its existing local font assets and fallback stacks. Present the actual logo and rendered page for user review during implementation.

## In scope

- One public marketing homepage at `/`, replacing `Welcome.vue`; no separate welcome route or signed-in onboarding page.
- A reusable SVG product mark and wordmark on the homepage, dashboard shell, and all auth pages reached through the shared auth layout.
- Homepage hero with an illustrative builder preview, feature highlights, how-it-works section, pricing, and clear signup/login or dashboard actions.
- Responsive layout, accessible links and typography, existing light/dark appearance support, and descriptive homepage title/description.
- The approved plan/overview updates adding Feature 17 ahead of Feature 15.

## Out of scope

- Changing authentication, account creation, verification, password reset, two-factor logic, or billing behavior.
- Customer church logos, customer published pages, a separate marketing CMS, new signup forms, or additional public routes.
- Testimonials, customer counts, performance promises, analytics, newsletter capture, new legal pages, or unverified marketing claims.
- Additional dependencies, remote font downloads, logo animation, a brand-management system, or broader dashboard redesign.

## Build loop

Use `workflow.stepReview: every`: implement one step, verify it, and stop for approval. Optional checkpoint commits are enabled but need explicit approval. `/complete` creates the final feature commit. Offer a read-only walkthrough after the final review packet.

## Build steps

- [x] **1. Shared product identity.** Design the doorway/arch SVG and Church Site App wordmark; adapt the existing logo components and active dashboard/auth shells to use them. **Done when:** the same recognizable mark appears in expanded and collapsed dashboard navigation and on login, registration, verification, password-reset, password-confirmation, and two-factor screens; linked branding has an accessible name and the expected existing destination; light/dark and narrow layouts remain readable; authentication behavior stays intact; relevant existing auth/dashboard tests and `composer ci:check` pass. Show the logo for user review before the next step.
- [x] **2. Public homepage.** Replace the starter content with the approved page structure, builder-inspired presentation, reusable branding, and working navigation/actions. **Done when:** guests can understand the product, navigate sections, register, or log in; signed-in users see a dashboard action; pricing accurately explains the existing per-site offer; the illustrative builder preview contains no private/customer data or misleading active controls; the page fits narrow and wide screens with accessible focus, readable contrast, and reduced-motion support; no Laravel starter marketing remains; focused homepage regression tests and final `composer ci:check` pass. Present the page for visual review and distinguish observed browser evidence from source/build evidence.

## Files / areas

- `resources/js/pages/Welcome.vue` and its existing home route in `routes/web.php`.
- `resources/js/components/AppLogoIcon.vue`, `resources/js/components/AppLogo.vue`, and their existing consumers, especially `AppSidebar.vue` and `resources/js/layouts/auth/AuthSimpleLayout.vue`.
- `resources/js/layouts/AuthLayout.vue` and other existing logo consumers only where needed to prevent inconsistent branding.
- `resources/css/app.css` for limited shared branding/homepage styles; reuse workspace tokens without changing customer-site palettes.
- Existing homepage, authentication, and dashboard Pest tests in `tests/Feature/`, with a focused homepage test if needed.
- Approved `blueprint/project-plan.md`, `blueprint/build-plan.md`, and regenerated `blueprint/context/project-overview.md` updates remain part of this feature.

## Data / contracts

- The canonical visible product name is **Church Site App**. Keep one reusable logo/wordmark implementation rather than copying SVG markup into each page. Use static trusted SVG paths, a viewBox, currentColor or approved palette colors, and no scripts or remote assets. Decorative marks beside visible text are hidden from assistive technology; icon-only links retain a meaningful accessible name.
- Preserve the existing named `home`, `login`, `register`, and `dashboard` routes and authentication-state branching. Dashboard branding continues to link to the dashboard; homepage/auth branding links home. Do not alter authentication middleware, validation, errors, loading states, form controls, or redirects. Keep every existing form accessible and functional after layout changes.
- Hero copy explains building and maintaining a church website without code. Use a primary account-creation action for guests and dashboard action for authenticated users. Secondary navigation can anchor to Features, How it works, and Pricing. Use ordinary semantic links and buttons with visible keyboard focus, without dead-end placeholder links.
- Illustrate the builder with lightweight local HTML/CSS showing its recognizable block list and page preview. Label it as an illustrative preview where needed and keep decorative simulated controls out of the tab order. Do not query real church content, create demo accounts, or embed a live authenticated editor.
- Feature copy may describe existing blocks, theme/font choices, service times/contact information, multi-page editing, draft/Publish separation, and custom domains. Do not advertise Rich text or other unfinished capabilities.
- Pricing states USD $15/month or $150/year **per site** for custom-domain service, no trial. Explain that creating and publishing a shareable subdirectory site is available before payment, and payment starts when connecting an owned `www` domain. Do not promise domain registration, bare-domain support, or free custom domains. Pricing CTAs enter the existing account/dashboard flow, never initiate payment directly.
- Homepage sections use semantic headings with one h1, readable supporting text, adequate contrast (4.5:1 normal text), and visible focus. Do not reuse a low-contrast muted token for new small text where it fails against the chosen surface; use existing darker text tokens locally. Do not expand scope into unrelated contrast repairs.
- Preserve the scaffold's light/dark appearance behavior and avoid horizontal overflow at narrow widths. Motion is optional and must respect prefers-reduced-motion. Reuse installed/local fonts; remove the starter page's external Inter stylesheet.
- The public homepage renders without application-specific database queries or external services. No persisted data, migrations, new permissions, or API contracts are introduced. Empty-account dashboard and all existing form pending/invalid/error states remain unchanged.

## Testing

- Step 2/final: `php artisan test tests/Feature/PublicHomepageTest.php` passed (2 tests, 23 assertions). `composer ci:check` passed with 750 tests and 5,811 assertions, frontend formatting/lint/TypeScript, PHP formatting/static analysis, and production build. Homepage opened for user review; no agent-observed browser screenshots or interaction checks were captured. This feature changes presentation only, not auth/security boundaries or persisted data, so conditional independent review was not selected; manual Check/Audit/try-guide gates were not requested.

- Step 1: focused auth/workspace tests passed (40 tests, 162 assertions). `composer ci:check` passed (748 tests, 5,788 assertions) with frontend checks, PHP formatting/static analysis, and build. A final dark-mode logo contrast adjustment was followed by frontend format/lint and a production rebuild. No live browser checks performed; SVG preview is provided for design review.

- Planning baseline `composer ci:check` passed: frontend formatting/lint and TypeScript, PHP formatting/static analysis, production build, and 748 Pest tests with 5,788 assertions.
- Add proportional route/Inertia regression coverage for guest and authenticated homepage responses, plus any new response props or branching. Reuse existing authentication and dashboard tests; do not add tests that merely mirror SVG paths or marketing markup.
- Run the relevant focused tests for each step and `composer ci:check` before its review handoff and at the final gate as required by the build loop.
- Browser tests are not configured. When browser access is available, inspect homepage, dashboard sidebar (expanded/collapsed), login/register, and representative auth errors at mobile and desktop sizes in light/dark appearances; verify logo consistency, keyboard navigation, primary links, and console/network health. No live browser evidence has been captured during planning. Do not start a dev server from planning.

## Notes for the AI

- User approved one public homepage at `/`, exact branding Church Site App, doorway/arch logo (revised by the user to retain the doorway and place a small cross above the arch), and placement of Feature 17 before Rich text.
- Critique clarified that this is product branding, not customer church branding, and preserves the existing auth and payment flows. Keep marketing claims tied to shipped behavior.
- Use repository-native SVG for this simple scalable mark, not a raster logo or an additional icon dependency.
- Frozen first-build archive: `blueprint/history/features/17-public-homepage-and-branding.md`. Branch and archive collision checks passed; no prior Feature 17 build was found.
- Existing deployment-plan disagreement about Worker proof remains a separate planning issue; do not amend deployment claims as part of this feature.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":10971,"specSha256":"dbe2e4a32e09b918946fd23eb5641702da66ad2e32735fecaac8d4876b37b17e","branch":"refs/heads/feature/public-homepage-and-branding","head":"d84d5d62a9e65093527957a4ee2181c91403ac54","baseRef":"refs/heads/main","baseCommit":"d84d5d62a9e65093527957a4ee2181c91403ac54","sourceTree":"31e9cc2e3eea61fea8fd566dbc2ff6548fcdaaf9","absentOptional":[]} -->
