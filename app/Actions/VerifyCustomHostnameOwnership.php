<?php

namespace App\Actions;

use App\Models\CustomHostname;
use App\Models\Site;
use App\Support\DomainDnsResolver;
use Illuminate\Support\Facades\DB;

class VerifyCustomHostnameOwnership
{
    public function __construct(private DomainDnsResolver $dns) {}

    public function handle(CustomHostname $domain): void
    {
        // DNS runs outside the lock. A disconnected or replaced operation cannot receive its result.
        $answers = $this->dns->txt($domain->ownershipRecordName());
        $matches = in_array($domain->ownership_challenge, $answers, true);
        DB::transaction(function () use ($domain, $matches): void {
            Site::query()->whereKey($domain->site_id)->lockForUpdate()->firstOrFail();
            $current = CustomHostname::query()->whereKey($domain->id)
                ->where('operation_id', $domain->operation_id)->lockForUpdate()->first();
            if ($current === null || $current->state === 'removing') {
                return;
            }
            $current->forceFill([
                'verified_at' => $matches ? now() : null,
                'last_checked_at' => now(),
            ])->save();
        });
    }
}
