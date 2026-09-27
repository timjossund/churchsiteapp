<?php

return [
    // Explicit local/staging sandbox testing only; production rollout remains closed.
    'enabled' => in_array(env('APP_ENV'), ['local', 'staging'], true)
        && env('CUSTOMER_DOMAINS_LOCAL_TESTING', false) === true
        && str_starts_with((string) env('STRIPE_SECRET', ''), 'sk_test_'),
    'zone_id' => env('CLOUDFLARE_SAAS_ZONE_ID'),
    'api_token' => env('CLOUDFLARE_SAAS_API_TOKEN'),
    'cname_target' => 'customers.churchsite.app',
    'origin_host' => env('CUSTOMER_DOMAINS_ORIGIN_HOST', 'origin.churchsite.app'),
    'platform_host' => 'churchsite.app',
];
