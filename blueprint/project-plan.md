# Project Plan

## 1. Problem and purpose

Churches need modern websites, but the people responsible for them are often busy and may not want to manage web design or code. Churchsite lets a user assemble a church website from editable blocks, publish it, and update it later through a straightforward editor.

The initial launch supports styled, multi-page sites published at a non-indexed `churchsite.app` subdirectory address. Per-site billing and customer `www` hostnames with SSL follow as later features.

## 2. Users and core workflow

Users sign up themselves. One account can create and manage multiple church sites. Each site has one editing account in the first release.

A typical user:

1. Signs up and creates a blank site.
2. Chooses a theme.
3. Adds, removes, and freely reorders blocks.
4. Selects a block and edits its fields in a side panel.
5. Uploads images from their device.
6. Sets the site's search and sharing details.
7. Presses **Publish** to update a shareable `churchsite.app` subdirectory page.
8. Subscribes for that site, connects a `www` hostname they own, follows the displayed DNS instructions, and waits for domain and SSL setup to complete.
9. Makes later edits as drafts and presses **Publish** again when ready.

## 3. First-release features

### Public homepage and branding

- The product is branded **Church Site App**.
- Replace the starter welcome screen with one public marketing homepage at `/`; no separate welcome page.
- Match the builder's forest-green and warm off-white palette, typography, rounded cards, and spacious layout.
- Include a hero with an illustrative builder preview, signup/login actions, feature highlights, how it works, and pricing consistent with the existing business model.
- Design a simple doorway/arch SVG mark with a wordmark and reuse it on the homepage, dashboard, and authentication pages.
- Preserve existing authentication behavior and keep product branding separate from customer church logos.

### Site editor

- Dashboard opens site settings for shared styles, header/logo, footer, and page management. Opening a page leads to its focused publishing, block editor, and preview screen.

- New sites start blank.
- Users can add, remove, and freely reorder blocks.
- Selecting a block opens its fields in a side panel beside the page preview.
- Saved edits remain in draft until the user presses **Publish**.
- The currently published version stays visible while the user edits a draft.

### Site deletion

- An owner can permanently delete one of their sites from site settings after confirming the site's name.
- Take the site offline immediately on acceptance, including the published subdirectory pages, custom hostname, and public media. Block further editing and publishing.
- Cancel subscription renewal and retain the records needed to finish billing reconciliation and external cleanup; do not delay taking the site offline until the paid period ends. Use the existing period-end cancellation policy rather than introducing refunds or immediate subscription termination.
- Remove the site's pages, blocks, publication, and uploaded files, and disconnect its custom hostname. Keep the operation recoverable while Stripe, Cloudflare, or storage cleanup is pending or fails.
- Preserve the owner's account and all other sites. This is permanent deletion, with no restore workflow in this feature.
- Deliver this feature after shared block styling and before image and text block improvements.

### Multi-page sites

- Preserve each existing site's content as its Home page. Home keeps the existing public URL and cannot be deleted.
- Add, rename, reorder, delete, and edit other pages within a site.
- Deliver draft page management first, preserving existing Home publishing; multi-page publication follows in the next feature.
- Publish all pages together. Page deletion changes the public site only after Publish.
- Add page navigation alongside links to sections on the current page.
- Other pages have editable paths such as `/s/church/about`; each page has its own title, description, and social preview image.

### Blocks

- **Hero:** heading, supporting text, image, and button. The button can link to another block on the page or an external URL.
- **About:** an editable introduction to the church.
- **Plain text:** text without a heading.
- **Heading and text:** a heading with supporting text.

The existing text blocks meet the simple builder's needs; a rich text editor is not planned.

- **Service times:** structured entries with a day and time; an optional label can distinguish services.
- **Contact us:** email and phone details that produce `mailto:` and `tel:` links. No visitor contact form is planned for the first release.
- **Contact map:** an optional OpenStreetMap embed on contact blocks, off by default. Owners find their church on OpenStreetMap, choose Share and Include marker, and paste the full sharing link into the block. Show the map and pin in editor previews and published pages with attribution, retaining the existing address and directions link. No automatic address lookup or API key is required.
- **Image:** an uploaded image displayed with rounded corners.
- **Text and image:** editable text and an uploaded image, with a choice of which side shows the image.
- **Embed block:** embed a public Google Calendar from a validated HTTPS embed URL. Support Google Calendar first, with fixed provider restrictions and matching draft previews and published output. Do not accept arbitrary HTML, scripts, or unrestricted iframe permissions. Other providers need a separate reviewed addition.
- **Video embed:** a YouTube or Vimeo video added by URL and displayed as an embedded player.

The site also has an editable header and footer, including a church name or logo and navigation links to page sections.

### Appearance and media

- Provide an initial mix of warm/traditional, clean/minimal, and bold/contemporary themes.
- Switching themes changes colors, fonts, and styling. It preserves content and block order.
- Add block-specific styling and configurable block options beyond the initial theme choices.
- Users upload images from their devices. Store uploads in the owner's IONOS buckets.
- Public pages should work on mobile and desktop and provide accessible text, links, and image descriptions.

### Publishing and search

- **Publish** updates the shareable site at a `churchsite.app` subdirectory address. Anyone with the link can view it.
- Keep the subdirectory page out of search engine results.
- Let users set a page title, description, and social preview image.
- A customer `www` hostname is required for the site's live presence. The published site should be served over HTTPS once its domain and SSL setup succeeds.

### Domains and billing

