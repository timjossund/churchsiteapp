<?php

use App\Actions\ValidateSiteBillingPrice;
use App\Exceptions\SiteBillingUnavailable;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Stripe\Price;
use Stripe\Service\PriceService;
use Stripe\StripeClient;

function mockSitePrice(array $overrides = [], string $interval = 'monthly'): Price
{
    $id = 'price_'.$interval;
    config(['site-billing.prices.'.$interval => $id]);
    $price = Price::constructFrom(array_replace_recursive([
        'id' => $id,
        'active' => true,
        'currency' => 'usd',
        'unit_amount' => $interval === 'monthly' ? 1500 : 15000,
        'type' => 'recurring',
        'billing_scheme' => 'per_unit',
        'transform_quantity' => null,
        'recurring' => [
            'interval' => $interval === 'monthly' ? 'month' : 'year',
            'interval_count' => 1,
            'usage_type' => 'licensed',
            'trial_period_days' => null,
        ],
    ], $overrides));
    $prices = Mockery::mock(PriceService::class);
    $prices->shouldReceive('retrieve')->once()->with($id)->andReturn($price);
    $client = Mockery::mock(StripeClient::class);
    $client->shouldReceive('getService')->with('prices')->andReturn($prices);
    app()->bind(StripeClient::class, fn () => $client);

    return $price;
}

test('Cashier bills each site separately with no trial and uses its owner email', function () {
    $user = User::factory()->create();
    $sites = Site::factory()->for($user)->count(2)->create();

    foreach ($sites as $index => $site) {
        $site->forceFill(['stripe_id' => 'cus_site_'.$index])->save();
        $subscription = $site->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_site_'.$index,
            'stripe_status' => 'active',
            'stripe_price' => $index === 0 ? 'price_monthly' : 'price_annual',
            'quantity' => 1,
        ]);
        $subscription->items()->create([
            'stripe_id' => 'si_'.$index,
            'stripe_product' => 'prod_site',
            'stripe_price' => $subscription->stripe_price,
            'quantity' => 1,
        ]);

        expect($subscription->owner->is($site))->toBeTrue()
            ->and($site->fresh()->subscriptions)->toHaveCount(1)
            ->and(Cashier::findBillable('cus_site_'.$index)->is($site))->toBeTrue()
            ->and($site->stripeEmail())->toBe($user->email)
            ->and($site->stripeName())->toBe($site->name)
            ->and($site->onTrial())->toBeFalse()
            ->and($site->subscription()->onTrial())->toBeFalse()
            ->and($site->toArray())->not->toHaveKeys(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']);
    }

    expect(Cashier::$customerModel)->toBe(Site::class)
        ->and(Schema::hasColumn('users', 'stripe_id'))->toBeFalse()
        ->and($sites[0]->subscription()->stripe_price)->toBe('price_monthly')
        ->and($sites[1]->subscription()->stripe_price)->toBe('price_annual');
});

test('a Stripe customer cannot belong to two sites', function () {
    $sites = Site::factory()->count(2)->create();
    $sites[0]->forceFill(['stripe_id' => 'cus_shared'])->save();
    $sites[1]->forceFill(['stripe_id' => 'cus_shared'])->save();
})->throws(UniqueConstraintViolationException::class);

test('billing migration preserves existing sites and can roll back its own schema', function () {
    $migration = require database_path('migrations/2026_09_27_000000_add_site_billing.php');
    $migration->down();
    $site = Site::factory()->create();
    $original = $site->fresh()->getAttributes();
    $homeId = $site->homePage->id;

    $migration->up();

    expect(array_intersect_key($site->fresh()->getAttributes(), $original))->toBe($original)
        ->and($site->fresh()->stripe_id)->toBeNull()
        ->and($site->fresh()->homePage->id)->toBe($homeId);

    $migration->down();
    expect($site->fresh()->getAttributes())->toBe($original);
    $migration->up();
});

test('billing routes remain unavailable during foundation work', function () {
    expect(Route::has('cashier.webhook'))->toBeFalse()
        ->and(Route::has('cashier.payment'))->toBeFalse()
        ->and(config('site-billing.checkout_enabled'))->toBeFalse();
});

test('approved USD prices validate against Stripe', function (string $interval) {
    $price = mockSitePrice(interval: $interval);
    expect(app(ValidateSiteBillingPrice::class)->handle($interval))->toBe($price);
})->with(['monthly', 'annual']);

test('invalid or unsafe Stripe prices cannot be used', function (array $override) {
    mockSitePrice($override);
    app(ValidateSiteBillingPrice::class)->handle('monthly');
})->with([
    'wrong id' => [['id' => 'price_other']],
    'inactive' => [['active' => false]],
    'wrong amount' => [['unit_amount' => 15000]],
    'wrong currency' => [['currency' => 'eur']],
    'one time' => [['type' => 'one_time', 'recurring' => null]],
    'wrong interval' => [['recurring' => ['interval' => 'year']]],
    'wrong interval count' => [['recurring' => ['interval_count' => 2]]],
    'trial' => [['recurring' => ['trial_period_days' => 7]]],
    'metered' => [['recurring' => ['usage_type' => 'metered']]],
    'tiered' => [['billing_scheme' => 'tiered']],
    'quantity transform' => [['transform_quantity' => ['divide_by' => 10, 'round' => 'up']]],
])->throws(SiteBillingUnavailable::class);

test('missing price configuration fails without contacting Stripe', function () {
    config(['site-billing.prices.monthly' => null]);
    app()->bind(StripeClient::class, fn () => throw new RuntimeException('Unexpected Stripe call'));
    app(ValidateSiteBillingPrice::class)->handle('monthly');
})->throws(SiteBillingUnavailable::class);

test('an unrecognized interval is a validation error without contacting Stripe', function () {
    app()->bind(StripeClient::class, fn () => throw new RuntimeException('Unexpected Stripe call'));
    app(ValidateSiteBillingPrice::class)->handle('weekly');
})->throws(ValidationException::class);
