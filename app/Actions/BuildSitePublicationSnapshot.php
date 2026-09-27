<?php

namespace App\Actions;

use App\Models\Site;
use App\Models\SiteBlock;
use App\Models\SitePage;
use Illuminate\Validation\ValidationException;

class BuildSitePublicationSnapshot
{
    /** @return array<string, mixed> */
    public function __invoke(Site $site): array
    {
        $pages = $site->pages()->orderBy('position')->orderBy('id')
            ->with(['blocks' => fn ($query) => $query->orderBy('position')->orderBy('id')])->get();
        if ($pages->where('is_home', true)->count() !== 1) {
            throw ValidationException::withMessages(['publish' => 'The site must have exactly one Home page.']);
        }
        $mediaIds = collect([$site->logo_media_asset_id]);
        foreach ($pages as $page) {
            if ($page->is_home ? $page->path !== null : ! is_string($page->path)
                || preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $page->path) !== 1 || strlen($page->path) > 100) {
                throw ValidationException::withMessages(['publish' => 'Review the page addresses before publishing.']);
            }
            $mediaIds->push($page->social_image_id);
            foreach ($page->blocks as $block) {
                $mediaId = $block->content['media_asset_id'] ?? null;
                if ($mediaId === null) {
                    continue;
                }
                if ((! is_int($mediaId) && (! is_string($mediaId) || ! ctype_digit($mediaId))) || (int) $mediaId < 1) {
                    throw ValidationException::withMessages(['publish' => 'A page image is no longer available. Review the draft and try again.']);
                }
                $mediaIds->push((int) $mediaId);
            }
        }
        $mediaIds = $mediaIds->filter()->map(fn ($id): int => (int) $id)->unique()->values();
        $mediaAssets = $site->mediaAssets()->whereIn('id', $mediaIds)->orderBy('id')
            ->get(['id', 'storage_key', 'mime_type', 'alt_text']);
        if ($mediaAssets->count() !== $mediaIds->count()
            || $mediaAssets->contains(fn ($asset): bool => ! str_starts_with($asset->storage_key, "sites/{$site->id}/")
                || ! in_array($asset->mime_type, ['image/jpeg', 'image/png'], true))) {
            throw ValidationException::withMessages(['publish' => 'A page image is no longer available. Review the draft and try again.']);
        }

        return [
            'version' => 2,
            'site' => $site->only('name', 'slug', 'theme_key', 'footer', 'logo_media_asset_id'),
            'pages' => $pages->map(fn (SitePage $page): array => array_merge(
                $page->only('id', 'name', 'position', 'is_home', 'path', 'seo_title', 'seo_description', 'social_image_id'),
                ['blocks' => $page->blocks->map(fn (SiteBlock $block): array => $block->only('id', 'type', 'position', 'content'))->all()],
            ))->all(),
            'media' => $mediaAssets->map(fn ($asset): array => $asset->only('id', 'storage_key', 'mime_type', 'alt_text'))->all(),
        ];
    }

    /** @param array<string, mixed> $snapshot */
    public function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
