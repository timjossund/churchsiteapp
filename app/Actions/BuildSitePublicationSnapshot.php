<?php

namespace App\Actions;

use App\Models\Site;
use App\Models\SiteBlock;
use Illuminate\Validation\ValidationException;

class BuildSitePublicationSnapshot
{
    /** @return array<string, mixed> */
    public function __invoke(Site $site): array
    {
        $blocks = $site->blocks()
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'type', 'position', 'content']);

        $mediaIds = collect([$site->logo_media_asset_id, $site->social_image_id]);
        foreach ($blocks as $block) {
            $mediaId = $block->content['media_asset_id'] ?? null;
            if ($mediaId === null) {
                continue;
            }

            if (! is_int($mediaId) && (! is_string($mediaId) || ! ctype_digit($mediaId))) {
                throw ValidationException::withMessages([
                    'publish' => 'A page image is no longer available. Review the draft and try again.',
                ]);
            }

            $mediaIds->push((int) $mediaId);
        }

        $mediaIds = $mediaIds
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $mediaAssets = $site->mediaAssets()
            ->whereIn('id', $mediaIds)
            ->orderBy('id')
            ->get(['id', 'storage_key', 'mime_type', 'alt_text']);

        if ($mediaAssets->count() !== $mediaIds->count()) {
            throw ValidationException::withMessages([
                'publish' => 'A page image is no longer available. Review the draft and try again.',
            ]);
        }

        $media = $mediaAssets->map(fn ($asset): array => [
            'id' => $asset->id,
            'storage_key' => $asset->storage_key,
            'mime_type' => $asset->mime_type,
            'alt_text' => $asset->alt_text,
        ])->all();

        return [
            'version' => 1,
            'site' => [
                'name' => $site->name,
                'slug' => $site->slug,
                'theme_key' => $site->theme_key,
                'footer' => $site->footer,
                'seo_title' => $site->seo_title,
                'seo_description' => $site->seo_description,
                'logo_media_asset_id' => $site->logo_media_asset_id,
                'social_image_id' => $site->social_image_id,
            ],
            'blocks' => $blocks->map(fn (SiteBlock $block): array => [
                'id' => $block->id,
                'type' => $block->type,
                'position' => $block->position,
                'content' => $block->content,
            ])->all(),
            'media' => $media,
        ];
    }

    /** @param array<string, mixed> $snapshot */
    public function fingerprint(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
