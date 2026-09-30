<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Support\CustomerPagePath;
use App\Support\EmbedFramePolicy;
use App\Support\GoogleCalendarUrl;
use App\Support\OpenStreetMapUrl;
use App\Support\PublishedAssets;
use App\Support\SiteAppearance;
use App\Support\VideoEmbedUrl;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublishedSiteController extends Controller
{
    public function show(string $slug, ?string $path = null): Response
    {
        return $this->renderSite($this->publishedSite($slug), $path);
    }

    public function renderSite(Site $site, ?string $path = null, ?string $hostname = null): Response
    {
        $slug = $site->slug;
        $pageUrl = fn (?string $pagePath): string => $hostname !== null
            ? 'https://'.$hostname.'/'.($pagePath ?? '')
            : ($pagePath === null ? route('sites.published.show', $slug) : route('sites.published.pages.show', [$slug, $pagePath]));
        $snapshot = $this->snapshot($site);
        $page = collect($snapshot['pages'])->first(fn (array $candidate): bool => $path === null
            ? $candidate['is_home'] === true
            : $candidate['is_home'] === false && $candidate['path'] === $path);
        abort_unless(is_array($page), 404);
        $media = collect($snapshot['media'])->keyBy('id');
        $mediaUrls = $media->mapWithKeys(fn (array $asset, int|string $id): array => [
            (string) $id => $hostname !== null ? 'https://'.$hostname.'/_media/'.$id : route('sites.published.media.show', [$slug, $id]),
        ]);
        $blockIds = collect($page['blocks'])
            ->pluck('id')
            ->filter(fn ($id): bool => is_int($id) && $id > 0)
            ->all();
        $blocks = collect($page['blocks'])->map(function (array $block) use ($blockIds, $media, $mediaUrls, $snapshot, $pageUrl): array {
            $content = is_array($block['content'] ?? null) ? $block['content'] : [];
            $type = is_string($block['type'] ?? null) ? $block['type'] : '';
            $id = is_int($block['id'] ?? null) ? $block['id'] : 0;
            $heading = trim(is_string($content['heading'] ?? null) ? $content['heading'] : '');
            $fallback = match ($type) {
                'hero' => 'Welcome to our church',
                'about' => 'Your introduction',
                'heading_text', 'text_image' => 'Your heading',
                'service_times' => 'Service times',
                'contact' => 'Contact us',
                'image' => 'Image',
                'video' => 'Video',
                'embed' => 'Calendar',
                'plain_text' => 'Text',
                default => 'Section',
            };
            $mediaId = $content['media_asset_id'] ?? null;
            $asset = (is_int($mediaId) || (is_string($mediaId) && ctype_digit($mediaId)))
                ? $media->get((int) $mediaId)
                : null;
            $heroLinks = [];
            foreach ([$content, $content['secondary_button'] ?? []] as $button) {
                $href = null;
                $external = false;
                if (in_array($type, ['hero', 'about', 'heading_text', 'plain_text', 'text_image'], true)
                    && is_array($button) && is_string($button['button_label'] ?? null) && trim($button['button_label']) !== '') {
                    $target = $button['target_block_id'] ?? null;
                    if (($button['link_type'] ?? null) === 'section' && is_numeric($target) && (int) $target !== $id && in_array((int) $target, $blockIds, true)) {
                        $href = '#block-'.(int) $target;
                    } elseif (($button['link_type'] ?? null) === 'page' && is_int($button['target_page_id'] ?? null)) {
                        $targetPage = collect($snapshot['pages'])->first(fn (array $candidate): bool => ($candidate['id'] ?? null) === $button['target_page_id']);
                        if ($targetPage !== null) {
                            $href = $pageUrl($targetPage['is_home'] ? null : $targetPage['path']);
                        }
                    } elseif (($button['link_type'] ?? null) === 'external') {
                        $href = $this->safeExternalUrl($button['external_url'] ?? null);
                        $external = $href !== null;
                    }
                }
                $heroLinks[] = ['href' => $href, 'external' => $external];
            }

            $email = is_string($content['email'] ?? null) ? $content['email'] : '';
            $phone = is_string($content['phone'] ?? null) ? $content['phone'] : '';
            $phoneHref = preg_match('/\A\+?[0-9().\- ]+\z/', $phone) === 1 && preg_match('/[0-9]/', $phone) === 1
                ? 'tel:'.preg_replace('/[().\- ]/', '', $phone)
                : null;
            $address = is_string($content['address'] ?? null) ? trim($content['address']) : '';
            $directionsHref = $address !== ''
                ? 'https://www.google.com/maps/dir/?api=1&destination='.urlencode($address)
                : null;
            $videoUrl = is_string($content['url'] ?? null) ? VideoEmbedUrl::from($content['url']) : null;
            $map = $content['map'] ?? null;
            $mapLinks = $type === 'contact' && is_array($map)
                && ($map['enabled'] ?? false) === true && is_string($map['url'] ?? null)
                ? OpenStreetMapUrl::from($map['url']) : null;

            return [
                'id' => $id,
                'type' => $type,
                'position' => $block['position'] ?? 0,
                'content' => $content,
                'heading' => $heading !== '' ? $heading : $fallback,
                'hero_href' => $heroLinks[0]['href'],
                'hero_external' => $heroLinks[0]['external'],
                'hero_secondary_href' => $heroLinks[1]['href'],
                'hero_secondary_external' => $heroLinks[1]['external'],
                'email_href' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
                    ? 'mailto:'.$email
                    : null,
                'phone_href' => $phoneHref,
                'address_text' => $address,
                'directions_href' => $directionsHref,
                'map_links' => $mapLinks,
                'image_url' => $asset === null ? null : $mediaUrls->get((string) $asset['id']),
                'image_alt' => $asset['alt_text'] ?? '',
                'video_embed_url' => $videoUrl,
                'calendar_embed_url' => $type === 'embed' && is_string($content['url'] ?? null) ? GoogleCalendarUrl::from($content['url']) : null,
            ];
        })->all();
        $siteData = $snapshot['site'];
        $siteData['appearance'] = SiteAppearance::normalize($siteData['appearance'] ?? null);
        $logoId = $siteData['logo_media_asset_id'] ?? null;
        $logo = is_int($logoId) ? $media->get($logoId) : null;
        $faviconId = $siteData['favicon_media_asset_id'] ?? null;
        $socialImageId = $page['social_image_id'] ?? null;
        $socialImage = is_int($socialImageId) ? $media->get($socialImageId) : null;
        $seoTitle = $page['seo_title'] ?? null;
        $seoDescription = $page['seo_description'] ?? null;

        $response = response()->view('sites.published', [
            'customDomain' => $hostname !== null,
            'publishedAssets' => $hostname !== null ? app(PublishedAssets::class)->manifest() : null,
            'site' => $siteData,
            'appearanceColors' => SiteAppearance::colors($siteData['appearance'], $siteData['theme_key']),
            'blocks' => $blocks,
            'pages' => array_map(fn (array $candidate): array => [
                'name' => $candidate['name'],
                'url' => $pageUrl($candidate['is_home'] ? null : $candidate['path']),
                'current' => $candidate['is_home'] ? $path === null : $candidate['path'] === $path,
            ], $snapshot['pages']),
            'mediaUrls' => $mediaUrls,
            'faviconUrl' => $faviconId === null ? null : $mediaUrls->get((string) $faviconId),
            'defaultIconBase' => $hostname === null ? '' : rtrim(config('app.url'), '/'),
            'logoAltText' => $logo['alt_text'] ?? '',
            'pageTitle' => is_string($seoTitle) && $seoTitle !== '' ? $seoTitle : ($page['is_home'] ? $siteData['name'] : $page['name'].' | '.$siteData['name']),
            'pageDescription' => is_string($seoDescription) && $seoDescription !== '' ? $seoDescription : null,
            'pageUrl' => $pageUrl($path),
            'socialImageUrl' => $socialImage === null
                ? null
                : $mediaUrls->get((string) $socialImage['id']),
        ])->header('Cache-Control', 'no-store');
        if ($hostname === null) {
            $response->header('X-Robots-Tag', 'noindex, nofollow');
        }

        EmbedFramePolicy::apply($response);

        return $response;
    }

    public function media(string $slug, int $mediaAsset): StreamedResponse
    {
        return $this->siteMedia($this->publishedSite($slug), $mediaAsset);
    }

    public function siteMedia(Site $site, int $mediaAsset): StreamedResponse
    {
        $snapshot = $this->snapshot($site);
        $references = [$snapshot['site']['logo_media_asset_id'] ?? null, $snapshot['site']['favicon_media_asset_id'] ?? null];
        foreach ($snapshot['pages'] as $page) {
            $references[] = $page['social_image_id'] ?? null;
            foreach ($page['blocks'] as $block) {
                $references[] = $block['content']['media_asset_id'] ?? null;
            }
        }
        $referenced = false;
        foreach ($references as $reference) {
            if ((is_int($reference) || (is_string($reference) && ctype_digit($reference))) && (int) $reference === $mediaAsset) {
                $referenced = true;
                break;
            }
        }
        abort_unless($referenced, 404);
        $asset = collect($snapshot['media'])->first(
            fn (array $candidate): bool => ($candidate['id'] ?? null) === $mediaAsset,
        );
        if (! is_array($asset)) {
            abort(404);
        }

        $storageKey = $asset['storage_key'] ?? null;
        $mimeType = $asset['mime_type'] ?? null;
        if (! is_string($storageKey)
            || ! str_starts_with($storageKey, "sites/{$site->id}/")
            || preg_match('#(?:^|/)\.\.?(?:/|$)|[\\\\\x00-\x1f]#', $storageKey) === 1
            || ! in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            abort(404);
        }

        $disk = Storage::disk('s3');
        if (! $disk->exists($storageKey)) {
            abort(404);
        }

        return $disk->response($storageKey, null, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ], 'inline');
    }

    public function validatePublication(Site $site): void
    {
        foreach ($this->snapshot($site)['pages'] as $page) {
            abort_unless($page['is_home'] || CustomerPagePath::valid($page['path']), 404);
        }
    }

    private function publishedSite(string $slug): Site
    {
        $site = Site::query()
            ->where('slug', $slug)
            ->whereNull('deletion_requested_at')
            ->whereNotNull('published_at')
            ->firstOrFail();

        return $site;
    }

    /** @return array{site: array<string, mixed>, pages: list<array{name: string, is_home: bool, path: string|null, blocks: array<array-key, array<string, mixed>>, ...}>, media: list<array<string, mixed>>} */
    private function snapshot(Site $site): array
    {
        abort_if($site->deletion_requested_at !== null, 404);
        $snapshot = $site->published_snapshot;
        abort_unless(is_array($snapshot) && in_array($snapshot['version'] ?? null, [1, 2], true), 404);
        if ($snapshot['version'] === 1) {
            // Adapt the frozen legacy content in memory; never read draft page rows here.
            $snapshot['pages'] = [[
                'id' => 0, 'name' => 'Home', 'is_home' => true, 'path' => null,
                'seo_title' => $snapshot['site']['seo_title'] ?? null,
                'seo_description' => $snapshot['site']['seo_description'] ?? null,
                'social_image_id' => $snapshot['site']['social_image_id'] ?? null,
                'blocks' => $snapshot['blocks'] ?? null,
            ]];
        }
        $rules = [
            'site' => ['required', 'array'],
            'site.name' => ['required', 'string'],
            'site.theme_key' => ['required', 'in:warm,clean,bold'],
            'site.footer' => ['required', 'array'],
            'site.footer.text' => ['present', 'string'],
            'site.logo_media_asset_id' => ['nullable', 'integer'],
            'site.favicon_media_asset_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'pages' => ['required', 'array', 'min:1'],
            'pages.*' => ['required', 'array'],
            'pages.*.id' => ['required', 'integer', 'distinct'],
            'pages.*.name' => ['required', 'string'],
            'pages.*.is_home' => ['required', 'boolean'],
            'pages.*.path' => ['present', 'nullable', 'string', 'max:100', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/'],
            'pages.*.seo_title' => ['nullable', 'string'],
            'pages.*.seo_description' => ['nullable', 'string'],
            'pages.*.social_image_id' => ['nullable', 'integer'],
            'pages.*.blocks' => ['present', 'array'],
            'pages.*.blocks.*' => ['required', 'array'],
            'pages.*.blocks.*.id' => ['required', 'integer'],
            'pages.*.blocks.*.type' => ['required', 'string'],
            'pages.*.blocks.*.content' => ['present', 'array'],
            'media' => ['present', 'array'],
            'media.*' => ['required', 'array'],
            'media.*.id' => ['required', 'integer', 'distinct'],
            'media.*.storage_key' => ['required', 'string'],
            'media.*.mime_type' => ['required', 'in:image/jpeg,image/png'],
            'media.*.alt_text' => ['nullable', 'string'],
        ];
        // Text and service-time fields are printed by Blade; reject malformed containers.
        foreach (['heading', 'body', 'button_label', 'email', 'phone'] as $field) {
            $rules['pages.*.blocks.*.content.'.$field] = ['nullable', 'string'];
        }
        $rules['pages.*.blocks.*.content.entries'] = ['sometimes', 'array'];
        foreach (['day', 'time', 'label'] as $field) {
            $rules['pages.*.blocks.*.content.entries.*.'.$field] = ['present', 'string'];
        }
        abort_if(Validator::make($snapshot, $rules)->fails(), 404);
        $faviconId = $snapshot['site']['favicon_media_asset_id'] ?? null;
        if ($faviconId !== null) {
            abort_unless(is_int($faviconId), 404);
            $favicon = null;
            foreach ($snapshot['media'] as $asset) {
                if ($asset['id'] === $faviconId) {
                    $favicon = $asset;
                    break;
                }
            }
            abort_unless(is_array($favicon)
                && $favicon['mime_type'] === 'image/png'
                && str_starts_with($favicon['storage_key'], "sites/{$site->id}/")
                && preg_match('#(?:^|/)\.\.?(?:/|$)|[\\\\\x00-\x1f]#', $favicon['storage_key']) !== 1, 404);
        }
        $pages = array_values($snapshot['pages']);
        abort_unless(count(array_filter($pages, fn (array $page): bool => $page['is_home'] === true)) === 1, 404);
        $paths = [];
        foreach ($pages as $page) {
            abort_unless(is_bool($page['is_home']) && ($page['is_home']
                ? $page['path'] === null
                : is_string($page['path']) && preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $page['path']) === 1), 404);
            if (! $page['is_home']) {
                abort_if(in_array($page['path'], $paths, true), 404);
                $paths[] = $page['path'];
            }
        }

        return ['site' => $snapshot['site'], 'pages' => $pages, 'media' => array_values($snapshot['media'])];
    }

    private function safeExternalUrl(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            return null;
        }

        $parts = parse_url($value);
        if ($parts === false
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        return $value;
    }
}
