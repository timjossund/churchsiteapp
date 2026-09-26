<?php

namespace App\Http\Controllers;

use App\Actions\BuildSitePublicationSnapshot;
use App\Http\Requests\SiteNameRequest;
use App\Http\Requests\SiteSettingsRequest;
use App\Models\Site;
use App\Models\SiteBlock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'sites' => $request->user()->sites()
                ->orderByDesc('id')
                ->get(['id', 'name']),
        ]);
    }

    public function store(SiteNameRequest $request): RedirectResponse
    {
        $site = $request->user()->sites()->create($request->validated());

        return to_route('sites.show', $site);
    }

    public function show(Request $request, int $site, BuildSitePublicationSnapshot $buildSnapshot): Response
    {
        $ownedSite = $request->user()->sites()->findOrFail($site);
        $blocks = $ownedSite->blocks()
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'type', 'position', 'content']);
        $mediaIds = $blocks
            ->map(fn (SiteBlock $block) => $block->content['media_asset_id'] ?? null)
            ->filter(fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->push($ownedSite->logo_media_asset_id)
            ->push($ownedSite->social_image_id)
            ->filter()
            ->unique()
            ->values();
        $mediaAssets = $ownedSite->mediaAssets()->whereIn('id', $mediaIds)->get()->keyBy('id');
        $logo = $ownedSite->logo_media_asset_id === null
            ? null
            : $mediaAssets->get($ownedSite->logo_media_asset_id);
        $publishedFingerprint = $ownedSite->published_snapshot['draft_fingerprint'] ?? null;
        $draftFingerprint = $ownedSite->published_at === null
            ? null
            : $buildSnapshot->fingerprint($buildSnapshot($ownedSite));
        $hasUnpublishedChanges = $ownedSite->published_at !== null
            && (! is_string($publishedFingerprint)
                || ! hash_equals($publishedFingerprint, $draftFingerprint ?? ''));

        return Inertia::render('Sites/Show', [
            'site' => array_merge($ownedSite->only('id', 'name', 'theme_key', 'footer', 'slug', 'seo_title', 'seo_description', 'published_at'), [
                'has_unpublished_changes' => $hasUnpublishedChanges,
                'published_url' => $ownedSite->published_at === null || $ownedSite->slug === null
                    ? null
                    : route('sites.published.show', ['slug' => $ownedSite->slug]),
                'logo' => $logo === null ? null : [
                    'media_asset_id' => $logo->id,
                    'url' => route('sites.media.show', [$ownedSite, $logo]),
                    'alt_text' => $logo->alt_text,
                ],
                'social_image' => $ownedSite->social_image_id === null || ! $mediaAssets->has($ownedSite->social_image_id)
                    ? null
                    : [
                        'media_asset_id' => $ownedSite->social_image_id,
                        'url' => route('sites.media.show', [$ownedSite, $ownedSite->social_image_id]),
                        'alt_text' => $mediaAssets->get($ownedSite->social_image_id)?->alt_text,
                    ],
            ]),
            'blocks' => $blocks->map(function (SiteBlock $block) use ($mediaAssets, $ownedSite): array {
                $assetId = $block->content['media_asset_id'] ?? null;
                $asset = is_int($assetId) || (is_string($assetId) && ctype_digit($assetId))
                    ? $mediaAssets->get((int) $assetId)
                    : null;

                return [
                    'id' => $block->id,
                    'type' => $block->type,
                    'position' => $block->position,
                    'content' => $block->content,
                    'media_url' => $asset === null ? null : route('sites.media.show', [$ownedSite, $asset]),
                    'alt_text' => $asset?->alt_text,
                ];
            }),
        ]);
    }

    public function update(SiteSettingsRequest $request, int $site): RedirectResponse
    {
        $settings = $request->validated();

        try {
            DB::transaction(function () use ($request, $site, $settings): void {
                $ownedSite = $request->user()->sites()->whereKey($site)->lockForUpdate()->firstOrFail();

                if (array_key_exists('slug', $settings)
                    && $ownedSite->published_at !== null
                    && $settings['slug'] !== $ownedSite->slug) {
                    throw ValidationException::withMessages([
                        'slug' => 'The address cannot change after the first publication.',
                    ]);
                }

                $ownedSite->update($settings);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $slug = $settings['slug'] ?? null;

            if (is_string($slug) && Site::query()
                ->where('slug', $slug)
                ->where('id', '<>', $site)
                ->exists()) {
                throw ValidationException::withMessages([
                    'slug' => 'This shareable address is already in use.',
                ]);
            }

            throw $exception;
        }

        return to_route('sites.show', $site);
    }
}
