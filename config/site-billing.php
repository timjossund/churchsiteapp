<?php

return [
    // Explicit local/staging sandbox testing only; production rollout remains closed.
    'checkout_enabled' => in_array(env('APP_ENV'), ['local', 'staging'], true)
        && env('CUSTOMER_DOMAINS_LOCAL_TESTING', false) === true
        && str_starts_with((string) env('STRIPE_SECRET', ''), 'sk_test_'),
    'portal_configuration' => env('STRIPE_SITE_PORTAL_CONFIGURATION_ID'),
    'prices' => [
        'monthly' => env('STRIPE_SITE_MONTHLY_PRICE_ID'),
        'annual' => env('STRIPE_SITE_ANNUAL_PRICE_ID'),
    ],
];
