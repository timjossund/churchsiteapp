<?php

namespace App\Actions;

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestSiteDeletion
{
    public function handle(User $owner, int $siteId, string $name): Site
    {
        return DB::transaction(function () use ($owner, $siteId, $name): Site {
            $owner = User::query()->lockForUpdate()->findOrFail($owner->id);
            $site = $owner->sites()->lockForUpdate()->findOrFail($siteId);
            if (trim($name) === '' || trim($name) !== trim($site->name)) {
                throw ValidationException::withMessages(['name' => 'Enter the site name to confirm deletion.']);
            }
            if ($site->deletion_requested_at !== null) {
                return $site;
            }

            $site->forceFill([
                'deletion_requested_at' => now(),
                'published_at' => null,
                'published_snapshot' => null,
                'logo_media_asset_id' => null,
            ])->save();
            // Keep remote identities and media inventory until cleanup is confirmed.
            $site->customHostname()->update([
                'state' => 'removing', 'verified_at' => null, 'cname_matches' => false,
                'check_id' => null, 'check_started_at' => null,
            ]);
            $site->blocks()->delete();
            $site->pages()->delete();

            return $site;
        });
    }
}
