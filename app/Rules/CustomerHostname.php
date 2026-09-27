<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CustomerHostname implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) > 253
            || preg_match('/\Awww\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?\z/', $value) !== 1) {
            $fail('Enter a full www hostname, such as www.example.org.');

            return;
        }

        $excluded = [
            parse_url((string) config('app.url'), PHP_URL_HOST),
            config('customer-domains.platform_host'),
            config('customer-domains.origin_host'),
            config('customer-domains.cname_target'),
            config('domain-proxy.proof_host'),
        ];
        foreach ($excluded as $host) {
            if (is_string($host) && $host !== '') {
                $host = strtolower(rtrim($host, '.'));
                if ($value === $host || str_ends_with($value, '.'.$host)) {
                    $fail('This hostname is unavailable.');

                    return;
                }
            }
        }
    }
}
