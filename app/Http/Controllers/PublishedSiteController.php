<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Support\VideoEmbedUrl;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublishedSiteController extends Controller
{
    public function show(string $slug, ?string $path = null): Response
    {
        $site = $this->publishedSite($slug);
        $snapshot = $this->snapshot($site);
        $page = collect($snapshot['pages'])->first(fn (array $candidate): bool => $path === null
            ? $candidate['is_home'] === true
            : $candidate['is_home'] === false && $candidate['path'] === $path);
        abort_unless(is_array($page), 404);
        $media = collect($snapshot['media'])->keyBy('id');
        $mediaUrls = $media->mapWithKeys(fn (array $asset, int|string $id): array => [
            (string) $id => route('sites.published.media.show', [$slug, $id]),
        ]);
        $blockIds = collect($page['blocks'])
            ->pluck('id')
            ->filter(fn ($id): bool => is_int($id) && $id > 0)
            ->all();
        $blocks = collect($page['blocks'])->map(function (array $block) use ($blockIds, $media, $mediaUrls): array {
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
                'plain_text' => 'Text',
                default => 'Section',
            };
            $mediaId = $content['media_asset_id'] ?? null;
            $asset = (is_int($mediaId) || (is_string($mediaId) && ctype_digit($mediaId)))
                ? $media->get((int) $mediaId)
                : null;
            $target = $content['target_block_id'] ?? null;
            $linkType = $content['link_type'] ?? null;
            $heroHref = null;
            $heroExternal = false;
            if ($type === 'hero' && trim((string) ($content['button_label'] ?? '')) !== '') {
                if ($linkType === 'section'
                    && is_numeric($target)
                    && (int) $target !== $id
                    && in_array((int) $target, $blockIds, true)) {
                    $heroHref = '#block-'.(int) $target;
                } elseif ($linkType === 'external') {
                    $heroHref = $this->safeExternalUrl($content['external_url'] ?? null);
                    $heroExternal = $heroHref !== null;
                }
            }

            $email = is_string($content['email'] ?? null) ? $content['email'] : '';
            $phone = is_string($content['phone'] ?? null) ? $content['phone'] : '';
            $phoneHref = preg_match('/\A\+?[0-9().\- ]+\z/', $phone) === 1 && preg_match('/[0-9]/', $phone) === 1
                ? 'tel:'.preg_replace('/[().\- ]/', '', $phone)
                : null;
            $videoUrl = is_string($content['url'] ?? null) ? VideoEmbedUrl::from($content['url']) : null;

            return [
                'id' => $id,
                'type' => $type,
                'position' => $block['position'] ?? 0,
                'content' => $content,
                'heading' => $heading !== '' ? $heading : $fallback,
                'hero_href' => $heroHref,
                'hero_external' => $heroExternal,
                'email_href' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
                    ? 'mailto:'.$email
                    : null,
                'phone_href' => $phoneHref,
                'image_url' => $asset === null ? null : $mediaUrls->get((string) $asset['id']),
                'image_alt' => $asset['alt_text'] ?? '',
                'video_embed_url' => $videoUrl,
            ];
        })->all();
        $siteData = $snapshot['site'];
        $logoId = $siteData['logo_media_asset_id'] ?? null;
        $logo = is_int($logoId) ? $media->get($logoId) : null;
        $socialImageId = $page['social_image_id'] ?? null;
        $socialImage = is_int($socialImageId) ? $media->get($socialImageId) : null;
        $seoTitle = $page['seo_title'] ?? null;
        $seoDescription = $page['seo_description'] ?? null;

        return response()->view('sites.published', [
            'site' => $siteData,
            'blocks' => $blocks,
            'pages' => array_map(fn (array $candidate): array => [
                'name' => $candidate['name'],
                'url' => $candidate['is_home'] ? route('sites.published.show', $slug) : route('sites.published.pages.show', [$slug, $candidate['path']]),
                'current' => $candidate['is_home'] ? $path === null : $candidate['path'] === $path,
            ], $snapshot['pages']),
            'mediaUrls' => $mediaUrls,
            'logoAltText' => $logo['alt_text'] ?? '',
            'pageTitle' => is_string($seoTitle) && $seoTitle !== '' ? $seoTitle : ($page['is_home'] ? $siteData['name'] : $page['name'].' | '.$siteData['name']),
            'pageDescription' => is_string($seoDescription) && $seoDescription !== '' ? $seoDescription : null,
            'pageUrl' => $path === null ? route('sites.published.show', $slug) : route('sites.published.pages.show', [$slug, $path]),
            'socialImageUrl' => $socialImage === null
                ? null
                : $mediaUrls->get((string) $socialImage['id']),
        ])->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store');
    }

    public function media(string $slug, int $mediaAsset): StreamedResponse
    {
        $site = $this->publishedSite($slug);
        $snapshot = $this->snapshot($site);
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

    private function publishedSite(string $slug): Site
    {
        $site = Site::query()
            ->where('slug', $slug)
            ->whereNotNull('published_at')
            ->firstOrFail();

        return $site;
    }

    /** @return array{site: array<string, mixed>, pages: list<array{name: string, is_home: bool, path: string|null, blocks: array<array-key, array<string, mixed>>, ...}>, media: list<array<string, mixed>>} */
    private function snapshot(Site $site): array
    {
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
