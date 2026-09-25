<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteImageRequest;
use App\Http\Requests\UpdateMediaAssetRequest;
use App\Models\MediaAsset;
use App\Models\Site;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SiteMediaController extends Controller
{
    public function uploadBlockImage(StoreSiteImageRequest $request, int $site, int $block): RedirectResponse
    {
        $file = $this->validatedImage($request);
        $this->uploadAndAssign($request, $site, $file, $this->validatedAltText($request), function (Site $ownedSite, MediaAsset $asset) use ($block): void {
            $ownedBlock = $ownedSite->blocks()->whereKey($block)->lockForUpdate()->firstOrFail();

            if (! in_array($ownedBlock->type, ['image', 'text_image'], true)) {
                abort(404);
            }

            $content = $ownedBlock->content;
            $content['media_asset_id'] = $asset->id;
            $ownedBlock->update(['content' => $content]);
        });

        return to_route('sites.show', $site);
    }

    public function uploadLogo(StoreSiteImageRequest $request, int $site): RedirectResponse
    {
        $file = $this->validatedImage($request);
        $this->uploadAndAssign($request, $site, $file, $this->validatedAltText($request), function (Site $ownedSite, MediaAsset $asset): void {
            $ownedSite->update(['logo_media_asset_id' => $asset->id]);
        });

        return to_route('sites.show', $site);
    }

    public function clearLogo(Request $request, int $site): RedirectResponse
    {
        $request->user()->sites()->whereKey($site)->firstOrFail()->update(['logo_media_asset_id' => null]);

        return to_route('sites.show', $site);
    }

    public function updateAltText(UpdateMediaAssetRequest $request, int $site, int $mediaAsset): RedirectResponse
    {
        $request->ownedAsset()->update($request->validated());

        return to_route('sites.show', $site);
    }

    public function show(Request $request, int $site, int $mediaAsset): StreamedResponse
    {
        $asset = $request->user()->sites()->findOrFail($site)
            ->mediaAssets()->findOrFail($mediaAsset);

        return Storage::disk('s3')->response($asset->storage_key, null, [
            'Content-Type' => $asset->mime_type,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    private function validatedImage(StoreSiteImageRequest $request): UploadedFile
    {
        $image = $request->validated('image');

        if (! $image instanceof UploadedFile) {
            throw new \LogicException('A validated image upload must be an uploaded file.');
        }

        return $image;
    }

    private function validatedAltText(StoreSiteImageRequest $request): ?string
    {
        $altText = $request->validated('alt_text');

        if ($altText !== null && ! is_string($altText)) {
            throw new \LogicException('Validated image alt text must be a string or null.');
        }

        return $altText;
    }

    /** @param  callable(Site, MediaAsset): void  $assign */
    private function uploadAndAssign(StoreSiteImageRequest $request, int $siteId, UploadedFile $file, ?string $altText, callable $assign): MediaAsset
    {
        $ownedSite = $request->user()->sites()->findOrFail($siteId);
        $key = "sites/{$ownedSite->id}/".Str::uuid();
        $disk = Storage::disk('s3');

        try {
            $storedPath = $disk->putFileAs("sites/{$ownedSite->id}", $file, basename($key), ['visibility' => 'private']);
        } catch (FilesystemException) {
            $this->deleteNewObject($disk, $key);
            throw ValidationException::withMessages([
                'image' => 'The image could not be saved. Try again.',
            ]);
        }

        if ($storedPath === false) {
            $this->deleteNewObject($disk, $key);
            throw ValidationException::withMessages([
                'image' => 'The image could not be saved. Try again.',
            ]);
        }

        try {
            return DB::transaction(function () use ($request, $siteId, $key, $file, $altText, $assign): MediaAsset {
                $lockedSite = $request->user()->sites()->whereKey($siteId)->lockForUpdate()->firstOrFail();
                $asset = $lockedSite->mediaAssets()->create([
                    'storage_key' => $key,
                    'mime_type' => $file->getMimeType(),
                    'alt_text' => $altText,
                ]);
                $assign($lockedSite, $asset);

                return $asset;
            });
        } catch (Throwable $exception) {
            $this->deleteNewObject($disk, $key);
            throw $exception;
        }
    }

    private function deleteNewObject(FilesystemAdapter $disk, string $key): void
    {
        try {
            if (! $disk->delete($key)) {
                report(new \RuntimeException('A newly uploaded site image could not be cleaned up.'));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
