# Churchsite - Project Overview

<!-- blueprint:source-hash 60b5325ddc0b3f7a1ef808b20744dc8cffe5f3bdc50cc5c4309f1bd84394fc16 -->

> A block-based church website builder with shareable previews and paid custom domains.

## Problem

Churches need modern websites, while the people responsible for them may have little time for design or code. Churchsite lets users create and update a site by filling out and arranging blocks. The first release builds single-page sites; multi-page sites follow after launch.

## Users and usage model

- A signed-in user creates and edits multiple church sites. Each site has one editing account in the first release.
- Visitors can view published sites without signing in. A `churchsite.app` subdirectory preview is public to anyone with its link but excluded from search indexing.
- One shared application and MySQL database serve all sites. Site ownership and hostname routing must keep each site's content and uploaded assets separate.
- A site needs its own active paid subscription to serve its published content on a customer `www` hostname. Without one, its subdirectory publication remains available.
- Bare customer domains, multiple editors, domain purchasing, and visitor contact forms are outside the first release. Usage volume and special compliance requirements have not been established.

## Features in build order

1. **Site workspace** - Self-service accounts create and manage multiple blank, singly owned sites.
2. **Block page editor** - Add, edit in a side panel, remove, and reorder hero, about, plain text, heading and text, structured service times, contact links, image, text and image, and YouTube/Vimeo embed blocks. Hero buttons link to a section or external URL.
3. **Themes and site shell** - Warm/traditional, clean/minimal, and bold/contemporary themes style an editable header, footer, church name or logo, and section navigation without changing content order.
4. **Image uploads** - Users upload images from their devices into IONOS buckets for image-bearing blocks and site presentation.
5. **Drafts, publishing, and preview** - Explicit Publish creates a stable Blade-rendered page on a shareable, non-indexed `churchsite.app` subdirectory URL; edits remain drafts until the next publication. Include page title, description, and social preview image.
6. **Per-site subscriptions** - Spark and Stripe provide monthly and annual billing for each site independently.
7. **Custom domains and SSL** - Paid sites connect a BYO `www` hostname through Cloudflare for SaaS, receive DNS instructions and status, and serve their published page over HTTPS. Inactive subscriptions lose hostname access.
8. **Multi-page sites, after launch** - Add pages, navigation, and page-specific published content.

## Data model

These are the initial logical shapes; later feature specs choose migrations and exact column names. Preserve the boundary between editable drafts and published content.

### User

- Existing authentication record: `id` (integer), `name` (string), `email` (unique string), and credential fields managed by the scaffold.
- Has many sites; an authenticated user may edit only their own sites.

### Site

- `id` (integer), `user_id` (foreign key), `name` (string), `slug` (unique string for the platform subdirectory), `theme_key` (string).
- Draft site presentation: `header` and `footer` (structured data), `seo_title` and `seo_description` (strings), `social_image_id` (nullable media reference).
- Published snapshot (structured data, nullable) and `published_at` (nullable timestamp) keep the visitor-facing version stable while draft fields and blocks change.
- Has many blocks and media assets; has a customer hostname and site-specific billing state when configured.

### SiteBlock

- `id` (integer), `site_id` (foreign key), `type` (block type), `position` (integer), `content` (structured data).
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
- **Blade and Tailwind 4** - Published sites and theme styles; share block data and styles with the editor preview to keep it faithful.
- **Laravel Spark and Stripe** - Per-site monthly and annual subscriptions.
- **IONOS buckets** - Uploaded image storage.
- **xCloud and Cloudflare for SaaS** - Laravel origin hosting, customer-hostname routing, and edge SSL.
- **Pest** - Existing PHP test suite.

## Monetization

Users can create and publish shareable subdirectory sites before paying. Each site needs its own subscription for a live `www` hostname. Monthly and annual prices are TODOs; no domain-purchasing flow is planned.

## UI and experience

- A blank site opens in a page editor with a visible preview, block list, free reordering, and a side panel for the selected block.
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
