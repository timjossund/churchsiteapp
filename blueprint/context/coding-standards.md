# Coding Standards

## Stack and structure

- Backend: PHP 8.3 or newer with Laravel 13. Routes live in `routes/`, application code in `app/`, and migrations in `database/migrations/`.
- Frontend: Vue 3, TypeScript, Inertia, and Vite Plus. Page components live in `resources/js/pages/`; reusable components live in `resources/js/components/`.
- Use Composer for PHP dependencies and npm for frontend dependencies. `composer.lock` and `package-lock.json` are the current lockfiles.
- Keep rendering through the existing Laravel routes and Inertia pages. Add an API endpoint only when a current feature needs one.
- Follow the existing Fortify authentication and Laravel request patterns for protected actions.

## PHP and data

- Use Laravel validation or Form Request classes for untrusted request data.
- Keep authorization at the route, controller, or policy boundary for data owned by a user.
- Use Eloquent and Laravel migrations for persisted data. The app's production database and hosting are not chosen yet.
- Handle expected failures with user-facing validation or error responses. Do not hide unexpected exceptions.
- Follow the existing PHP formatting rules through Laravel Pint.

## Vue and TypeScript

- Use Vue single-file components and TypeScript. Prefer inferred types where clear and explicit types at data boundaries.
- Keep pages focused on presentation and interaction. Use existing composables and shared components when they fit a current need.
- Use generated Wayfinder route and action helpers where the scaffold already uses them.
- Avoid `any`; use a specific type or `unknown` with narrowing.
- Follow the existing Vite Plus lint and format configuration.

## Styling

- Use Tailwind CSS 4 and the theme variables in `resources/css/app.css`.
- Reuse the existing UI components and patterns before adding a new styling system.
- Support the scaffold's light and dark appearance behavior where a screen uses those themes.

## Testing and verification

- Pest is configured for PHP unit and feature tests in `tests/`. `composer test` runs PHP format checks, PHP static analysis, and the PHP test suite. Tests are an active gate for logic-bearing changes.
- `composer ci:check` also runs the existing frontend lint and TypeScript checks. It is the current combined local check; there is no GitHub Actions workflow yet.
- For logic with meaningful edge cases, add focused tests in the existing test suite. Verify UI behavior through the running app or browser evidence when relevant.
- Browser test automation is not configured. Do not install a runner in the middle of an unrelated feature.
- The exact commands are listed in `AGENTS.md`.

## Scope and code quality

- Build for current requirements and follow established patterns. Avoid abstractions and dependencies for hypothetical needs.
- Keep functions and components focused. Remove unused imports, dead code, and commented-out code.
- Comment a non-obvious reason or constraint, not what the code already says.

## Writing

- Use concise, direct prose in docs and comments.
- Avoid em dashes, en dashes, and ellipsis characters in generated content.
