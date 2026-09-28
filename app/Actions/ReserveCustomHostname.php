<?php

namespace App\Actions;

use App\Models\CustomHostname;
use App\Models\User;
use App\Rules\CustomerHostname;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReserveCustomHostname
{
    public function handle(User $owner, int $siteId, mixed $hostname): CustomHostname
    {
        try {
            return DB::transaction(function () use ($owner, $siteId, $hostname): CustomHostname {
                $owner = User::query()->lockForUpdate()->findOrFail($owner->id);
                $site = $owner->sites()->whereNull('deletion_requested_at')->lockForUpdate()->findOrFail($siteId);
                if ($owner->deletion_requested_at !== null) {
                    throw ValidationException::withMessages(['hostname' => 'Domain setup is unavailable while account deletion is pending.']);
                }
                $hostname = is_string($hostname) ? strtolower(trim($hostname)) : $hostname;
                Validator::make(['hostname' => $hostname], ['hostname' => ['required', 'string', new CustomerHostname]])->validate();
                $existing = $site->customHostname()->first();
                if ($existing !== null) {
                    if ($existing->hostname === $hostname && $existing->state !== 'removing') {
                        return $existing;
                    }
                    throw ValidationException::withMessages(['hostname' => 'Finish disconnecting the current hostname before connecting another.']);
                }
                if (CustomHostname::query()->where('hostname', $hostname)->exists()) {
                    throw ValidationException::withMessages(['hostname' => 'This hostname is unavailable.']);
                }

                $domain = new CustomHostname;
                $domain->forceFill([
                    'site_id' => $site->id,
                    'hostname' => $hostname,
                    'operation_id' => (string) Str::uuid(),
                    'ownership_challenge' => bin2hex(random_bytes(32)),
                    'state' => 'reserved',
                    'hostname_status' => 'pending',
                    'ssl_status' => 'pending',
                ])->save();

                return $domain;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // Only a competing hostname reservation is an expected user conflict.
            if (is_string($hostname) && CustomHostname::query()->where('hostname', strtolower(trim($hostname)))->exists()) {
                throw ValidationException::withMessages(['hostname' => 'This hostname is unavailable.']);
            }
            throw $exception;
        }
    }
}
