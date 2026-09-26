# Churchsite - Project Overview

<!-- blueprint:source-hash 531fdc27370b40ac2f52ce7af3609e82b59e736ca657a5a1e3e3954bd994172d -->

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
- **6.** **Per-site subscriptions** - Integrate Spark with Stripe so each site can have its own monthly or annual subscription and billing status.
- **7.** **Custom domains and SSL** - Let a subscribed site connect a BYO `www` hostname through Cloudflare for SaaS, show DNS instructions and connection status, serve its published Blade page over HTTPS, and remove custom-domain access when the subscription becomes inactive.

## Data model

These are the initial logical shapes; later feature specs choose migrations and exact column names. Preserve the boundary between editable drafts and published content.

### User

- Existing authentication record: `id` (integer), `name` (string), `email` (unique string), and credential fields managed by the scaffold.
- Has many sites; an authenticated user may edit only their own sites.

### Site

- `id` (integer), `user_id` (foreign key), `name` (string), `slug` (unique string for the platform subdirectory), `theme_key` (string).
- Has many ordered pages. Published page content remains stable while its draft changes.
- Draft site presentation: `header` and `footer` (structured data), `seo_title` and `seo_description` (strings), `social_image_id` (nullable media reference).
- Published snapshot (structured data, nullable) and `published_at` (nullable timestamp) keep the visitor-facing version stable while draft fields and blocks change.
- Has many blocks and media assets; has a customer hostname and site-specific billing state when configured.

### Page

- `id` and `site_id` (integers), `name` (string), `position` (integer), and Home identity. Each site has ordered pages, each with its own ordered blocks.
- Existing content becomes Home, which cannot be deleted and retains `/s/{site-slug}`. Other pages receive editable paths such as `/s/church/about` in Feature 8b.
- Feature 8a supplies draft page management and preserves Home publishing. Feature 8b publishes all pages together, including page navigation alongside current-page section links and page-specific title, description, and social image. Public deletions take effect only after Publish.

### SiteBlock

- `id` (integer), `site_id` (foreign key), `page_id` (page relationship), `type` (block type), `position` (integer), and structured content and styling options.
- `content` holds fields for the selected block. Service times are structured day/time entries with an optional label; contact details supply email and telephone links; video embeds use a YouTube or Vimeo URL.
- Image-bearing blocks refer to site media assets. Block position determines page order.

### MediaAsset

- `id` (integer), `site_id` (foreign key), `storage_key` (string for an object in an IONOS bucket), `mime_type` (string), and `alt_text` (nullable string).
- Referenced by block content, logo, or social preview image; access must stay within the owning site.

### CustomHostname

- `id` (integer), `site_id` (foreign key), `hostname` (unique `www` hostname), `cloudflare_id` (nullable string), `hostname_status` and `ssl_status` (strings), `verified_at` (nullable timestamp).
- Hostname resolution identifies one site. Serve it only when hostname and SSL are ready and that site's subscription is active.

### Site subscription

- Spark/Stripe-managed customer and subscription records are associated with the site as the billable unit, not with account-wide access.
- Record or derive the plan interval and active subscription state so only that site's custom hostname is enabled. Monthly and annual prices remain to be set.

## Tech stack

- **Laravel 13 and MySQL** - Shared application, persistence, ownership checks, hostname resolution, and publishing.
- **Vue 3, TypeScript, and Inertia** - Signup, account dashboard, and block editor.
- **Blade and Tailwind 4** - Published sites and theme styles; share page and block data and styles with the editor preview to keep it faithful.
- **Laravel Spark and Stripe** - Per-site monthly and annual subscriptions.
- **IONOS buckets** - Uploaded image storage.
- **xCloud and Cloudflare for SaaS** - Laravel origin hosting, customer-hostname routing, and edge SSL.
- **Pest** - Existing PHP test suite.

## Monetization

Users can create and publish shareable subdirectory sites before paying. Each site needs its own subscription for a live `www` hostname. Monthly and annual prices are TODOs; no domain-purchasing flow is planned.

## UI and experience

- Dashboard opens site settings with shared styles, header/logo, footer, and page management. Opening a page shows publishing, preview, block list, free reordering, and the selected block’s side panel.
- Users can configure expanded block-specific styling and options in addition to choosing a site theme.
- An explicit Publish action distinguishes drafts from the version visitors see.
- Initial themes span warm/traditional, clean/minimal, and bold/contemporary styles. Switching themes changes styling, not content or order.
- Domain setup shows DNS values and explains connection and SSL progress in plain language.
- Public sites should be responsive and accessible, including usable links and image descriptions.

## Deployment

Host the shared Laravel app and MySQL arrangement on xCloud, with `churchsite.app` serving product pages and preview subdirectories. Cloudflare for SaaS manages customer `www` hostnames and edge certificates; the xCloud origin serves the correct Blade-rendered site. Configure Stripe webhooks, mail, IONOS storage access, and queues or scheduled checks if domain verification requires them.

> TODO: Choose the MySQL hosting arrangement and final deployment commands. Validate xCloud origin host handling, Cloudflare for SaaS onboarding, and separate hostname and certificate readiness before the custom-domain feature.

## Open questions

- What are the monthly and annual per-site prices?
- What exact xCloud origin and Cloudflare configuration supports many customer hostnames on this one Laravel application?
- Where will production MySQL run, and what are the final deploy and operational checks?
