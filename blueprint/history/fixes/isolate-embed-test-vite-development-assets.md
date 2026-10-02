# Fix: Isolate embed test Vite development assets

**Type:** Fix
**Status:** verified
**Branch:** fix/isolate-embed-test-vite-development-assets

## The problem

The development-assets dataset in `tests/Feature/EmbedBlockTest.php` mocks
`Vite::isRunningHot()` to true without supplying a hot file. Rendering the Blade
document then reads the missing `public/hot` and returns 500. This blocks
`composer ci:check` before giving work can begin.

## The fix

Use an isolated temporary Vite hot-file path for this test. Supply a development
server URL only for the development dataset; the built-assets dataset uses a
missing temporary path. Exercise the real Vite mode detection and document
rendering, preserve the existing CSP and Inertia assertions, restore Vite's
original path, and clean up in `finally`. Never write or remove Herd's hot file.
No production changes, new dependencies, or broader test refactor.

## Build steps

- [x] Replace the mock with an isolated fixture for both asset datasets.
      Done when the focused EmbedBlockTest passes with all existing assertions,
      cleanup runs even on failure, and `composer ci:check` passes.

## Verify

- `php artisan test tests/Feature/EmbedBlockTest.php`
- `composer ci:check`

## Notes

Tim approved repairing the baseline failure in this chat. Keep the separately
approved giving plan edits intact. This fix must be reviewed and completed
before activating Feature 21a; do not overwrite an active fix spec with giving.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":1482,"specSha256":"f6640a7f186b4f3f6bc963695f995c3339dbaf599617046882e1d1cb2ef50bd2","sourceTree":"4268ae0cb81e0758bf0fddf77e7d8eef3cfc5554","head":"5d037e0b06a6252c243700e61191fa6ada5f5f34","branch":"refs/heads/fix/isolate-embed-test-vite-development-assets","baseRef":"refs/heads/main","baseCommit":"5d037e0b06a6252c243700e61191fa6ada5f5f34","absentOptional":[]} -->
