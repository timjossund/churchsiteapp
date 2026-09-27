<?php

namespace App\Services;

use App\Exceptions\DomainProvisioningFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CloudflareCustomHostnames
{
    public function zone(): string
    {
        $zone = config('customer-domains.zone_id');
        $token = config('customer-domains.api_token');
        if (! is_string($zone) || preg_match('/\A[a-f0-9]{32}\z/', $zone) !== 1
            || ! is_string($token) || trim($token) === '') {
            throw new DomainProvisioningFailed('configuration');
        }

        return $zone;
    }

    /** @return array<string, mixed>|null */
    public function find(string $hostname): ?array
    {
        $body = $this->request('GET', '', ['hostname.exact' => $hostname, 'per_page' => 50]);
        $rows = $body['result'] ?? null;
        $count = $body['result_info']['total_count'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows) || ! is_int($count) || $count !== count($rows)) {
            throw new DomainProvisioningFailed('provider_response');
        }
        if ($count > 1) {
            throw new DomainProvisioningFailed('operator_required');
        }
        if ($count === 0) {
            return null;
        }
        $this->validateIdentity($rows[0], $hostname);

        return $rows[0];
    }

    /** @return array<string, mixed> */
    public function create(string $hostname): array
    {
        $body = $this->request('POST', '', [
            'hostname' => $hostname,
            'ssl' => ['method' => 'txt', 'type' => 'dv'],
        ]);
        $result = $body['result'] ?? null;
        $this->validateIdentity($result, $hostname);

        return $result;
    }

    /** @return array<string, mixed> */
    public function read(string $id, string $hostname): array
    {
        $this->validateId($id);
        $body = $this->request('GET', '/'.$id);
        $result = $body['result'] ?? null;
        $this->validateIdentity($result, $hostname);
        if ($result['id'] !== $id) {
            throw new DomainProvisioningFailed('operator_required');
        }

        return $result;
    }

    public function remove(string $id): void
    {
        $this->validateId($id);
        $body = $this->request('DELETE', '/'.$id);
        if (($body['result']['id'] ?? null) !== $id) {
            throw new DomainProvisioningFailed('provider_response');
        }
    }

    /** @param array<string, mixed> $result
     * @return array{hostname_status: string, ssl_status: string, dns_instructions: list<array{purpose: string, type: string, name: string, value: string}>} */
    public function status(array $result): array
    {
        $hostnameStatus = $result['status'] ?? null;
        $sslStatus = $result['ssl']['status'] ?? null;
        foreach ([$hostnameStatus, $sslStatus] as $status) {
            if (! is_string($status) || preg_match('/\A[a-z_]{1,64}\z/', $status) !== 1) {
                throw new DomainProvisioningFailed('provider_response');
            }
        }
        $records = [];
        if (isset($result['ownership_verification'])) {
            $record = $result['ownership_verification'];
            if (! is_array($record) || ($record['type'] ?? null) !== 'txt') {
                throw new DomainProvisioningFailed('provider_response');
            }
            $records[] = $this->record('hostname', 'TXT', $record['name'] ?? null, $record['value'] ?? null);
        }
        $validation = $result['ssl']['validation_records'] ?? [];
        if (! is_array($validation) || ! array_is_list($validation) || count($validation) > 20) {
            throw new DomainProvisioningFailed('provider_response');
        }
        foreach ($validation as $record) {
            if (! is_array($record)) {
                throw new DomainProvisioningFailed('provider_response');
            }
            if (isset($record['txt_name']) || isset($record['txt_value'])) {
                $records[] = $this->record('certificate', 'TXT', $record['txt_name'] ?? null, $record['txt_value'] ?? null);
            }
            if (isset($record['cname']) || isset($record['cname_target'])) {
                $records[] = $this->record('certificate', 'CNAME', $record['cname'] ?? null, $record['cname_target'] ?? null);
            }
        }

        return ['hostname_status' => $hostnameStatus, 'ssl_status' => $sslStatus, 'dns_instructions' => $records];
    }

    /** @return array{purpose: string, type: string, name: string, value: string} */
    private function record(string $purpose, string $type, mixed $name, mixed $value): array
    {
        if (! is_string($name) || strlen($name) > 253
            || preg_match('/\A(?:[a-zA-Z0-9_-]{1,63}\.)+[a-zA-Z0-9-]{1,63}\.?\z/', $name) !== 1
            || ! is_string($value) || $value === '' || strlen($value) > 2048
            || preg_match('/[^\x20-\x7e]/', $value) === 1
            || ($type === 'CNAME' && preg_match('/\A(?:[a-zA-Z0-9-]{1,63}\.)+[a-zA-Z0-9-]{1,63}\.?\z/', $value) !== 1)) {
            throw new DomainProvisioningFailed('provider_response');
        }

        return compact('purpose', 'type', 'name', 'value');
    }

    private function validateId(mixed $id): void
    {
        if (! is_string($id) || ! Str::isUuid($id)) {
            throw new DomainProvisioningFailed('provider_response');
        }
    }

    /** @phpstan-assert array<string, mixed> $result */
    private function validateIdentity(mixed $result, string $hostname): void
    {
        if (! is_array($result) || ($result['hostname'] ?? null) !== $hostname) {
            throw new DomainProvisioningFailed('provider_response');
        }
        $this->validateId($result['id'] ?? null);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $data = []): array
    {
        $zone = $this->zone();
        try {
            $response = Http::withToken(config('customer-domains.api_token'))->acceptJson()
                ->connectTimeout(3)->timeout(10)->withoutRedirecting()
                ->send($method, 'https://api.cloudflare.com/client/v4/zones/'.$zone.'/custom_hostnames'.$path,
                    [$method === 'GET' ? 'query' : 'json' => $data]);
        } catch (ConnectionException) {
            throw new DomainProvisioningFailed('provider_unavailable');
        }
        if (! $response->successful()) {
            throw new DomainProvisioningFailed(match (true) {
                $response->status() === 429 => 'rate_limited',
                in_array($response->status(), [401, 403], true) => 'configuration',
                $response->status() >= 500 => 'provider_unavailable',
                default => 'provider_rejected',
            });
        }
        $body = $response->json();
        if (! is_array($body) || ($body['success'] ?? null) !== true) {
            throw new DomainProvisioningFailed('provider_response');
        }

        return $body;
    }
}
