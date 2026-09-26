<?php

namespace App\Http\Controllers;

use App\Actions\BuildSitePublicationSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SitePublishingController extends Controller
{
    public function publish(Request $request, int $site, BuildSitePublicationSnapshot $buildSnapshot): RedirectResponse
    {
        DB::transaction(function () use ($request, $site, $buildSnapshot): void {
            $ownedSite = $request->user()->sites()->whereKey($site)->lockForUpdate()->firstOrFail();

            if ($ownedSite->slug === null) {
                throw ValidationException::withMessages([
                    'slug' => 'Choose and save a shareable address before publishing.',
                ]);
            }

            $snapshot = $buildSnapshot($ownedSite);
            $snapshot['draft_fingerprint'] = $buildSnapshot->fingerprint($snapshot);

            $ownedSite->update([
                'published_snapshot' => $snapshot,
                'published_at' => now(),
            ]);
        });

        return to_route('sites.show', $site);
    }
}
