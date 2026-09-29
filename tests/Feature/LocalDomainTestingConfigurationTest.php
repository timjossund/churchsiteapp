<?php

it('opens domain setup and checkout only for explicitly enabled matching Stripe environments', function (string $environment, ?string $enabled, ?string $productionEnabled, ?string $secret, bool $expected) {
    $values = ['APP_ENV' => $environment, 'CUSTOMER_DOMAINS_LOCAL_TESTING' => $enabled, 'CUSTOMER_DOMAINS_PRODUCTION_ENABLED' => $productionEnabled, 'STRIPE_SECRET' => $secret];
    $original = [];
    foreach ($values as $key => $value) {
        $original[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
        if ($value === null) {
            putenv($key);
        } else {
            putenv($key.'='.$value);
        }
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
    try {
        expect((require base_path('config/customer-domains.php'))['enabled'])->toBe($expected)
            ->and((require base_path('config/site-billing.php'))['checkout_enabled'])->toBe($expected);
    } finally {
        foreach ($original as $key => $value) {
            if ($value[2] === false) {
                putenv($key);
            } else {
                putenv($key.'='.$value[2]);
            }
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
    'explicit local sandbox' => ['local', 'true', 'false', 'sk_test_fixture', true],
    'explicit staging sandbox' => ['staging', 'true', 'false', 'sk_test_fixture', true],
    'disabled staging sandbox' => ['staging', 'false', 'false', 'sk_test_fixture', false],
    'staging live key' => ['staging', 'true', 'false', 'sk_live_fixture', false],
    'staging missing key' => ['staging', 'true', 'false', '', false],
    'unknown environment remains closed' => ['preview', 'true', 'false', 'sk_test_fixture', false],
    'disabled local sandbox' => ['local', 'false', 'false', 'sk_test_fixture', false],
    'local live key' => ['local', 'true', 'false', 'sk_live_fixture', false],
    'local missing key' => ['local', 'true', 'false', '', false],
    'production test key remains closed' => ['production', 'true', 'false', 'sk_test_fixture', false],
    'automated tests remain closed' => ['testing', 'true', 'false', 'sk_test_fixture', false],
    'explicit production live' => ['production', 'false', 'true', 'sk_live_fixture', true],
    'both switches with production live' => ['production', 'true', 'true', 'sk_live_fixture', true],
    'disabled production live' => ['production', 'true', 'false', 'sk_live_fixture', false],
    'missing production switch' => ['production', 'true', null, 'sk_live_fixture', false],
    'nonboolean production switch' => ['production', 'false', 'yes', 'sk_live_fixture', false],
    'production opt-in with test key' => ['production', 'false', 'true', 'sk_test_fixture', false],
    'production opt-in with empty key' => ['production', 'false', 'true', '', false],
    'production opt-in with missing key' => ['production', 'false', 'true', null, false],
    'production opt-in with incorrect key' => ['production', 'false', 'true', 'invalid_fixture', false],
    'production switch cannot enable staging live' => ['staging', 'true', 'true', 'sk_live_fixture', false],
    'production switch cannot enable local live' => ['local', 'true', 'true', 'sk_live_fixture', false],
    'production switch cannot replace sandbox switch' => ['staging', 'false', 'true', 'sk_test_fixture', false],
    'production switch cannot enable testing live' => ['testing', 'false', 'true', 'sk_live_fixture', false],
    'production switch cannot enable unknown environment' => ['preview', 'false', 'true', 'sk_live_fixture', false],
    'missing sandbox switch' => ['staging', null, 'false', 'sk_test_fixture', false],
]);
