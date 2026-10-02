# Fix: Welcome hero redesign

**Type:** Fix
**Status:** verified
**Branch:** fix/welcome-hero-redesign

## Scope

Apply the user-approved `prototypes/welcome-hero.html` design to the real homepage hero in `resources/js/pages/Welcome.vue`. Preserve the existing header, remaining homepage sections, guest signup links, signed-in dashboard links, and light/dark appearance support. Keep the illustrative website preview static and accessible.

## Design reference

`prototypes/welcome-hero.html` and `prototypes/theme.css`. The user approved the prototype and requested implementation in this chat.

## Build steps

- [x] Replace the hero copy, layout, and illustration; verify responsive layout, auth links, frontend checks, and the configured combined local gate.

## Done when

- The live welcome hero matches the approved headline, preview, arch backdrop, and editing-card composition.
- Guest and authenticated CTA destinations retain their existing behavior.
- Mobile layout fits the viewport and light/dark modes remain readable.
- Navigation and all sections below the hero remain unchanged.
- `composer ci:check` passes, with live visual evidence when the local app is available.

## Verification

- `composer ci:check` passed: frontend format/lint, Vue typecheck, Pint, PHPStan, production build, and 993 Pest tests (7,293 assertions).
- Rendered the actual Vue hero with the production CSS in a temporary static harness. Visually checked 320px light, 390px dark, and 768px light layouts.
- The existing local Herd address refused connections, so full-app live verification remains unavailable.
- Existing homepage guest and authenticated route tests passed. Header and all sections after the hero are unchanged.
- No findings or pending independent review. This isolated presentation change does not select the configured sensitive-work review gate.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":1859,"specSha256":"ecc1b483944702aa6356592b913460dfc819010060606bb3c0b0a73e1fd0d843","branch":"refs/heads/fix/welcome-hero-redesign","head":"fca9187bb2f4098c1805796e53cb3c2d1ec92dad","baseRef":"refs/heads/main","baseCommit":"fca9187bb2f4098c1805796e53cb3c2d1ec92dad","sourceTree":"90beab82c99cd38ae209a3acfc8e9b76debe1153","absentOptional":[]} -->

## How to try it

Open the local homepage at `http://churchsiteappnew.test/` with Herd running. Check the new headline and website illustration at desktop and mobile widths, then confirm the primary button opens signup for guests or the dashboard for signed-in users.

## Prototype cleanup

The consumed `prototypes/welcome-hero.html` and `prototypes/theme.css` were removed during completion. Their design and required tokens now live in the welcome component.
