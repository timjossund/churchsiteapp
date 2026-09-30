# Fix: Improve muted workspace text contrast

**Type:** Fix
**Status:** verified
**Branch:** fix/muted-workspace-text-contrast
**Fixes:** F-02

## The problem

The light-mode `--workspace-muted` color in `resources/css/app.css` is
`#718076`. Normal-size supporting text falls below the 4.5:1 contrast target
on the white, page, and soft workspace backgrounds recorded in finding F-02.

## The fix

Darken the existing light-mode token to `#627267`, preserving its muted green
appearance. Keep the dark-mode token, layout, and component usage unchanged.
This is one shared color change, with no new styling system or dependencies.

## Build steps

- [x] Update the light-mode muted color and calculate relative-luminance contrast
      against `#ffffff`, `#f5f7f4`, and `#f9faf7` from the actual CSS values.
      **Done when:** each pairing is at least 4.5:1 and the dark-mode color is
      unchanged. Mark F-02 fixed with evidence, retaining it for audit closure.

## Verify

- Record before/after contrast calculations from source colors. These prove
  color contrast, not browser rendering or overall accessibility compliance.
- Run `composer ci:check` for the configured frontend checks, PHP checks,
  production build, and Pest suite.
- If browser inspection is available, inspect muted text on Dashboard, Site
  Settings, and the page editor in light mode; check dark mode for regressions.
- Run `/audit current` after implementation to re-examine and close F-02 before
  completion. The domain ownership-record length finding F-06 is the next
  separate fix and remains outside this work item.

## Implementation evidence

- One product change: light-mode `--workspace-muted` is `#627267`.
- Source calculations: white 4.16 -> 5.09:1, page background 3.86 -> 4.73:1,
  soft background 3.97 -> 4.86:1. All three meet 4.5:1.
- CSS comparison proves dark mode and every other style are unchanged.
- `composer ci:check` passed: frontend lint/format and typecheck, Pint, PHPStan,
  production build, and 975 Pest tests.
- `/audit current` reviewed the complete branch/local change across all four
  lenses against `origin/main` at `9844c6fc96d3337124f30a4e5f28b9028f775f5b`.
  F-02 closed; no new findings. F-06 remains open for the next separate fix.
- No new logic tests needed for this single CSS token change. Browser rendering
  remains unverified; the evidence is source-color contrast calculation.
- Independent review is not selected for this one-token visual change. Check
  and try guide are manual; Audit was required by this spec and completed.


<!-- blueprint:completion {"schemaVersion":1,"specBytes":2560,"specSha256":"b461717967c95241985c8954bce1c28e18af4f1cc37eb4db564d71cbd6df152b","branch":"refs/heads/fix/muted-workspace-text-contrast","head":"9844c6fc96d3337124f30a4e5f28b9028f775f5b","baseRef":"refs/heads/main","baseCommit":"9844c6fc96d3337124f30a4e5f28b9028f775f5b","sourceTree":"85dcee0c9f48981c030c1d7b043eb2d926a84d6f","absentOptional":[]} -->

## Findings

### muted-workspace-text-contrast/F-02 [P2] closed - Darken muted workspace text for readable contrast

