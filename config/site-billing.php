<?php

return [
    // Keep checkout closed until account deletion and domain setup are integrated.
    'checkout_enabled' => false,
    'portal_configuration' => env('STRIPE_SITE_PORTAL_CONFIGURATION_ID'),
    'prices' => [
        'monthly' => env('STRIPE_SITE_MONTHLY_PRICE_ID'),
        'annual' => env('STRIPE_SITE_ANNUAL_PRICE_ID'),
    ],
];
