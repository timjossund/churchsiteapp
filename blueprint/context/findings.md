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
**Resolution:** Independent re-review on 2026-09-24 confirmed the token remains unchanged; fresh calculations reproduce 4.16:1, 3.86:1, and 3.97:1 against the three listed backgrounds. Remains open P2. Recomputed at 56d3a3e9b74ad30a77ba7dcc77202c22d5d78b40 with the same results. Fresh calculations at eb0aff9ef186c2e4293bc0b3eb9b0224a92c8c6d still produce 4.16:1, 3.86:1, and 3.97:1; remains open P2. Independent review at b323c32dd21be2fd22fa6ef6049a9de34e987007 reproduced the same contrast ratios; remains open P2. Independent review at 4a699022b3c80400b8b5c0990c35bad01f490f70 inspected the unchanged CSS token and its settings/editor uses; fresh calculations again produce 4.16:1, 3.86:1, and 3.97:1. Remains open P2.
