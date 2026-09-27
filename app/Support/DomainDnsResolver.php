<?php

namespace App\Support;

use App\Exceptions\DomainProvisioningFailed;

class DomainDnsResolver
{
    /** @return list<string> */
    public function cnames(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_CNAME);
        if ($records === false) {
            throw new DomainProvisioningFailed('dns_unavailable');
        }

        return array_map(fn (array $record): string => strtolower(rtrim($record['target'] ?? '', '.')), $records);
    }

    /** @return list<string> */
    public function txt(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_TXT);
        if ($records === false) {
            throw new DomainProvisioningFailed('dns_unavailable');
        }

        return array_map(fn (array $record): string => $record['txt'] ?? '', $records);
    }
}
