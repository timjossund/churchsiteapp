# Fix: Block editor layout

**Type:** Fix
**Status:** verified
**Branch:** fix/block-editor-layout

## The problem

On the site editor, the “Add a block” choices are at the bottom of the left Blocks panel, below the block list. Users must scroll past the list to add content. Both the Blocks arranger and Selected block editor scroll out of view while the page preview is being reviewed, making it harder to arrange and edit blocks while seeing the page.

## The fix

- Move the “Add a block” heading and choices below the Publishing section and above the editor columns. Keep all choices in one horizontal scrolling row, sized to show three and a half choices at once on wider screens, with a visible cue that more choices are available. Keep cards large enough to read on phones while allowing horizontal scrolling. Keep each choice’s label and description, current add behavior, and processing/error feedback.
- Keep both the Blocks arranger and Selected block editor sticky near the top of the viewport on wide layouts. Constrain each to the available viewport height and let its contents scroll when needed, so long block lists and forms remain usable. Keep the natural stacked layout on narrow screens.
- Preserve the block list, preview, publishing controls, responsive behavior, and existing keyboard and screen-reader access.

## Build steps

- [x] 1. Reposition the add-block controls and make both editor side panels sticky on wide layouts. **Done when:** add choices appear immediately below Publishing in one horizontal scrolling row, three and a half choices are visible on wider screens with a clear scroll cue, narrow screens can scroll the readable choices without page overflow, adding a block still works, and both the Blocks arranger and Selected block editor stay visible while scrolling the preview with their contents reachable.

## Verify

- Open a site editor with several blocks. Confirm the add choices are directly below Publishing, appear in one horizontal row with three and a half choices visible and a scroll cue, and add a block from that row.
- Select a block and scroll the page preview. Confirm the Selected block panel stays in view on a wide screen; for a long form, scroll within the panel to reach Save and Remove.
- Scroll the page preview with a long block list. Confirm the Blocks arranger stays in view and its list can scroll independently.
- At a narrow viewport, confirm the choices remain on one row, can scroll horizontally, remain readable, and do not create page-level horizontal overflow; confirm the editor returns to normal document flow.
- Run `npm run check`, `npm run types:check`, and `npm run build`. No browser automation is configured, so record manual layout verification only if it was actually performed.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":2779,"specSha256":"cf7339bc9df9a77c92330da5d99b858ebe30522de417f16ae7794cfa19387fd3","branch":"refs/heads/fix/block-editor-layout","head":"689d916204281d8a8eee24a87cb506cdb1a56b58","baseRef":"refs/heads/main","baseCommit":"689d916204281d8a8eee24a87cb506cdb1a56b58","sourceTree":"4b0b2b1b6b50c1b2888f778b9ba829992232ab0b","absentOptional":[]} -->