**File:** resources/css/app.css:97
**Found:** 2026-09-23 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The new light-mode muted text token `#718076` is applied to normal-size instructions, supporting copy, site counts, and blank-card text in Dashboard.vue and Sites/Show.vue. Calculated relative-luminance contrast is 4.16:1 against white, 3.86:1 against the page background `#f5f7f4`, and 3.97:1 against `#f9faf7`. All fall below the 4.5:1 minimum for normal-size text, reducing readability for users with low vision. This is a color calculation from source tokens, not a browser measurement.
**Suggested fix:** Darken the existing light-mode `--workspace-muted` token until it reaches at least 4.5:1 against all three backgrounds. Keep the existing token and layout; no new styling machinery is needed.
**Resolution:** Independent re-review on 2026-09-24 confirmed the token remains unchanged; fresh calculations reproduce 4.16:1, 3.86:1, and 3.97:1 against the three listed backgrounds. Remains open P2. Recomputed at 56d3a3e9b74ad30a77ba7dcc77202c22d5d78b40 with the same results. Fresh calculations at eb0aff9ef186c2e4293bc0b3eb9b0224a92c8c6d still produce 4.16:1, 3.86:1, and 3.97:1; remains open P2. Independent review at b323c32dd21be2fd22fa6ef6049a9de34e987007 reproduced the same contrast ratios; remains open P2. Independent review at 4a699022b3c80400b8b5c0990c35bad01f490f70 inspected the unchanged CSS token and its settings/editor uses; fresh calculations again produce 4.16:1, 3.86:1, and 3.97:1. Remains open P2. Independent review at 84987f0b76ccac27899ec5a1391894c52ad7857a confirmed the unchanged token and freshly calculated 4.16:1, 3.86:1, and 3.97:1 contrast ratios. Remains open P2 and outside this feature's repair scope. Independent review at `c6ef0aba71b6b1cd6b809071950f7d99714f79aa` re-examined the unchanged token and its new deletion-dialog and pending-dashboard uses; fresh source calculations confirm 4.16:1, 3.86:1, and 3.97:1 against the same backgrounds. F-02 remains open P2; no browser contrast measurement or acceptance is claimed.

Independent review by codex / gpt-6-astra at `069973b71542ed6898039622076fb7b7b9ee70cb` on 2026-09-28 re-examined the unchanged muted token and new editor help-text uses. Fresh offline luminance calculations reproduce 4.16:1, 3.86:1, and 3.97:1. F-02 remains open P2; no browser measurement or user acceptance is claimed.

Independent review by codex / gpt-6-astra at `2da4c6e360eccd31b2de31610bfdc6b4c085bf6a` on 2026-09-28 re-examined the unchanged token and new appearance-control help text. Fresh offline luminance calculations again produce 4.16:1, 3.86:1, and 3.97:1. F-02 remains open P2; no browser measurement, repair, or user acceptance is claimed.

Independent review by codex / gpt-6-astra at `11d3b762ef496ce6227e46b6ea9c2751bde1b0c6` on 2026-09-28 re-examined the unchanged muted workspace token and appearance-control help text. Fresh offline calculations reproduce 4.16:1, 3.86:1, and 3.97:1 against the three recorded backgrounds. F-02 remains open P2; no browser measurement, repair, or acceptance is claimed. No new finding was raised in the complete feature review.

Independent review by codex / gpt-6-astra at `847798b72dcd450beaf95d24e3b6a627b8cda964` on 2026-09-30 re-examined the unchanged muted workspace token and the new contact-map instructions in Sites/Show.vue. Fresh offline relative-luminance calculations reproduce 4.16:1, 3.86:1, and 3.97:1 against the recorded backgrounds. F-02 remains open P2; no browser measurement, repair, or user acceptance is claimed. No new finding was raised in the complete contact-map review.

Independent review by codex / gpt-6-astra at `0303d71e6aab72ffb1564cd6ec72abfc842fbb1d` on 2026-09-30 re-examined the unchanged muted workspace token and the new favicon help/status text in Sites/Settings.vue. Fresh offline luminance calculations reproduce 4.16:1, 3.86:1, and 3.97:1 against the recorded backgrounds. F-02 remains open P2; no browser measurement, repair, or user acceptance is claimed.

Implementation repair: light-mode `--workspace-muted` is now `#627267`. Source-color calculations give 5.09:1 on white, 4.73:1 on `#f5f7f4`, and 4.86:1 on `#f9faf7`. The dark-mode section is byte-for-byte unchanged. Marked fixed pending audit re-review.

Audit current re-review: inspected the complete one-token CSS diff and nearby light/dark definitions across quality, security, performance, and tests. Fresh source calculations confirm 5.09:1, 4.73:1, and 4.86:1 for the three backgrounds. The CSS equals the base file with only the intended light-mode substitution, so dark mode and other styles are unchanged. No new defect found in this scoped change. `composer ci:check` passed with 975 Pest tests. F-02 is closed; browser rendering was not inspected.

