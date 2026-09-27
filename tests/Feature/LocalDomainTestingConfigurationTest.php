<?php

it('only opens domain setup and checkout for explicitly enabled local or staging Stripe sandbox testing', function (string $environment, string $enabled, string $secret, bool $expected) {
    $values = ['APP_ENV' => $environment, 'CUSTOMER_DOMAINS_LOCAL_TESTING' => $enabled, 'STRIPE_SECRET' => $secret];
    $original = [];
    foreach ($values as $key => $value) {
        $original[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null];
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    try {
        expect((require base_path('config/customer-domains.php'))['enabled'])->toBe($expected)
            ->and((require base_path('config/site-billing.php'))['checkout_enabled'])->toBe($expected);
    } finally {
        foreach ($original as $key => $value) {
            if ($value[0] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value[0];
            }
            if ($value[1] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value[1];
            }
        }
    }
})->with([
    'explicit local sandbox' => ['local', 'true', 'sk_test_fixture', true],
    'explicit staging sandbox' => ['staging', 'true', 'sk_test_fixture', true],
    'disabled staging sandbox' => ['staging', 'false', 'sk_test_fixture', false],
    'staging live key' => ['staging', 'true', 'sk_live_fixture', false],
    'staging missing key' => ['staging', 'true', '', false],
    'unknown environment remains closed' => ['preview', 'true', 'sk_test_fixture', false],
    'disabled local sandbox' => ['local', 'false', 'sk_test_fixture', false],
    'local live key' => ['local', 'true', 'sk_live_fixture', false],
    'local missing key' => ['local', 'true', '', false],
    'production remains closed' => ['production', 'true', 'sk_test_fixture', false],
    'automated tests remain closed' => ['testing', 'true', 'sk_test_fixture', false],
]);
