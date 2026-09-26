<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
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
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @vite('resources/css/app.css')
</head>
<body class="font-sans antialiased">
    <main class="site-preview min-h-screen" data-theme="{{ $site['theme_key'] }}">
        <header class="border-b border-[var(--site-preview-border)] px-6 py-7 sm:px-10">
            <div class="mx-auto flex max-w-5xl items-center gap-3">
                @if (! empty($site['logo_media_asset_id']) && isset($mediaUrls[(string) $site['logo_media_asset_id']]))
                    <img
                        src="{{ $mediaUrls[(string) $site['logo_media_asset_id']] }}"
                        alt="{{ $logoAltText }}"
                        class="max-h-24 max-w-40 shrink-0 object-contain object-left"
                    >
                @endif
                <h1 class="min-w-0 font-serif text-2xl font-semibold tracking-tight break-words">
                    {{ $site['name'] }}
                </h1>
            </div>
            @if (count($blocks) > 0)
                <nav aria-label="Page sections" class="mx-auto mt-4 max-w-5xl">
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($blocks as $block)
                            <li>
                                <a href="#block-{{ $block['id'] }}" class="inline-flex min-h-10 items-center rounded-lg border border-[var(--site-preview-border)] bg-[var(--site-preview-soft)] px-3 py-2 text-sm font-semibold text-[var(--site-preview-accent)] underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]">
                                    {{ $block['navigation_label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @endif
        </header>

        @foreach ($blocks as $block)
            @php($content = $block['content'])
            <section id="block-{{ $block['id'] }}" class="border-b border-[var(--site-preview-border)] px-6 py-12 last:border-b-0 sm:px-10 {{ $block['type'] === 'about' ? 'bg-[var(--site-preview-soft)]' : '' }}">
                <div class="mx-auto max-w-5xl">
                    @switch($block['type'])
                        @case('hero')
                            <p class="text-xs font-bold tracking-[0.14em] text-[var(--site-preview-accent)] uppercase">Welcome</p>
                            <h2 class="mt-4 max-w-xl font-serif text-4xl leading-tight sm:text-5xl">{{ $block['heading'] }}</h2>
                            @if (! empty($content['body']))
                                <p class="mt-5 max-w-prose whitespace-pre-line text-[var(--site-preview-muted)]">{{ $content['body'] }}</p>
                            @endif
                            @if ($block['hero_href'])
                                <a href="{{ $block['hero_href'] }}" @if ($block['hero_external']) target="_blank" rel="noopener noreferrer" @endif class="mt-7 inline-flex min-h-11 items-center rounded-lg bg-[var(--site-preview-action)] px-5 py-2 text-sm font-semibold text-[var(--site-preview-action-ink)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--site-preview-accent)]">
                                    {{ $content['button_label'] }}
                                </a>
                            @endif
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
                                <ul class="mt-6 divide-y divide-[var(--site-preview-border)]">
                                    @foreach ($content['entries'] as $entry)
                                        <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3">
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
                            <h2 class="font-serif text-2xl">{{ $block['heading'] }}</h2>
                            @if (! empty($content['email']) || ! empty($content['phone']))
                                <div class="mt-5 flex flex-col items-start gap-3">
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
                                </div>
                            @endif
                            @break

                        @case('image')
                            @if ($block['image_url'])
                                <div class="overflow-hidden rounded-xl border border-[var(--site-preview-border)] bg-[var(--site-preview-soft)]">
                                    <img src="{{ $block['image_url'] }}" alt="{{ $block['image_alt'] }}" class="max-h-[32rem] w-full object-contain">
                                </div>
                            @endif
                            @break

                        @case('text_image')
                            <div class="grid gap-8 md:grid-cols-2 md:items-center">
                                <div>
                                    <h2 class="font-serif text-2xl">{{ $block['heading'] }}</h2>
                                    @if (! empty($content['body']))
                                        <p class="mt-4 whitespace-pre-line text-[var(--site-preview-muted)]">{{ $content['body'] }}</p>
                                    @endif
                                </div>
                                @if ($block['image_url'])
                                    <div class="overflow-hidden rounded-xl border border-[var(--site-preview-border)] bg-[var(--site-preview-soft)]">
                                        <img src="{{ $block['image_url'] }}" alt="{{ $block['image_alt'] }}" class="max-h-[32rem] min-h-56 w-full object-contain">
                                    </div>
                                @endif
                            </div>
                            @break

                        @case('video')
                            @if ($block['video_embed_url'])
                                <div class="mx-auto max-w-3xl overflow-hidden rounded-xl bg-[var(--site-preview-soft)]">
                                    <div class="aspect-video">
                                        <iframe src="{{ $block['video_embed_url'] }}" title="YouTube or Vimeo video" loading="lazy" allowfullscreen class="h-full w-full border-0"></iframe>
                                    </div>
                                </div>
                            @endif
                            @break

                        @case('plain_text')
                            @if (! empty($content['body']))
                                <p class="max-w-prose text-lg leading-relaxed whitespace-pre-line">{{ $content['body'] }}</p>
                            @endif
                            @break
                    @endswitch
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