- Users bring domains they already own. Domain purchasing and bare-domain support are outside the first-release scope.
- Show the DNS records needed to connect a `www` hostname and check the connection automatically.
- A separate paid subscription is required for each site that uses a custom hostname.
- Use Laravel Cashier with Stripe. Offer monthly and annual billing.
- If a site no longer has an active subscription, its custom hostname stops serving the site; the published subdirectory version remains available.

### Planned block and styling improvements

Deliver these improvements after the existing launch features, in the order listed in the build plan:

- **Hero blocks:** background images, readable overlays, height options, an editable welcome label, and a second button. Background motion has three modes: normal scrolling, fixed relative to the viewport, and parallax moving at 0.5 times the page's scroll speed. Text and buttons retain normal scrolling. Respect reduced-motion preferences with a static image and ensure mobile usability.
- **Shared block styling:** section spacing, content width, heading sizes, and additional theme-aware backgrounds.
- **Image and text blocks:** image proportions, crop position, corner styles, captions, and optional buttons on text-bearing blocks.
- **Church information blocks:** service-time layout choices, plus contact addresses and directions links.
- **Site-wide styling:** font pairings, an editable accent color, and consistent button styles.

Keep existing sites' appearance unchanged until owners select the new options. All new content and styling follow the existing draft/Publish boundary and must match between the editor preview and published Blade pages. Exact presets belong in each feature spec, not this roadmap.

## 4. Data

Persist users; sites and their owners; pages and their order within each site; theme choices; draft and published site content per page; block types, order, styling, and fields; uploaded image references; structured service times; site metadata; custom-hostname connection and SSL status; and each site's subscription state in MySQL.

Persist a site deletion request until its billing, hostname, and uploaded-file cleanup is safely complete.

A site's published content must remain stable while its draft is edited. Ownership checks must prevent one account from changing another account's sites or assets. Domain routing must resolve a hostname to only its assigned site.

## 5. Technology and boundaries

- Build on the existing Laravel 13, Vue 3, TypeScript, Inertia, Tailwind 4, and Pest scaffold.
- Use one shared Laravel application and MySQL database for all church sites, with data scoped to the owning user and site.
- Use Vue/Inertia for signup, the account dashboard, and the block editor. Use Blade to render published church pages.
- Keep the editor preview faithful to the published page by sharing block data and theme styles with the Blade renderer.
- Host the Laravel origin on a Plesk-managed server with administrator access to configure the web server. Use Cloudflare for SaaS to manage customer hostnames and edge SSL, with a Cloudflare Worker forwarding customer requests to a fixed HTTPS Laravel origin and securely carrying the original hostname. Serve platform pages and subdirectory previews at `churchsite.app`.
- Store uploaded images in IONOS buckets.
- Use Laravel Cashier with Stripe for per-site subscriptions.
- Use the existing Laravel authentication foundation for self-service accounts.

**Technical validation:** Feature 7a establishes authenticated Worker forwarding and isolated public routing, with an operator-owned test hostname. Feature 7b adds hostname-to-site resolution, automated Cloudflare hostname/certificate checks, and paid-access enforcement. The manually configured Plesk alias test proves connectivity only; Worker transport must be proved separately before customer rollout. Cloudflare documents a fallback origin, a custom-hostname API, and separate hostname and certificate readiness checks. See [Cloudflare for SaaS setup](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/start/getting-started/) and [custom-hostname API calls](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/start/common-api-calls/).

## 6. Business model

Users may create and publish shareable subdirectory sites before paying. Each site needs its own subscription to go live on a custom hostname. Each site costs USD $15/month or $150/year, with no trial. Payment starts when the owner begins connecting a custom domain. Cashier uses Site as the billable model. Account deletion cancels renewals and occurs after the last site's paid subscription ends. Use Stripe-hosted Checkout and Billing Portal. See the [Cashier documentation](https://laravel.com/framework/docs/13.x/billing).

## 7. UI and experience

The builder should feel manageable for a busy user: a visible page preview, a clear block list, simple reordering, and a side panel with only the fields relevant to the selected block. Clearly distinguish draft changes from the published site. Domain setup should show the required DNS values and report connection and SSL progress in plain language.

## 8. Deployment and operations

Deploy the shared Laravel app on a Plesk-managed server with MySQL. Plesk administrator access is available for web-server configuration. Place Cloudflare for SaaS and a forwarding Worker in front of the Plesk origin for customer hostnames. The server also hosts other websites; use the existing application virtual host and its certificate instead of adding a Plesk alias/certificate for every customer. Authenticate forwarding metadata and isolate customer requests from account, editor, and billing routes. Configure `churchsite.app` for the product, account dashboard, and preview subdirectories. Configure mail, Stripe webhooks, access to IONOS buckets, and queues or scheduled checks if the domain workflow needs them.

The operator reports a live platform and a successful `test.timjossund.com/up` test using a manual Plesk alias, a Let's Encrypt certificate, and Cloudflare Full (strict). Plesk is Obsidian 18.0.81.1 on Debian 13.7, with nginx proxying to Apache and administrator/SSH access available. Worker deployment and its independent transport proof, the MySQL hosting arrangement, final deployment commands, and operational checks remain **TODOs**. The test subdomain is an operator-only infrastructure fixture; customer onboarding remains limited to `www` hostnames.

## 9. Scope and later work

**Initial launch:** block styling and expanded block options, multi-page site editing and publishing, themes, uploads, explicit publishing, and shareable non-indexed subdirectory pages.

**After the initial launch:** per-site billing, then self-service `www` customer hostnames with SSL.

**Deferred:** bare customer domains, multiple editors for one site, domain purchasing, and a visitor contact form. No user count, traffic target, or special compliance requirement has been established.
