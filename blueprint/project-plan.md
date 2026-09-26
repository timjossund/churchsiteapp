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

### Site editor

- New sites start blank.
- Users can add, remove, and freely reorder blocks.
- Selecting a block opens its fields in a side panel beside the page preview.
- Saved edits remain in draft until the user presses **Publish**.
- The currently published version stays visible while the user edits a draft.

### Blocks

- **Hero:** heading, supporting text, image, and button. The button can link to another block on the page or an external URL.
- **About:** an editable introduction to the church.
- **Plain text:** text without a heading.
- **Heading and text:** a heading with supporting text.
- **Service times:** structured entries with a day and time; an optional label can distinguish services.
- **Contact us:** email and phone details that produce `mailto:` and `tel:` links. No visitor contact form is planned for the first release.
- **Image:** an uploaded image displayed with rounded corners.
- **Text and image:** editable text and an uploaded image, with a choice of which side shows the image.
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
- Use Laravel Spark with Stripe. Offer monthly and annual billing.
- If a site no longer has an active subscription, its custom hostname stops serving the site; the published subdirectory version remains available.

## 4. Data

Persist users; sites and their owners; pages and their order within each site; theme choices; draft and published site content per page; block types, order, styling, and fields; uploaded image references; structured service times; site metadata; custom-hostname connection and SSL status; and each site's subscription state in MySQL.

A site's published content must remain stable while its draft is edited. Ownership checks must prevent one account from changing another account's sites or assets. Domain routing must resolve a hostname to only its assigned site.

## 5. Technology and boundaries

- Build on the existing Laravel 13, Vue 3, TypeScript, Inertia, Tailwind 4, and Pest scaffold.
- Use one shared Laravel application and MySQL database for all church sites, with data scoped to the owning user and site.
- Use Vue/Inertia for signup, the account dashboard, and the block editor. Use Blade to render published church pages.
- Keep the editor preview faithful to the published page by sharing block data and theme styles with the Blade renderer.
- Host the Laravel origin with xCloud. Use Cloudflare for SaaS to manage customer hostnames and edge SSL. Serve platform pages and subdirectory previews at `churchsite.app`.
- Store uploaded images in IONOS buckets.
- Use Laravel Spark with Stripe for per-site subscriptions.
- Use the existing Laravel authentication foundation for self-service accounts.

**Technical validation before the domain feature:** confirm that the xCloud origin can accept traffic for Cloudflare customer hostnames, that Laravel resolves each hostname to the correct published site, and that Cloudflare hostname and certificate status can be checked automatically. Cloudflare documents a fallback origin, a custom-hostname API, and separate hostname and certificate readiness checks. See [Cloudflare for SaaS setup](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/start/getting-started/) and [custom-hostname API calls](https://developers.cloudflare.com/cloudflare-for-platforms/cloudflare-for-saas/start/common-api-calls/).

## 6. Business model

Users may create and publish shareable subdirectory sites before paying. Each site needs its own subscription to go live on a custom hostname. Monthly and annual prices are **TODOs**. Spark's billing model must associate subscription access with an individual site. See the [Spark billable-model guide](https://spark.laravel.com/docs/spark-stripe/cookbook).

## 7. UI and experience

The builder should feel manageable for a busy user: a visible page preview, a clear block list, simple reordering, and a side panel with only the fields relevant to the selected block. Clearly distinguish draft changes from the published site. Domain setup should show the required DNS values and report connection and SSL progress in plain language.

## 8. Deployment and operations

Deploy the shared Laravel app on xCloud with MySQL. Place Cloudflare for SaaS in front of the xCloud origin for customer hostnames. Configure `churchsite.app` for the product, account dashboard, and preview subdirectories. Configure mail, Stripe webhooks, access to IONOS buckets, and queues or scheduled checks if the domain workflow needs them.

The MySQL hosting arrangement, exact deployment commands, origin host handling, Cloudflare configuration, and operational checks remain **TODOs** until the hosting flow is validated.

## 9. Scope and later work

**Initial launch:** block styling and expanded block options, multi-page site editing and publishing, themes, uploads, explicit publishing, and shareable non-indexed subdirectory pages.

**After the initial launch:** per-site billing, then self-service `www` customer hostnames with SSL.

**Deferred:** bare customer domains, multiple editors for one site, domain purchasing, and a visitor contact form. No user count, traffic target, or special compliance requirement has been established.
