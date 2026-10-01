# Church Site App - Project Overview

<!-- blueprint:source-hash 279c6d79289938fd38bd3c95ef6362ed11f82705369cb96f50a96cf3c69a60a2 -->

> A block-based church website builder with shareable multi-page previews and later paid custom domains.

## Problem

Churches need modern websites, while the people responsible for them may have little time for design or code. Churchsite lets users create and update a site by filling out and arranging blocks. The initial launch includes expanded block styling and options, multi-page sites, and shareable subdirectory publishing. Per-site billing and custom domains follow later.

## Users and usage model

- A signed-in user creates and edits multiple church sites. Each site has one editing account in the first release.
- Visitors can view published sites without signing in. A `churchsite.app` subdirectory preview is public to anyone with its link but excluded from search indexing.
- One shared application and MySQL database serve all sites. Site ownership and hostname routing must keep each site's content and uploaded assets separate.
- A site needs its own active paid subscription to serve its published content on a customer `www` hostname. Without one, its subdirectory publication remains available.
- Bare customer domains, multiple editors, domain purchasing, and visitor contact forms are outside the first release. Usage volume and special compliance requirements have not been established.

## Features in build order

- **1.** **Site workspace** - Let signed-in users create and manage multiple blank sites, with one account owning and editing each site.
- **2.** **Block page editor** - Let users add, edit in a side panel, remove, and freely reorder the agreed landing-page blocks, including structured service times, hero links, and YouTube/Vimeo video embeds.
    - **2a.** **Editor foundation** - Save ordered blocks and provide the page preview, block list, and side-panel editing for about, plain text, and heading-and-text blocks.
    - **2b.** **Church details** - Add hero blocks with section or external links, structured service times, and contact blocks with email and phone links.
    - **2c.** **Media blocks** - Add image, text-and-image, and YouTube/Vimeo video blocks; image placeholders await uploads in Feature 4.
- **3.** **Themes and site shell** - Add the initial theme choices plus editable header, footer, logo or church name, and section navigation without changing block content or order.
- **4.** **Image uploads** - Let users upload images for the relevant blocks and store them in IONOS buckets.
- **5.** **Drafts, publishing, and preview** - Publish a stable Blade-rendered version to a shareable, non-indexed `churchsite.app` subdirectory page while later edits remain drafts; add page title, description, and social preview image.
- **9.** **Block styling and options** - Improve block styling and expand the options users can configure for the blocks in their sites.
- **8.** **Multi-page sites** - Let users add and manage pages within a site, with navigation and page-specific published content.
    - **8a.** **Page management** - Preserve existing content as a protected Home page; add, rename, reorder, delete, and edit other draft pages while retaining Home publishing. Separate shared site settings and page management from the focused page editor.
    - **8b.** **Navigation and publishing** - Publish all pages together, add page navigation alongside section links, editable page paths, and page-specific metadata; keep Home at the existing URL and apply public page deletions only on Publish.
- **6.** **Per-site subscriptions** - Integrate Cashier with Stripe so each site can have its own monthly or annual subscription and billing status.
- **7.** **Custom domains and SSL** - Let a subscribed site connect a BYO `www` hostname through Cloudflare for SaaS, show DNS instructions and connection status, serve its published Blade page over HTTPS, and remove custom-domain access when the subscription becomes inactive.
    - **7a.** **Worker connection** - Establish authenticated Cloudflare Worker forwarding to Laravel, isolate public requests from platform routes, and prove the connection using an operator-owned test hostname.
    - **7b.** **Customer domains** - Add the connect-domain UI, Stripe handoff, automatic DNS/SSL status checks, and published-site routing with paid-access enforcement.
- **10.** **Hero blocks** - Add background images with readable overlays, height options, an editable welcome label, and a second button; offer normal scrolling, fixed backgrounds, and half-speed (0.5) parallax with a static reduced-motion fallback.
- **11.** **Shared block styling** - Add section spacing, content width, heading sizes, and additional theme-aware background choices while preserving existing block defaults.
- **16.** **Site deletion** - Let an owner permanently delete a site after confirming its name, take it offline immediately, cancel subscription renewal, and safely finish domain, upload, and billing cleanup without affecting other sites.
- **12.** **Image and text blocks** - Add image proportions, crop position, corner styles, captions, and optional buttons for text-bearing blocks.
- **13.** **Church information blocks** - Add service-time layout choices and contact addresses with directions links.
- **14.** **Site-wide styling** - Add font pairings, an editable accent color, and consistent button styles across the site.
- **17.** **Public homepage and branding** - One public homepage at `/` with builder-styled marketing sections and a shared Church Site App doorway/arch logo across homepage, dashboard, and auth pages.

