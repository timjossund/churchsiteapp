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
**Resolution:** Independent re-review on 2026-09-24 confirmed the token remains unchanged; fresh calculations reproduce 4.16:1, 3.86:1, and 3.97:1 against the three listed backgrounds. Remains open P2. Recomputed at 56d3a3e9b74ad30a77ba7dcc77202c22d5d78b40 with the same results. Fresh calculations at eb0aff9ef186c2e4293bc0b3eb9b0224a92c8c6d still produce 4.16:1, 3.86:1, and 3.97:1; remains open P2. Independent review at b323c32dd21be2fd22fa6ef6049a9de34e987007 reproduced the same contrast ratios; remains open P2.

### F-05 [P2] open - Cover successful republishing and replacement of public media access

**File:** tests/Feature/SitePublishingTest.php:96
**Found:** 2026-09-25 by /audit independent current (scope: current; lenses: quality, security, performance, tests)
**Why it matters:** The feature's central update path is not exercised by a successful second publication in the current tests. The draft-isolation test stops after editing, and the public-media test verifies continued access before republishing but never checks that the previous image becomes inaccessible afterward. A regression that retains the old snapshot, leaves the unpublished-changes flag set, or preserves a removed image in the public allowlist could therefore pass the suite. This is a confirmed coverage gap, not a reproduced product defect.
**Suggested fix:** Extend the existing Pest tests to publish, change content and replace or remove a referenced image, then publish again. Assert that the URL is unchanged, new content is served, the new image is accessible when present, the old image is 404, and the editor reports no unpublished changes. Reuse fake storage and the existing routes.
**Resolution:** Open; no source or test changes made during independent review.
