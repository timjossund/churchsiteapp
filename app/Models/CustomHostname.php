<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $site_id
 * @property string $hostname
 * @property string|null $cloudflare_id
 * @property string $operation_id
 * @property string|null $cloudflare_zone_id
 * @property CarbonImmutable|null $provision_started_at
 * @property string|null $check_id
 * @property CarbonImmutable|null $check_started_at
 * @property bool $cname_matches
 * @property string $state
 * @property string $hostname_status
 * @property string $ssl_status
 * @property string $ownership_challenge
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $last_checked_at
 * @property array<int, array{purpose: string, type: string, name: string, value: string}>|null $dns_instructions
 * @property string|null $error_category
 */
class CustomHostname extends Model
{
    // Domain responses must be explicitly assembled, never serialize provider/lifecycle attributes.
    protected $visible = ['hostname'];

    public function isReadyToServe(): bool
    {
        return $this->state === 'ready' && $this->cloudflare_id !== null
            && $this->verified_at !== null && $this->cname_matches
            && $this->hostname_status === 'active' && $this->ssl_status === 'active'
            && $this->site->hasPaidDomainAccess();
    }

    public function ownershipRecordName(): string
    {
        return '_churchsite.'.$this->hostname;
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'provision_started_at' => 'immutable_datetime',
            'check_started_at' => 'immutable_datetime',
            'cname_matches' => 'boolean',
            'verified_at' => 'immutable_datetime',
            'last_checked_at' => 'immutable_datetime',
            'dns_instructions' => 'array',
        ];
    }
}