- **18.** **Contact map** - Optional OpenStreetMap embed on contact blocks, with an owner-selected pin, matching editor previews and published pages, attribution, and existing directions links.

- **19.** **Embed block** - A provider-restricted Google Calendar block using a validated HTTPS embed URL, with matching draft preview and publication. Raw HTML, scripts, and user-controlled iframe permissions are excluded.

- **20.** **Custom site favicons** - Site Settings upload/preview/replacement/removal of a square PNG icon, applied to all public pages and custom domains on Publish, with the default icon when unset.

## Site deletion

Owners can permanently delete a site from settings by confirming its name. Acceptance immediately revokes editing and all public page, domain, and media access. Cancel renewal using the existing period-end policy while retaining billing and external cleanup identities until safe finalization; do not delay taking the site offline until the paid period ends. Remove its pages, blocks, publication, uploads, and custom-hostname connection without affecting the account or other sites. Retry pending external cleanup safely. No restore, refund, or new immediate subscription-termination workflow is introduced.

## Data model

These are the initial logical shapes; later feature specs choose migrations and exact column names. Preserve the boundary between editable drafts and published content.

### User

- Existing authentication record: `id` (integer), `name` (string), `email` (unique string), and credential fields managed by the scaffold.
- Has many sites; an authenticated user may edit only their own sites.

### Site

- `id` (integer), `user_id` (foreign key), `name` (string), `slug` (unique string for the platform subdirectory), `theme_key` (string).
- Has many ordered pages. Published page content remains stable while its draft changes.
- Optional site favicon: an owned media reference included in the frozen publication; upload and removal are draft changes until Publish.
- Draft site presentation: `header` and `footer` (structured data), `seo_title` and `seo_description` (strings), `social_image_id` (nullable media reference).
- Published snapshot (structured data, nullable) and `published_at` (nullable timestamp) keep the visitor-facing version stable while draft fields and blocks change.
- Has many blocks and media assets; has a customer hostname and site-specific billing state when configured.

- Site deletion requires a persisted request timestamp and retention of site, billing, domain, and media identities until external cleanup and billing reconciliation finish; the feature spec defines the exact lifecycle.

### Page

- `id` and `site_id` (integers), `name` (string), `position` (integer), and Home identity. Each site has ordered pages, each with its own ordered blocks.
- Existing content becomes Home, which cannot be deleted and retains `/s/{site-slug}`. Other pages receive editable paths such as `/s/church/about` in Feature 8b.
- Feature 8a supplies draft page management and preserves Home publishing. Feature 8b publishes all pages together, including page navigation alongside current-page section links and page-specific title, description, and social image. Public deletions take effect only after Publish.

### SiteBlock

- `id` (integer), `site_id` (foreign key), `page_id` (page relationship), `type` (block type), `position` (integer), and structured content and styling options.
- `content` holds fields for the selected block. Service times are structured day/time entries with an optional label; contact details supply email and telephone links; video embeds use a YouTube or Vimeo URL.
- Image-bearing blocks refer to site media assets. Block position determines page order.

### Planned block and appearance data

- Features 10-14 extend draft block options and shared site appearance settings; their specs define exact presets and stored fields. Existing blocks keep their current appearance until owners choose new options.
- Hero background motion offers normal scrolling, viewport-fixed images, and images moving at 0.5 times page scroll speed. Hero text and buttons scroll normally; reduced-motion users receive static images.
- Contact maps are off by default. Owners find their church on OpenStreetMap, choose Share and Include marker, and paste the full sharing link. The preview and publication show the chosen pin with attribution and retain address/directions links. No automatic address lookup or API key is required.
- All new settings participate in the existing publication snapshot and site ownership rules. Existing text blocks meet the simple builder's needs; a rich text editor is not planned.

### MediaAsset

- `id` (integer), `site_id` (foreign key), `storage_key` (string for an object in an IONOS bucket), `mime_type` (string), and `alt_text` (nullable string).
- Referenced by block content, logo, social preview image, or site favicon; access must stay within the owning site.

### CustomHostname

