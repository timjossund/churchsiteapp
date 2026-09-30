<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @unless ($customDomain)
        <meta name="robots" content="noindex, nofollow">
    @endunless
    <title>{{ $pageTitle }}</title>
    @if ($pageDescription)
        <meta name="description" content="{{ $pageDescription }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    @if ($pageDescription)
        <meta property="og:description" content="{{ $pageDescription }}">
    @endif
    @if ($socialImageUrl)
        <meta property="og:image" content="{{ $socialImageUrl }}">
    @endif
    <meta property="og:url" content="{{ $pageUrl }}">
    @unless ($customDomain)
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/published.ts'])
    @else
        @foreach ($publishedAssets['styles'] as $href)
            <link rel="stylesheet" href="{{ $href }}">
        @endforeach
        @foreach ($publishedAssets['scripts'] as $src)
            <script type="module" src="{{ $src }}"></script>
        @endforeach
    @endunless
</head>
<body class="font-sans antialiased">
    <main class="site-preview min-h-screen" style="{{ collect($appearanceColors)->map(fn ($value, $key) => $key.':'.$value)->implode(';') }}" data-theme="{{ $site['theme_key'] }}" data-font-pairing="{{ $site['appearance']['font_pairing'] }}" data-button-shape="{{ $site['appearance']['button_shape'] }}">
        <header data-site-header class="border-b border-[var(--site-preview-border)] px-6 py-7 sm:px-10">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-x-8 gap-y-4">
                <div class="flex max-w-[calc(100%-5rem)] min-w-0 flex-wrap items-center gap-3 sm:max-w-full">
                    @if (! empty($site['logo_media_asset_id']) && isset($mediaUrls[(string) $site['logo_media_asset_id']]))
                        <img
                            src="{{ $mediaUrls[(string) $site['logo_media_asset_id']] }}"
                            alt="{{ $logoAltText }}"
                            class="h-[75px] w-auto max-w-full shrink-0 object-contain object-left"
                        >
                    @endif
                    <h1 class="{{ $site['appearance']['show_site_title'] || empty($site['logo_media_asset_id']) || ! isset($mediaUrls[(string) $site['logo_media_asset_id']]) ? 'min-w-0 font-serif text-2xl font-semibold tracking-tight break-words' : 'sr-only' }}">
                        {{ $site['name'] }}
                    </h1>
                </div>
                <nav aria-label="Site pages" class="ml-auto hidden max-w-full min-w-0 sm:block">
                    <ul class="flex flex-wrap justify-end gap-x-6 gap-y-1">
                        @foreach ($pages as $navigationPage)
                            <li class="min-w-0 max-w-full">
                                <a href="{{ $navigationPage['url'] }}" @if ($navigationPage['current']) aria-current="page" @endif class="inline-flex min-h-10 max-w-full items-center py-2 text-sm font-semibold text-[var(--site-preview-accent)] underline-offset-4 hover:opacity-75 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)] {{ $navigationPage['current'] ? 'underline' : 'no-underline' }}">
                                    <span class="min-w-0 break-words">{{ $navigationPage['name'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
                <button type="button" data-menu-open aria-label="Open menu" aria-expanded="false" aria-controls="site-mobile-menu" class="site-menu-toggle ml-auto shrink-0 sm:hidden">
                             <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-6"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
                <dialog id="site-mobile-menu" data-menu-dialog aria-label="Site menu" class="site-menu-drawer">
                    <div class="mb-6 flex justify-end">
                        <button type="button" data-menu-close aria-label="Close menu" autofocus class="site-menu-toggle">
                               <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="size-6"><path d="m6 6 12 12M6 18 18 6" /></svg>
                        </button>
                    </div>
                    <nav aria-label="Mobile site pages">
                        <ul class="flex flex-col gap-2">
                            @foreach ($pages as $navigationPage)
                                <li class="min-w-0 max-w-full">
                                    <a href="{{ $navigationPage['url'] }}" @if ($navigationPage['current']) aria-current="page" @endif class="inline-flex min-h-10 max-w-full items-center py-2 text-sm font-semibold text-[var(--site-preview-accent)] underline-offset-4 hover:opacity-75 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)] {{ $navigationPage['current'] ? 'underline' : 'no-underline' }}">
                                        <span class="min-w-0 break-words">{{ $navigationPage['name'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                </dialog>
            </div>
        </header>

        @foreach ($blocks as $block)
            @php($content = $block['content'])
            @php($style = is_array($content['style'] ?? null) ? $content['style'] : [])
            @php($alignment = in_array($style['alignment'] ?? null, ['left', 'center'], true) ? $style['alignment'] : ($block['type'] === 'video' ? 'center' : 'left'))
            @php($background = in_array($style['background'] ?? null, ['theme', 'soft', 'accent', 'contrast'], true) ? $style['background'] : ($block['type'] === 'about' ? 'soft' : 'theme'))
            @php($imageOnLeft = $block['type'] === 'text_image' && ($style['layout'] ?? 'image_right') === 'image_left')
            @php($spacing = in_array($style['spacing'] ?? null, ['compact', 'current', 'spacious'], true) ? $style['spacing'] : 'current')
            @php($contentWidth = in_array($style['content_width'] ?? null, ['narrow', 'current', 'full'], true) ? $style['content_width'] : 'current')
            @php($headingSize = !in_array($block['type'], ['plain_text', 'image', 'video'], true) && in_array($style['heading_size'] ?? null, ['small', 'current', 'large'], true) ? $style['heading_size'] : 'current')
            @php($imageRatio = in_array($style['image_ratio'] ?? null, ['landscape', 'square', 'portrait'], true) ? $style['image_ratio'] : 'original')
            @php($cropPosition = in_array($style['crop_position'] ?? null, ['top', 'bottom'], true) ? $style['crop_position'] : 'center')
            @php($cornerStyle = in_array($style['corner_style'] ?? null, ['square', 'rounded'], true) ? $style['corner_style'] : 'current')
            <section id="block-{{ $block['id'] }}" data-block-type="{{ $block['type'] }}" data-spacing="{{ $spacing }}" data-content-width="{{ $contentWidth }}" data-heading-size="{{ $headingSize }}" data-background="{{ $background }}" @if ($block['type'] === 'hero') data-height="{{ $style['height'] ?? 'current' }}" data-overlay="{{ $style['overlay'] ?? 'medium' }}" data-motion="{{ $style['motion'] ?? 'normal' }}" data-text-background="{{ ($style['text_background'] ?? true) !== false ? 'true' : 'false' }}" @if ($block['image_url']) data-has-image="true" @endif @endif class="site-block {{ $block['type'] === 'hero' ? 'site-hero' : '' }} border-b border-[var(--site-preview-border)] px-6 py-12 last:border-b-0 sm:px-10 {{ $alignment === 'center' ? 'text-center' : '' }}">
                @if ($block['type'] === 'hero' && $block['image_url'])
                    <img src="{{ $block['image_url'] }}" alt="" class="site-hero-image">
                @endif
                <div class="site-block-content mx-auto w-full max-w-5xl {{ $block['type'] === 'hero' ? 'site-hero-content' : '' }}">
                    @switch($block['type'])
                        @case('hero')
                            @if (($content['welcome_label'] ?? 'Welcome') !== '')
                                <p class="text-xs font-bold tracking-[0.14em] text-[var(--site-preview-accent)] uppercase">{{ $content['welcome_label'] ?? 'Welcome' }}</p>
                            @endif
                            <h2 class="mt-4 max-w-xl font-serif text-4xl leading-tight sm:text-5xl {{ $alignment === 'center' ? 'mx-auto' : '' }}">{{ $block['heading'] }}</h2>
                            @if (! empty($content['body']))
                                <p class="mt-5 max-w-prose whitespace-pre-line text-[var(--site-preview-muted)] {{ $alignment === 'center' ? 'mx-auto' : '' }}">{{ $content['body'] }}</p>
                            @endif
                            @foreach ([false, true] as $secondary)
                                @php($href = $secondary ? $block['hero_secondary_href'] : $block['hero_href'])
                                @php($external = $secondary ? $block['hero_secondary_external'] : $block['hero_external'])
                                @if ($href)
                                    <a href="{{ $href }}" @if ($external) target="_blank" rel="noopener noreferrer" @endif class="site-hero-button mt-7 inline-flex min-h-11 items-center rounded-lg bg-[var(--site-preview-action)] px-5 py-2 text-sm font-semibold text-[var(--site-preview-action-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)] {{ $secondary ? 'ms-3' : '' }}">
                                        {{ $secondary ? $content['secondary_button']['button_label'] : $content['button_label'] }}
                                    </a>
                                @endif
                            @endforeach
                            @break

                        @case('about')
                            <p class="text-xs font-bold tracking-[0.14em] text-[var(--site-preview-accent)] uppercase">About us</p>
                            <h2 class="mt-3 font-serif text-3xl">{{ $block['heading'] }}</h2>
                            @if (! empty($content['body']))
                                <p class="mt-4 whitespace-pre-line text-[var(--site-preview-muted)]">{{ $content['body'] }}</p>
                            @endif
                            @break

                        @case('heading_text')
                            <h2 class="font-serif text-2xl">{{ $block['heading'] }}</h2>
                            @if (! empty($content['body']))
                                <p class="mt-4 whitespace-pre-line text-[var(--site-preview-muted)]">{{ $content['body'] }}</p>
                            @endif
                            @break

                        @case('service_times')
                            <h2 class="font-serif text-2xl">{{ $block['heading'] }}</h2>
                            @if (! empty($content['entries']))
                                @php($serviceLayout = ($style['layout'] ?? null) === 'grid' ? 'grid' : 'list')
                                <ul data-service-layout="{{ $serviceLayout }}" class="mt-6 {{ $serviceLayout === 'grid' ? 'grid grid-cols-1 gap-3 sm:grid-cols-2' : 'divide-y divide-[var(--site-preview-border)]' }} {{ $alignment === 'center' ? ($serviceLayout === 'grid' ? 'mx-auto max-w-4xl' : 'mx-auto max-w-2xl') : '' }}">
                                    @foreach ($content['entries'] as $entry)
                                        <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3 {{ $serviceLayout === 'grid' ? 'rounded-lg border border-[var(--site-preview-border)] px-3' : '' }}">
                                            <span class="font-semibold">{{ ucfirst($entry['day']) }}</span>
                                            <span class="text-[var(--site-preview-muted)]">
                                                @php($time = $entry['time'])
                                                @if (preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $time))
                                                    @php([$hour, $minute] = array_map('intval', explode(':', $time)))
                                                    {{ ($hour % 12 ?: 12).':'.str_pad((string) $minute, 2, '0', STR_PAD_LEFT).' '.($hour < 12 ? 'AM' : 'PM') }}
                                                @else
                                                    {{ $time }}
                                                @endif
                                            </span>
                                            @if (! empty($entry['label']))
                                                <span class="w-full text-sm text-[var(--site-preview-muted)]">{{ $entry['label'] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @break

                        @case('contact')
                            <div class="{{ $block['map_links'] !== null ? 'grid items-start gap-6 md:grid-cols-2' : '' }}">
                                <div class="min-w-0">
                                    <h2 class="font-serif text-2xl">{{ $block['heading'] }}</h2>
                                    @if (! empty($content['email']) || ! empty($content['phone']) || $block['address_text'] !== '')
                                        <div class="mt-5 flex flex-col gap-3 {{ $alignment === 'center' ? 'items-center' : 'items-start' }}">
                                            @if (! empty($content['email']))
                                                @if ($block['email_href'])
                                                    <a href="{{ $block['email_href'] }}" class="text-[var(--site-preview-accent)] underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]">{{ $content['email'] }}</a>
                                                @else
                                                    <span class="text-[var(--site-preview-muted)]">{{ $content['email'] }}</span>
                                                @endif
                                            @endif
                                            @if (! empty($content['phone']))
                                                @if ($block['phone_href'])
                                                    <a href="{{ $block['phone_href'] }}" class="text-[var(--site-preview-accent)] underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]">{{ $content['phone'] }}</a>
                                                @else
                                                    <span class="text-[var(--site-preview-muted)]">{{ $content['phone'] }}</span>
                                                @endif
                                            @endif
                                            @if ($block['address_text'] !== '')
                                                <address class="text-[var(--site-preview-muted)] break-words whitespace-pre-line not-italic">{{ $block['address_text'] }}</address>
                                                <a href="{{ $block['directions_href'] }}" target="_blank" rel="noopener noreferrer" class="text-[var(--site-preview-accent)] underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]">Get directions</a>
                                            @endif
                                           </div>
                                    @endif
                                </div>
                                @if ($block['map_links'] !== null)
                                    <div class="min-w-0 w-full space-y-2">
                                        <iframe src="{{ $block['map_links']['embed_url'] }}" title="Location map: {{ $block['heading'] }}" loading="lazy" class="aspect-video min-h-64 w-full rounded-xl border border-[var(--site-preview-border)]"></iframe>
                                        <p class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-[var(--site-preview-muted)]">
                                            <a href="{{ $block['map_links']['location_url'] }}" target="_blank" rel="noopener noreferrer" class="text-[var(--site-preview-accent)] underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]">View on OpenStreetMap</a>
                                            <span>Map data © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer" class="underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]">OpenStreetMap contributors</a></span>
                                        </p>
                                    </div>
                                @endif
                            </div>
                            @break

                        @case('image')
                            @if ($block['image_url'])
                                <figure class="{{ $alignment === 'center' ? 'mx-auto max-w-3xl' : '' }}">
                                    <div data-image-ratio="{{ $imageRatio }}" data-crop-position="{{ $cropPosition }}" data-corner-style="{{ $cornerStyle }}" class="site-image-frame overflow-hidden rounded-xl border-0 bg-[var(--site-preview-soft)]">
                                        <img src="{{ $block['image_url'] }}" alt="{{ $block['image_alt'] }}" class="max-h-[32rem] w-full object-contain">
                                    </div>
                                    @if (is_string($content['caption'] ?? null) && $content['caption'] !== '')
                                        <figcaption class="mt-3 text-sm whitespace-pre-line text-[var(--site-preview-muted)]">{{ $content['caption'] }}</figcaption>
                                    @endif
                                </figure>
                            @endif
                            @break

                        @case('text_image')
                            <div class="grid gap-8 md:grid-cols-2 md:items-center">
                                <div class="{{ $imageOnLeft ? 'md:order-2' : '' }}">
                                    <h2 class="font-serif text-2xl">{{ $block['heading'] }}</h2>
                                    @if (! empty($content['body']))
                                        <p class="mt-4 whitespace-pre-line text-[var(--site-preview-muted)]">{{ $content['body'] }}</p>
                                    @endif
                                    @if ($block['hero_href'])
                                        <a href="{{ $block['hero_href'] }}" @if ($block['hero_external']) target="_blank" rel="noopener noreferrer" @endif class="site-hero-button mt-7 inline-flex min-h-11 items-center rounded-lg bg-[var(--site-preview-action)] px-5 py-2 text-sm font-semibold text-[var(--site-preview-action-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]">{{ $content['button_label'] }}</a>
                                    @endif
                                   </div>
                                @if ($block['image_url'])
                                    <figure class="{{ $imageOnLeft ? 'md:order-1' : 'md:order-2' }}">
                                        <div data-image-ratio="{{ $imageRatio }}" data-crop-position="{{ $cropPosition }}" data-corner-style="{{ $cornerStyle }}" class="site-image-frame overflow-hidden rounded-xl border-0 bg-[var(--site-preview-soft)]">
                                            <img src="{{ $block['image_url'] }}" alt="{{ $block['image_alt'] }}" class="max-h-[32rem] min-h-56 w-full object-contain">
                                        </div>
                                        @if (is_string($content['caption'] ?? null) && $content['caption'] !== '')
                                            <figcaption class="mt-3 text-sm whitespace-pre-line text-[var(--site-preview-muted)]">{{ $content['caption'] }}</figcaption>
                                        @endif
                                    </figure>
                                @endif
                               </div>
                            @break

                        @case('embed')
                            <h2 class="font-serif text-3xl font-semibold tracking-tight break-words">{{ $block['heading'] }}</h2>
                            @if ($block['calendar_embed_url'])
                                <iframe src="{{ $block['calendar_embed_url'] }}" title="Google Calendar: {{ $block['heading'] }}" sandbox="allow-scripts allow-same-origin" referrerpolicy="no-referrer" loading="lazy" allow="camera 'none'; microphone 'none'; geolocation 'none'; payment 'none'" class="mt-6 h-[600px] w-full rounded-xl border-0"></iframe>
                                <a href="{{ $block['calendar_embed_url'] }}" target="_blank" rel="noopener noreferrer" aria-label="Open {{ $block['heading'] }} in Google Calendar" class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold text-[var(--site-preview-accent)] underline underline-offset-4">Open calendar</a>
                                <p class="text-sm text-[var(--site-preview-muted)]">If the calendar does not appear, open it in Google Calendar.</p>
                            @else
                                <p class="mt-6 text-sm text-[var(--site-preview-muted)]">Calendar is not available.</p>
                            @endif
                            @break

                        @case('video')
                            @if ($block['video_embed_url'])
                                <div class="{{ $alignment === 'center' ? 'mx-auto' : 'mr-auto' }} max-w-3xl overflow-hidden rounded-xl bg-[var(--site-preview-soft)]">
                                    <div class="aspect-video">
                                        <iframe src="{{ $block['video_embed_url'] }}" title="YouTube or Vimeo video" loading="lazy" allowfullscreen class="h-full w-full border-0"></iframe>
                                       </div>
                                   </div>
                            @endif
                            @break

                        @case('plain_text')
                            @if (! empty($content['body']))
                                <p class="max-w-prose text-lg leading-relaxed whitespace-pre-line {{ $alignment === 'center' ? 'mx-auto' : '' }}">{{ $content['body'] }}</p>
                            @endif
                            @break
                    @endswitch
                    @if (in_array($block['type'], ['about', 'heading_text', 'plain_text'], true) && $block['hero_href'])
                        <a href="{{ $block['hero_href'] }}" @if ($block['hero_external']) target="_blank" rel="noopener noreferrer" @endif class="site-hero-button mt-7 inline-flex min-h-11 items-center rounded-lg bg-[var(--site-preview-action)] px-5 py-2 text-sm font-semibold text-[var(--site-preview-action-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]">{{ $content['button_label'] }}</a>
                    @endif
                </div>
            </section>
        @endforeach

        @if (! empty($site['footer']['text']))
            <footer class="border-t border-[var(--site-preview-border)] px-6 py-5 text-sm text-[var(--site-preview-muted)] sm:px-10">
                <div class="mx-auto max-w-5xl">{{ $site['footer']['text'] }}</div>
            </footer>
        @endif
    </main>
</body>
</html>
