# Findings

> **Generated file.** The findings ledger: review findings raised by `/audit`
> against the work in progress, each with a durable ID, severity (P0-P3), and
> status. `/implement` marks repaired findings `fixed`, a later `/audit` pass
> moves them to `closed`, and `/complete` refuses to merge while any P0 or P1
> finding is `open` or `fixed`, then archives resolved findings with the work
> and resets this file.

### F-02 [P2] open - Darken muted workspace text for readable contrast

**File:** resources/css/app.css:97
**Found:** 2026-09-23 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The new light-mode muted text token `#718076` is applied to normal-size instructions, supporting copy, site counts, and blank-card text in Dashboard.vue and Sites/Show.vue. Calculated relative-luminance contrast is 4.16:1 against white, 3.86:1 against the page background `#f5f7f4`, and 3.97:1 against `#f9faf7`. All fall below the 4.5:1 minimum for normal-size text, reducing readability for users with low vision. This is a color calculation from source tokens, not a browser measurement.
**Suggested fix:** Darken the existing light-mode `--workspace-muted` token until it reaches at least 4.5:1 against all three backgrounds. Keep the existing token and layout; no new styling machinery is needed.
**Resolution:** Independent re-review on 2026-09-24 confirmed the token remains unchanged; fresh calculations reproduce 4.16:1, 3.86:1, and 3.97:1 against the three listed backgrounds. Remains open P2. Recomputed at 56d3a3e9b74ad30a77ba7dcc77202c22d5d78b40 with the same results. Fresh calculations at eb0aff9ef186c2e4293bc0b3eb9b0224a92c8c6d still produce 4.16:1, 3.86:1, and 3.97:1; remains open P2. Independent review at b323c32dd21be2fd22fa6ef6049a9de34e987007 reproduced the same contrast ratios; remains open P2. Independent review at 4a699022b3c80400b8b5c0990c35bad01f490f70 inspected the unchanged CSS token and its settings/editor uses; fresh calculations again produce 4.16:1, 3.86:1, and 3.97:1. Remains open P2. Independent review at 84987f0b76ccac27899ec5a1391894c52ad7857a confirmed the unchanged token and freshly calculated 4.16:1, 3.86:1, and 3.97:1 contrast ratios. Remains open P2 and outside this feature's repair scope. Independent review at `c6ef0aba71b6b1cd6b809071950f7d99714f79aa` re-examined the unchanged token and its new deletion-dialog and pending-dashboard uses; fresh source calculations confirm 4.16:1, 3.86:1, and 3.97:1 against the same backgrounds. F-02 remains open P2; no browser contrast measurement or acceptance is claimed.

Independent review by codex / gpt-6-astra at `069973b71542ed6898039622076fb7b7b9ee70cb` on 2026-09-28 re-examined the unchanged muted token and new editor help-text uses. Fresh offline luminance calculations reproduce 4.16:1, 3.86:1, and 3.97:1. F-02 remains open P2; no browser measurement or user acceptance is claimed.

Independent review by codex / gpt-6-astra at `2da4c6e360eccd31b2de31610bfdc6b4c085bf6a` on 2026-09-28 re-examined the unchanged token and new appearance-control help text. Fresh offline luminance calculations again produce 4.16:1, 3.86:1, and 3.97:1. F-02 remains open P2; no browser measurement, repair, or user acceptance is claimed.

Independent review by codex / gpt-6-astra at `11d3b762ef496ce6227e46b6ea9c2751bde1b0c6` on 2026-09-28 re-examined the unchanged muted workspace token and appearance-control help text. Fresh offline calculations reproduce 4.16:1, 3.86:1, and 3.97:1 against the three recorded backgrounds. F-02 remains open P2; no browser measurement, repair, or acceptance is claimed. No new finding was raised in the complete feature review.

### F-06 [P2] open - Validate the ownership record length before checkout

**File:** app/Rules/CustomerHostname.php:12
**Found:** 2026-09-27 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The rule accepts full 253-character hostnames, but `CustomHostname::ownershipRecordName()` prepends `_churchsite.`, making the required ownership TXT name 265 characters. DNS cannot represent that name. Hosts longer than 241 characters can therefore be reserved and sent to paid checkout even though this connection can never satisfy its mandatory ownership check. An offline PHP reproduction using the exact 253-character boundary fixture confirmed `CustomerHostname` accepts it and native `FILTER_VALIDATE_DOMAIN` accepts the hostname but rejects its generated ownership name. The existing boundary test verifies reservation only, so it misses this integration constraint. This is a rare boundary input, not a general onboarding failure.
**Suggested fix:** Validate the generated ownership DNS name before reservation and checkout, using the existing validation rule/native domain validation, and show a hostname error when it cannot fit. Update the boundary regression to cover the generated record and rejection before payment. Reconcile the spec's stated 253-character acceptance with the required ownership prefix when making the repair; do not introduce an alternate ownership protocol solely for this edge case.
**Resolution:** Confirmed at `1bd9ea5c94d10e6d573435038f468a8b370da442`. Reproduction built `www.` plus labels of 63, 63, 63, and 57 characters; hostname length 253, application acceptance true, native hostname validation true, ownership length 265, native ownership validation false. No DNS request or provider call was made. Remains open P2.
<<<<<<< Updated upstream
=======

### F-07 [P2] closed - Preserve working hero links in the editor preview

**File:** resources/js/pages/Sites/Show.vue:2019
**Found:** 2026-09-28 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** Every preview hero anchor now has an unconditional `@click.prevent` with no navigation handler. A configured section, page, or external button therefore does nothing on ordinary mouse or keyboard activation. Before this change, the primary section link scrolled and the external link opened in a new tab; the neighboring preview page-navigation links remain operable. Owners cannot try either hero destination in the editor. Published links are unaffected, so this is a nonblocking preview regression. Compiling the exact Sites/Show template confirms Vue generates `withModifiers(() => {}, ["prevent"])`; invoking that modifier cancels the event with no replacement action.
**Suggested fix:** Preserve normal section navigation and restore safe new-tab attributes for external preview links. Use the existing Inertia navigation/unsaved-edit guard for page destinations. Remove the unconditional no-op prevention handler; no new navigation abstraction is needed.
**Resolution:** Confirmed at `f3ee5050a1f7b722d2ed4e2b4438c21772f51eeb` by source comparison and the installed Vue compiler/runtime. No product code changed. No browser click walkthrough was performed in this review.

F-07 repair evidence: native section anchors, safe external new-tab anchors, and Inertia page Links now replace the no-op prevention handler for both buttons. Isolated Chrome verified all six destination/button combinations including unsaved-page cancellation. Final project checks passed with 553 tests and 4,214 assertions. Independent re-review on 2026-09-28 at `15b8234eaf48835ddf1aa30daa5216e3bf6b2ea3` confirmed the repair and closes F-07. The exact current Vue template was compiled and rendered to VNodes for both buttons across all three destination types: section/external use native anchors without cancelling click handlers, external links retain `_blank` and `noopener noreferrer`, and page destinations use Inertia Link. Source tracing confirms those Link visits pass through the existing router before-event and unsaved-edit confirmation guard. Both changed Vue script/template pairs compile. This pass reviewed the complete base-to-target delta and found no new defect from this repair. No fresh browser click walkthrough was performed by this reviewer.
>>>>>>> Stashed changes
