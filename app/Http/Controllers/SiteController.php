<?php

namespace App\Http\Controllers;

use App\Actions\BuildSitePublicationSnapshot;
use App\Actions\SiteBillingSummary;
use App\Http\Requests\SiteNameRequest;
use App\Http\Requests\SiteSettingsRequest;
use App\Models\Site;
use App\Models\SiteBlock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
        $site = DB::transaction(fn () => $request->user()->sites()->create($request->validated()));

        return to_route('sites.show', $site);
    }

    public function show(Request $request, int $site, BuildSitePublicationSnapshot $buildSnapshot, ?int $page = null): Response
    {
        $ownedSite = $request->user()->sites()->findOrFail($site);
        $selectedPage = $page === null ? null : $ownedSite->editorPage($page);
        $blocks = $selectedPage?->blocks()
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'type', 'position', 'content']) ?? collect();
        $mediaIds = $blocks->toBase()
            ->map(fn (SiteBlock $block) => $block->content['media_asset_id'] ?? null)
            ->filter(fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->push($ownedSite->logo_media_asset_id)
            ->push($selectedPage?->social_image_id)
            ->filter()
            ->unique()
            ->values();
        $mediaAssets = $ownedSite->mediaAssets()->whereIn('id', $mediaIds)->get()->keyBy('id');
        $logo = $ownedSite->logo_media_asset_id === null
            ? null
            : $mediaAssets->get($ownedSite->logo_media_asset_id);
        $snapshot = $ownedSite->published_snapshot;
        $hasUnpublishedChanges = false;
        if ($ownedSite->published_at !== null) {
            // Legacy publications await a whole-site publish; v1 and v2 hashes are not comparable.
            $hasUnpublishedChanges = ($snapshot['version'] ?? null) !== 2;
            if (! $hasUnpublishedChanges) {
                try {
                    $fingerprint = $buildSnapshot->fingerprint($buildSnapshot($ownedSite));
                    $publishedFingerprint = $snapshot['draft_fingerprint'] ?? null;
                    $hasUnpublishedChanges = ! is_string($publishedFingerprint) || ! hash_equals($publishedFingerprint, $fingerprint);
                } catch (ValidationException) {
                    // Invalid draft references must not prevent the owner from opening the editor to repair them.
                    $hasUnpublishedChanges = true;
                }
            }
        }
        $pagePublishedUrl = null;
        if ($selectedPage !== null && $ownedSite->published_at !== null && $ownedSite->slug !== null) {
            if (($snapshot['version'] ?? null) === 1 && $selectedPage->is_home) {
                $pagePublishedUrl = route('sites.published.show', $ownedSite->slug);
            } elseif (($snapshot['version'] ?? null) === 2 && is_array($snapshot['pages'] ?? null)) {
                foreach ($snapshot['pages'] as $publishedPage) {
                    if (! is_array($publishedPage) || ($publishedPage['id'] ?? null) !== $selectedPage->id) {
                        continue;
                    }
                    if (($publishedPage['is_home'] ?? null) === true) {
                        $pagePublishedUrl = route('sites.published.show', $ownedSite->slug);
                    } elseif (is_string($publishedPage['path'] ?? null) && preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $publishedPage['path']) === 1) {
                        $pagePublishedUrl = route('sites.published.pages.show', [$ownedSite->slug, $publishedPage['path']]);
                    }
                    break;
                }
            }
        }

        return Inertia::render($selectedPage === null ? 'Sites/Settings' : 'Sites/Show', array_merge(
            $selectedPage === null
                ? ['billing' => app(SiteBillingSummary::class)->handle($ownedSite), 'pages' => $ownedSite->pages()->orderBy('position')->orderBy('id')->get(['id', 'name', 'position', 'is_home'])]
                : ['selected_page' => array_merge($selectedPage->only('id', 'name', 'position', 'is_home', 'path', 'seo_title', 'seo_description'), [
                    'published_url' => $pagePublishedUrl,
                    'default_title' => $selectedPage->is_home ? $ownedSite->name : $selectedPage->name.' | '.$ownedSite->name,
                    'social_image' => $selectedPage->social_image_id === null || ! $mediaAssets->has($selectedPage->social_image_id) ? null : [
                        'media_asset_id' => $selectedPage->social_image_id,
                        'url' => route('sites.media.show', [$ownedSite, $selectedPage->social_image_id]),
                        'alt_text' => $mediaAssets->get($selectedPage->social_image_id)?->alt_text,
                    ],
                ])],
            [
                'site' => array_merge($ownedSite->only('id', 'name', 'theme_key', 'footer', 'slug', 'published_at'), [
                    'has_unpublished_changes' => $hasUnpublishedChanges,
                    'published_url' => $ownedSite->published_at === null || $ownedSite->slug === null
                        ? null
                        : route('sites.published.show', ['slug' => $ownedSite->slug]),
                    'logo' => $logo === null ? null : [
                        'media_asset_id' => $logo->id,
                        'url' => route('sites.media.show', [$ownedSite, $logo]),
                        'alt_text' => $logo->alt_text,
                    ],

                ]),
            ],
            $selectedPage === null ? [] : [
                'navigation_pages' => $ownedSite->pages()->orderBy('position')->orderBy('id')->get(['id', 'name']),
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
            ]));
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

                $ownedSite->update(Arr::except($settings, ['seo_title', 'seo_description']));
                $ownedSite->homePage()->firstOrFail()->update(Arr::only($settings, ['seo_title', 'seo_description']));
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
