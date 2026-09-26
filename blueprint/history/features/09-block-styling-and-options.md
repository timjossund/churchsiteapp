# Feature: Block styling and options

**From build-plan:** feature 9
**Build attempt:** 1
**Status:** verified
**Branch:** `feature/block-styling-and-options`

## Goal

Let site owners choose a small set of per-block layout, alignment, and background styles. Keep the choices consistent with the selected site theme, and show the same styling in the editor preview and the published page.

## In scope

- Add curated style controls to the existing block editor for the nine existing block types.
- Offer these values:
    - Layout: `image_left` or `image_right` for text-and-image blocks. Missing layout uses the current image-right layout.
    - Alignment: `left` or `center` for block content. Missing alignment preserves the current renderer default: the video block is centered and other blocks are left-aligned.
        - Background: `theme` or `soft`, using the existing theme background and soft-background tokens. Missing values preserve the current appearance; About blocks keep their existing soft background by default.
- Store optional style choices inside the existing block `content` JSON so drafts continue to publish through the current snapshot flow.
- Keep existing content editing, block ordering, site themes, and published-versus-draft behavior intact.
- Validate style values against fixed enums and render them through known classes and theme tokens.

## Out of scope

- Arbitrary color pickers, custom CSS, typography controls, new themes, or new block types.
- Multi-page editing and publishing, which remains the next planned feature after this one.
- Changes to block copy, uploads, page navigation, billing, or domains.

## Build loop

`workflow.stepReview` is `every` and `workflow.checkpointCommits` is `enabled`. Implement one step, verify it, then pause for review and the checkpoint choice before continuing. `/complete` creates the feature commit.

## Build steps

- [x] Add the optional style contract and server validation for block updates. Existing blocks without style settings must remain valid and render as they do today. Add focused request and persistence coverage. **Done when:** valid enum choices save on the owning site's block, invalid values receive field errors, and legacy block content still passes the existing feature tests.
- [x] Add labeled layout, alignment, and background controls to the selected-block editor. Preserve selected-block draft, save, discard, and validation behavior. **Done when:** each supported choice is editable and saved for its block, and invalid server feedback is associated with the relevant control and focused accessibly.
- [x] Apply saved styles consistently to the editor preview and published Blade page using the selected theme's tokens. **Done when:** saved choices are visible in both renderers, missing choices retain the current appearance, and the existing publication snapshot carries the values without changing published content before Publish.
- [x] Run the project verification command and review the full diff. **Done when:** `composer ci:check` passes and a browser walkthrough confirms editing, saving, draft preview, publishing, and responsive contrast for each style choice. The owner reviewed the UI during implementation, confirming persistence and that the styling looks good so far; browser automation is not configured.

## Files / areas

- `resources/js/pages/Sites/Show.vue` - block editor state, controls, saving, and live preview.
- `app/Http/Requests/UpdateSiteBlockRequest.php` - block content allowlist and style enum validation.
- `app/Http/Controllers/SiteBlockController.php` - persists validated content in the existing JSON field.
- `app/Actions/BuildSitePublicationSnapshot.php` - published snapshot currently preserves block content.
- `resources/views/sites/published.blade.php` - public block rendering.
- `resources/css/app.css` - theme-aware preview tokens.
- `tests/Feature/SiteBlockEditorTest.php`, `tests/Feature/SiteBlockPersistenceTest.php`, and `tests/Feature/PublishedSiteTest.php` - focused regression coverage.

## Data / contracts

- Keep the existing site and block ownership boundary: an authenticated user may update only a block belonging to their site.
- Add an optional `style` object inside block `content`. Its allowed keys and values are `layout: image_left|image_right` for `text_image`, `alignment: left|center`, and `background: theme|soft`. Reject unsupported keys, values, and layout choices for other block types.
- Existing block rows with no `style` key remain valid. Missing values resolve to the current rendering, including the video block's centered alignment and About block's soft background.
- Style values are identifiers only. Do not accept class names, CSS declarations, or arbitrary colors from the request; map validated values to fixed Tailwind classes and existing theme variables.
- The style settings are draft block content. They appear on the published page only after Publish includes them in the snapshot.

## Testing

- Add focused Pest coverage for allowed and rejected style values, site ownership, legacy content without style fields, and snapshot/render parity.
- Use the configured `composer ci:check` as the final automated gate. Browser automation is not configured; the implementation walkthrough supplies UI evidence.

## Notes for the AI

- Preserve the current default appearance when style keys are absent, including the centered video block and soft About block.
- Keep the editor preview and Blade renderer aligned for all three site themes. Use only theme variables already present in `resources/css/app.css` unless an established design need requires a reviewed addition.
- Maintain accessible labels, keyboard use, focus, and error associations for the new controls.
- Do not implement Multi-page sites in this feature.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":5813,"specSha256":"39aa5086ac8a07136d721df75b4c61fb4cd44b35d8ca01fcc82948d36ff125fb","branch":"refs/heads/feature/block-styling-and-options","head":"2c73bf66e758c5f41b159d58f81cac8fecbfc307","baseRef":"refs/heads/main","baseCommit":"2c73bf66e758c5f41b159d58f81cac8fecbfc307","sourceTree":"57e2bf13cffd6727a3882a07afe42d3e7ec43cb1","absentOptional":[]} -->
