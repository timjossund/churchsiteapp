<?php

return [
    // Sandbox and production activation require separate explicit opt-ins.
    'checkout_enabled' => (in_array(env('APP_ENV'), ['local', 'staging'], true)
        && env('CUSTOMER_DOMAINS_LOCAL_TESTING', false) === true
        && str_starts_with((string) env('STRIPE_SECRET', ''), 'sk_test_'))
        || (env('APP_ENV') === 'production'
            && env('CUSTOMER_DOMAINS_PRODUCTION_ENABLED', false) === true
            && (str_starts_with((string) env('STRIPE_SECRET', ''), 'sk_live_')
                || str_starts_with((string) env('STRIPE_SECRET', ''), 'rk_live_'))),
    'portal_configuration' => env('STRIPE_SITE_PORTAL_CONFIGURATION_ID'),
    'prices' => [
        'monthly' => env('STRIPE_SITE_MONTHLY_PRICE_ID'),
        'annual' => env('STRIPE_SITE_ANNUAL_PRICE_ID'),
    ],
];
