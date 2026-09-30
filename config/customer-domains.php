<?php

return [
    // Sandbox and production activation require separate explicit opt-ins.
    'enabled' => (in_array(env('APP_ENV'), ['local', 'staging'], true)
        && env('CUSTOMER_DOMAINS_LOCAL_TESTING', false) === true
        && str_starts_with((string) env('STRIPE_SECRET', ''), 'sk_test_'))
        || (env('APP_ENV') === 'production'
            && env('CUSTOMER_DOMAINS_PRODUCTION_ENABLED', false) === true
            && (str_starts_with((string) env('STRIPE_SECRET', ''), 'sk_live_')
                || str_starts_with((string) env('STRIPE_SECRET', ''), 'rk_live_'))),
    'zone_id' => env('CLOUDFLARE_SAAS_ZONE_ID'),
    'api_token' => env('CLOUDFLARE_SAAS_API_TOKEN'),
    'cname_target' => 'customers.churchsite.app',
    'origin_host' => env('CUSTOMER_DOMAINS_ORIGIN_HOST', 'origin.churchsite.app'),
    'platform_host' => 'churchsite.app',
];
