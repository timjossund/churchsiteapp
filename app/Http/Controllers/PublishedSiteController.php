<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Support\VideoEmbedUrl;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublishedSiteController extends Controller
{
    public function show(string $slug): Response
    {
        $site = $this->publishedSite($slug);
        $snapshot = $this->snapshot($site);
        $media = collect($snapshot['media'])->keyBy('id');
        $mediaUrls = $media->mapWithKeys(fn (array $asset, int|string $id): array => [
            (string) $id => route('sites.published.media.show', [$slug, $id]),
        ]);
        $blockIds = collect($snapshot['blocks'])
            ->pluck('id')
            ->filter(fn ($id): bool => is_int($id) && $id > 0)
            ->all();
        $blocks = collect($snapshot['blocks'])->map(function (array $block) use ($blockIds, $media, $mediaUrls): array {
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
                'navigation_label' => $heading !== '' ? $heading : $fallback,
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
        $socialImageId = $siteData['social_image_id'] ?? null;
        $socialImage = is_int($socialImageId) ? $media->get($socialImageId) : null;
        $seoTitle = $siteData['seo_title'] ?? null;
        $seoDescription = $siteData['seo_description'] ?? null;

        return response()->view('sites.published', [
            'site' => $siteData,
            'blocks' => $blocks,
            'mediaUrls' => $mediaUrls,
            'logoAltText' => $logo['alt_text'] ?? '',
            'pageTitle' => is_string($seoTitle) && $seoTitle !== '' ? $seoTitle : $siteData['name'],
            'pageDescription' => is_string($seoDescription) && $seoDescription !== '' ? $seoDescription : null,
            'pageUrl' => route('sites.published.show', $slug),
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

    /**
     * @return array{
     *     version: 1,
     *     site: array<string, mixed>,
     *     blocks: list<array<string, mixed>>,
     *     media: list<array<string, mixed>>
     * }
     */
    private function snapshot(Site $site): array
    {
        $snapshot = $site->published_snapshot;
        abort_unless(
            is_array($snapshot)
                && ($snapshot['version'] ?? null) === 1
                && is_array($snapshot['site'] ?? null)
                && is_array($snapshot['blocks'] ?? null)
                && is_array($snapshot['media'] ?? null),
            404,
        );

        $blocks = array_values(array_filter($snapshot['blocks'], 'is_array'));
        $media = array_values(array_filter($snapshot['media'], 'is_array'));
        abort_unless(count($blocks) === count($snapshot['blocks']) && count($media) === count($snapshot['media']), 404);

        return [
            'version' => 1,
            'site' => $snapshot['site'],
            'blocks' => $blocks,
            'media' => $media,
        ];
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
