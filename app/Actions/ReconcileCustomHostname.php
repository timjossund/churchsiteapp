<?php

namespace App\Actions;

use App\Exceptions\DomainProvisioningFailed;
use App\Models\CustomHostname;
use App\Models\Site;
use App\Services\CloudflareCustomHostnames;
use App\Support\DomainDnsResolver;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReconcileCustomHostname
{
    public function __construct(private CloudflareCustomHostnames $provider, private DomainDnsResolver $dns) {}

    public function handle(int $id): void
    {
        // Cleanup remains available after rollout is disabled; it cannot provision a new hostname.
        if (config('customer-domains.enabled') !== true
            && ! CustomHostname::query()->whereKey($id)->where('state', 'removing')->exists()) {
            return;
        }
        $domain = $this->claim($id);
        if ($domain === null) {
            return;
        }
        try {
            if ($domain->state === 'removing') {
                $this->remove($domain);

                return;
            }
            $site = $domain->site()->firstOrFail();
            if (! $site->hasPaidDomainAccess()) {
                $this->apply($domain, fn (CustomHostname $current) => $current->forceFill([
                    'state' => $current->state === 'removing' ? 'removing' : 'pending',
                    'verified_at' => null, 'cname_matches' => false,
                ])->save());

                return;
            }
            $zone = $this->provider->zone();
            if ($domain->cloudflare_zone_id !== null && $domain->cloudflare_zone_id !== $zone) {
                throw new DomainProvisioningFailed('operator_required');
            }
            if ($domain->cloudflare_id === null) {
                $remote = $this->provider->find($domain->hostname);
                // Standard plans cannot tag creates with operation metadata. A matching name is not attribution.
                if ($remote !== null || $domain->provision_started_at !== null) {
                    throw new DomainProvisioningFailed('operator_required');
                }
                $started = false;
                $this->apply($domain, function (CustomHostname $current) use ($zone, &$started): void {
                    if ($current->state === 'removing' || $current->provision_started_at !== null
                        || $current->site->user->deletion_requested_at !== null
                        || ! $current->site->hasPaidDomainAccess()) {
                        return;
                    }
                    $current->forceFill(['state' => 'provisioning', 'provision_started_at' => now(), 'cloudflare_zone_id' => $zone])->save();
                    $started = true;
                });
                if (! $started) {
                    return;
                }
                $remote = $this->provider->create($domain->hostname);
                // Preserve create identity across disconnect or billing invalidation; only readiness requires the check lease.
                $saved = false;
                $this->apply($domain, function (CustomHostname $current) use ($remote, &$saved): void {
                    if ($current->cloudflare_id !== null && $current->cloudflare_id !== $remote['id']) {
                        throw new DomainProvisioningFailed('operator_required');
                    }
                    $current->forceFill(['cloudflare_id' => $remote['id']])->save();
                    $saved = true;
                }, requireCheck: false);
                if (! $saved) {
                    return;
                }
            } else {
                $remote = $this->provider->read($domain->cloudflare_id, $domain->hostname);
            }
            $status = $this->provider->status($remote);
            $ownership = in_array($domain->ownership_challenge, $this->dns->txt($domain->ownershipRecordName()), true);
            $target = config('customer-domains.cname_target');
            if (! is_string($target) || $target === '') {
                throw new DomainProvisioningFailed('configuration');
            }
            $cname = in_array(strtolower(rtrim($target, '.')), $this->dns->cnames($domain->hostname), true);
            $this->apply($domain, function (CustomHostname $current) use ($status, $ownership, $cname): void {
                if ($current->state === 'removing') {
                    return;
                }
                $ready = $ownership && $cname && $status['hostname_status'] === 'active'
                    && $status['ssl_status'] === 'active' && $current->site->hasPaidDomainAccess();
                $current->forceFill($status + [
                    'verified_at' => $ownership ? now() : null,
                    'cname_matches' => $cname,
                    'state' => $ready ? 'ready' : 'pending',
                    'error_category' => null,
                ])->save();
            });
        } catch (DomainProvisioningFailed $exception) {
            $this->apply($domain, fn (CustomHostname $current) => $current->forceFill([
                'error_category' => $exception->category,
                'state' => $current->state === 'removing' ? 'removing' : 'pending',
                'verified_at' => null,
                'cname_matches' => false,
            ])->save());
        } finally {
            $this->apply($domain, fn (CustomHostname $current) => $current->forceFill([
                'check_id' => null, 'check_started_at' => null, 'last_checked_at' => now(),
            ])->save());
        }
    }

    private function remove(CustomHostname $domain): void
    {
        if ($domain->cloudflare_id === null && $domain->provision_started_at === null) {
            $this->apply($domain, fn (CustomHostname $current) => $current->delete());

            return;
        }
        if ($domain->cloudflare_zone_id !== $this->provider->zone()) {
            throw new DomainProvisioningFailed('operator_required');
        }
        $remote = $this->provider->find($domain->hostname);
        if ($domain->cloudflare_id === null) {
            // Even an empty list cannot prove an in-flight/uncertain create will never arrive.
            throw new DomainProvisioningFailed('operator_required');
        }
        if ($remote !== null) {
            if ($remote['id'] !== $domain->cloudflare_id) {
                throw new DomainProvisioningFailed('operator_required');
            }
            $this->provider->remove($domain->cloudflare_id);
        }
        $this->apply($domain, fn (CustomHostname $current) => $current->delete());
    }

    private function claim(int $id): ?CustomHostname
    {
        $snapshot = CustomHostname::query()->find($id);
        if ($snapshot === null) {
            return null;
        }

        return DB::transaction(function () use ($snapshot): ?CustomHostname {
            Site::query()->whereKey($snapshot->site_id)->lockForUpdate()->first();
            $current = CustomHostname::query()->whereKey($snapshot->id)->lockForUpdate()->first();
            if ($current === null || ($current->check_started_at !== null && $current->check_started_at->gt(now()->subMinutes(2)))) {
                return null;
            }
            $current->forceFill(['check_id' => (string) Str::uuid(), 'check_started_at' => now()])->save();

            return $current;
        });
    }

    private function apply(CustomHostname $snapshot, Closure $callback, bool $requireCheck = true): void
    {
        DB::transaction(function () use ($snapshot, $callback, $requireCheck): void {
            Site::query()->whereKey($snapshot->site_id)->lockForUpdate()->first();
            $current = CustomHostname::query()->whereKey($snapshot->id)
                ->where('operation_id', $snapshot->operation_id)
                ->when($requireCheck, fn ($query) => $query->where('check_id', $snapshot->check_id))
                ->lockForUpdate()->first();
            if ($current !== null) {
                $callback($current);
            }
        });
    }
}
