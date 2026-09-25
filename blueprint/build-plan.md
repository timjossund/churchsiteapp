# Build Plan

The first seven features lead to the single-page launch. Multi-page support is the first feature afterward. Each item is a high-level outcome; implementation steps belong in its later feature spec.

## Single-page launch

- [x] 1. **Site workspace** - Let signed-in users create and manage multiple blank sites, with one account owning and editing each site.
- [x] 2. **Block page editor** - Let users add, edit in a side panel, remove, and freely reorder the agreed landing-page blocks, including structured service times, hero links, and YouTube/Vimeo video embeds.
    - [x] 2a. **Editor foundation** - Save ordered blocks and provide the page preview, block list, and side-panel editing for about, plain text, and heading-and-text blocks.
    - [x] 2b. **Church details** - Add hero blocks with section or external links, structured service times, and contact blocks with email and phone links.
    - [x] 2c. **Media blocks** - Add image, text-and-image, and YouTube/Vimeo video blocks; image placeholders await uploads in Feature 4.
- [x] 3. **Themes and site shell** - Add the initial theme choices plus editable header, footer, logo or church name, and section navigation without changing block content or order.
- [x] 4. **Image uploads** - Let users upload images for the relevant blocks and store them in IONOS buckets.
- [ ] 5. **Drafts, publishing, and preview** - Publish a stable Blade-rendered version to a shareable, non-indexed `churchsite.app` subdirectory page while later edits remain drafts; add page title, description, and social preview image.
- [ ] 6. **Per-site subscriptions** - Integrate Spark with Stripe so each site can have its own monthly or annual subscription and billing status.
- [ ] 7. **Custom domains and SSL** - Let a subscribed site connect a BYO `www` hostname through Cloudflare for SaaS, show DNS instructions and connection status, serve its published Blade page over HTTPS, and remove custom-domain access when the subscription becomes inactive.

## First feature after launch

- [ ] 8. **Multi-page sites** - Let users add and manage pages within a site, with navigation and page-specific published content.

## Planning TODOs

- Set monthly and annual prices.
- Prove the xCloud origin and Cloudflare for SaaS workflow for many customer hostnames and SSL certificates on one shared Laravel app.
- Choose the MySQL hosting arrangement and final deployment setup.
