<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DisconnectCustomHostname
{
    public function handle(User $owner, int $siteId): void
    {
        DB::transaction(function () use ($owner, $siteId): void {
            $owner = User::query()->lockForUpdate()->findOrFail($owner->id);
            $site = $owner->sites()->lockForUpdate()->findOrFail($siteId);
            $domain = $site->customHostname()->lockForUpdate()->first();
            if ($domain !== null) {
                // Retain the exact operation and remote ID for retryable cleanup.
                $domain->forceFill(['state' => 'removing', 'verified_at' => null])->save();
            }
        });
    }
}