- `id` (integer), `site_id` (foreign key), `hostname` (unique `www` hostname), `cloudflare_id` (nullable string), `hostname_status` and `ssl_status` (strings), `verified_at` (nullable timestamp).
- Hostname resolution identifies one site. Serve it only when hostname and SSL are ready and that site's subscription is active.

### Site subscription

- Cashier/Stripe-managed customer and subscription records are associated with the site as the billable unit, not with account-wide access.
- Record or derive the plan interval and active subscription state so only that site's custom hostname is enabled. Pricing is USD $15/month or $150/year, with no trial; payment starts when domain connection begins.

## Tech stack

- **Laravel 13 and MySQL** - Shared application, persistence, ownership checks, hostname resolution, and publishing.
- **Vue 3, TypeScript, and Inertia** - Signup, account dashboard, and block editor.
- **Blade and Tailwind 4** - Published sites and theme styles; share page and block data and styles with the editor preview to keep it faithful.
- **Laravel Cashier and Stripe** - Per-site monthly and annual subscriptions, with Stripe-hosted Checkout and Billing Portal.
- **IONOS buckets** - Uploaded image storage.
- **Plesk, Cloudflare for SaaS, and Cloudflare Workers** - Shared Laravel origin hosting, customer certificates, and authenticated forwarding to the existing application virtual host.
- **Pest** - Existing PHP test suite.

## Monetization

Users can create and publish shareable subdirectory sites before paying. Each site needs its own subscription for a live `www` hostname. Pricing is USD $15/month or $150/year per site, without a trial. Payment begins when the owner starts domain connection. Account deletion cancels renewals and occurs after the latest paid site subscription ends. No domain-purchasing flow is planned.

## UI and experience

- Brand the product as **Church Site App**. Replace the starter welcome screen at `/` with one public homepage, using the builder's forest-green/warm-off-white palette, typography, rounded cards, and generous spacing.
- The homepage includes a hero and illustrative builder preview, signup/login actions, features, how it works, and existing pricing. Use a reusable doorway/arch SVG mark and wordmark across homepage, dashboard, and auth pages. Keep authentication behavior and customer church logos unchanged.

- Dashboard opens site settings with shared styles, header/logo and favicon, footer, and page management. Opening a page shows publishing, preview, block list, free reordering, and the selected block’s side panel.
- Users can configure expanded block-specific styling and options in addition to choosing a site theme.
- An explicit Publish action distinguishes drafts from the version visitors see. Whole-site deletion takes the site offline immediately and does not require Publish.
- Site settings offer name-confirmed permanent deletion; the dashboard reports pending cleanup without permitting the site to reopen.
- Initial themes span warm/traditional, clean/minimal, and bold/contemporary styles. Switching themes changes styling, not content or order.
- Planned appearance controls include hero imagery and parallax, shared spacing/width/heading/background options, image crop/proportions/corners/captions, optional text-block buttons, service-time layouts, contact addresses/directions, and site font pairings/accent/button styles. Preview and published rendering must agree, including mobile and reduced-motion behavior.
- Domain setup shows DNS values and explains connection and SSL progress in plain language.
- Public sites should be responsive and accessible, including usable links and image descriptions.

## Deployment

Host the shared Laravel application on Plesk, with `churchsite.app` serving platform routes and subdirectory previews. The server also hosts other websites. Cloudflare for SaaS manages customer certificates; an approved forwarding Worker will send customer requests to a fixed HTTPS application origin and securely identify the original hostname. Public forwarding must not expose account, editor, or billing routes. No per-customer Plesk alias or certificate is required by the intended Worker design.

The operator reports Plesk Obsidian 18.0.81.1 on Debian 13.7, nginx proxying to Apache, administrator/SSH access, a live platform, and a successful manual alias test at `test.timjossund.com/up` with Full (strict). This proves manual connectivity only. Feature 7a proves Worker transport before Feature 7b customer rollout. The test hostname is an operator fixture; customer onboarding still requires `www`.

Configure Stripe webhooks, mail, IONOS storage access, and queues or scheduled checks as needed for the domain workflow.

## Open questions

- Worker deployment and its independent transport proof remain pending in Feature 7a.
- Where will production MySQL run, and what are the final deploy and operational checks?

- Define exact style presets in any future feature specs.
- The build plan marks Features 7a/7b complete, while deployment notes still describe Worker transport proof as pending; reconcile this separately from the block roadmap.
