<?php

namespace App\Http\Controllers;

use App\Http\Requests\SiteNameRequest;
use App\Http\Requests\SiteSettingsRequest;
use App\Models\SiteBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function show(Request $request, int $site): Response
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
            ->filter()
            ->unique()
            ->values();
        $mediaAssets = $ownedSite->mediaAssets()->whereIn('id', $mediaIds)->get()->keyBy('id');
        $logo = $ownedSite->logo_media_asset_id === null
            ? null
            : $mediaAssets->get($ownedSite->logo_media_asset_id);

        return Inertia::render('Sites/Show', [
            'site' => array_merge($ownedSite->only('id', 'name', 'theme_key', 'footer'), [
                'logo' => $logo === null ? null : [
                    'media_asset_id' => $logo->id,
                    'url' => route('sites.media.show', [$ownedSite, $logo]),
                    'alt_text' => $logo->alt_text,
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
        $ownedSite = $request->user()->sites()->findOrFail($site);
        $ownedSite->update($request->validated());

        return to_route('sites.show', $ownedSite);
    }
}
