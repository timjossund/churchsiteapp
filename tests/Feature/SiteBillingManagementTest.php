<?php

use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

function managementStripe(Closure $handler): void
{
    config(['cashier.secret' => 'sk_test_fake', 'site-billing.portal_configuration' => 'bpc_restricted']);
    $http = Mockery::mock(ClientInterface::class);
    $http->shouldReceive('request')->andReturnUsing(function ($method, $url, $headers, $params) use ($handler) {
        return [json_encode($handler($method, parse_url($url, PHP_URL_PATH), $params), JSON_THROW_ON_ERROR), 200, []];
    });
    ApiRequestor::setHttpClient($http);
}

function managementSite(string $status = 'active', ?string $end = null): Site
{
    config(['site-billing.prices.monthly' => 'price_monthly', 'site-billing.prices.annual' => 'price_annual']);
    $site = Site::factory()->create();
    $site->forceFill(['stripe_id' => 'cus_'.$site->id])->save();
    $site->subscriptions()->create([
        'type' => 'default', 'stripe_id' => 'sub_'.$site->id, 'stripe_status' => $status,
        'stripe_price' => 'price_monthly', 'quantity' => 1, 'ends_at' => $end,
        'paid_until' => now()->addMonth()->toDateTimeString(),
    ]);

    return $site;
}

function managementPortalConfiguration(): array
{
    return [
        'id' => 'bpc_restricted', 'object' => 'billing_portal.configuration', 'active' => true,
        'login_page' => ['enabled' => false],
        'features' => [
            'subscription_cancel' => ['enabled' => false], 'subscription_update' => ['enabled' => false],
            'customer_update' => ['enabled' => false], 'payment_method_update' => ['enabled' => true],
            'invoice_history' => ['enabled' => true],
        ],
    ];
}

afterEach(function () {
    ApiRequestor::setHttpClient(new CurlClient);
});

test('billing management enforces owner and verified authentication before Stripe', function (string $action) {
    $site = managementSite();
    managementStripe(fn () => throw new RuntimeException('No provider request expected'));
    $url = route('sites.billing.'.$action, $site);
    $this->post($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->post($url)->assertNotFound();
    $site->user->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($site->user)->post($url)->assertRedirect(route('verification.notice'));
})->with(['portal', 'cancel']);

test('settings present billing states without provider calls or private identifiers', function (string $remoteStatus, ?string $end, string $expected) {
    $site = managementSite($remoteStatus, $end === null ? null : now()->addDays(10)->toDateTimeString());
    managementStripe(fn () => throw new RuntimeException('Settings must not call Stripe'));
    $this->actingAs($site->user)->get(route('sites.show', $site))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Sites/Settings')->where('billing.status', $expected)
        ->where('billing.amount', 1500)->where('billing.interval', 'monthly')->where('billing.currency', 'USD')
        ->where('billing.can_manage', true)->missing('site.stripe_id')->missing('billing.stripe_id'));
})->with([
    ['active', null, 'active'], ['active', 'future', 'cancellation_scheduled'],
    ['past_due', null, 'action_required'], ['unpaid', null, 'action_required'],
    ['incomplete', null, 'action_required'], ['canceled', null, 'ended'], ['paused', null, 'unavailable'],
]);

test('free pending and annual billing summaries remain site specific', function () {
    $site = Site::factory()->create();
    $this->actingAs($site->user)->get(route('sites.show', $site))->assertInertia(fn (Assert $page) => $page
        ->where('billing.status', 'free')->where('billing.can_manage', false));
    $site->forceFill(['checkout_attempt' => 'pending'])->save();
    $this->get(route('sites.show', $site).'?billing=success')->assertInertia(fn (Assert $page) => $page->where('billing.status', 'pending'));
    $annual = managementSite();
    $annual->forceFill(['user_id' => $site->user_id])->save();
    $annual->subscriptions()->first()->update(['stripe_price' => 'price_annual']);
    $this->get(route('sites.show', $annual))->assertInertia(fn (Assert $page) => $page
        ->where('billing.interval', 'annual')->where('billing.amount', 15000));
    $this->get(route('sites.show', $site))->assertInertia(fn (Assert $page) => $page->where('billing.status', 'pending'));
});

test('portal uses only the selected site customer restricted configuration and server return URL', function () {
    $site = managementSite();
    $site->user->forceFill(['deletion_requested_at' => now()])->save();
    managementStripe(function ($method, $path, $params) use ($site) {
        if ($path === '/v1/billing_portal/configurations/bpc_restricted') {
            return managementPortalConfiguration();
        }
        expect($path)->toBe('/v1/billing_portal/sessions')->and($method)->toBe('post')
            ->and($params['customer'])->toBe($site->stripe_id)
            ->and($params['configuration'])->toBe('bpc_restricted')
            ->and($params['return_url'])->toBe(route('sites.show', $site));

        return ['id' => 'bps_test', 'object' => 'billing_portal.session', 'url' => 'https://billing.stripe.com/p/session/test'];
    });
    $this->actingAs($site->user)->withHeader('X-Inertia', 'true')->post(route('sites.billing.portal', $site), [
        'customer' => 'cus_other', 'configuration' => 'bpc_unsafe', 'return_url' => 'https://example.com',
    ])->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://billing.stripe.com/p/session/test');
    $this->withoutHeader('X-Inertia')->get(route('sites.show', $site))->assertInertia(fn (Assert $page) => $page->where('billing.can_cancel', false));
});

test('unsafe portal configuration is rejected before issuing a session', function (string $setting) {
    $site = managementSite();
    managementStripe(function ($method, $path) use ($setting) {
        expect($path)->toBe('/v1/billing_portal/configurations/bpc_restricted');
        $config = managementPortalConfiguration();
        data_set($config, $setting, ! data_get($config, $setting));

        return $config;
    });
    $this->actingAs($site->user)->from(route('sites.show', $site))->post(route('sites.billing.portal', $site))
        ->assertRedirect(route('sites.show', $site))->assertSessionHasErrors('billing');
})->with(['active', 'login_page.enabled', 'features.subscription_cancel.enabled', 'features.subscription_update.enabled', 'features.customer_update.enabled', 'features.payment_method_update.enabled', 'features.invoice_history.enabled']);

test('missing portal setup and provider failures yield retryable feedback', function () {
    $site = managementSite();
    managementStripe(fn () => throw new ApiConnectionException('simulated outage'));
    $this->actingAs($site->user)->post(route('sites.billing.portal', $site))->assertSessionHasErrors('billing');
    $this->post(route('sites.billing.cancel', $site))->assertSessionHasErrors('billing');
    config(['site-billing.portal_configuration' => null]);
    $this->post(route('sites.billing.portal', $site))->assertSessionHasErrors('billing');
    expect($site->subscriptions()->first()->ends_at)->toBeNull()->and($site->fresh()->hasPaidDomainAccess())->toBeTrue();
});

test('cancel renewal is retry safe and preserves paid access account and site', function () {
    $site = managementSite();
    $end = now()->addMonth()->timestamp;
    $canceled = false;
    $writes = 0;
    managementStripe(function ($method, $path, $params) use ($site, $end, &$canceled, &$writes) {
        expect($path)->toBe('/v1/subscriptions/sub_'.$site->id);
        if ($method === 'post') {
            expect(array_keys($params))->toBe(['cancel_at_period_end'])
                ->and(filter_var($params['cancel_at_period_end'], FILTER_VALIDATE_BOOLEAN))->toBeTrue();
            $canceled = true;
            $writes++;
        }

        return ['id' => 'sub_'.$site->id, 'object' => 'subscription', 'customer' => $site->stripe_id,
            'status' => 'active', 'cancel_at_period_end' => $canceled, 'cancel_at' => $canceled ? $end : null];
    });
    $this->actingAs($site->user);
    for ($i = 0; $i < 2; $i++) {
        $this->post(route('sites.billing.cancel', $site))->assertRedirect(route('sites.show', $site))->assertSessionHasNoErrors();
    }
    expect($writes)->toBe(1)->and($site->fresh()->hasPaidDomainAccess())->toBeTrue()
        ->and($site->user->fresh()->deletion_requested_at)->toBeNull()
        ->and($site->subscriptions()->first()->ends_at->timestamp)->toBe($end);
    $this->get(route('sites.show', $site))->assertInertia(fn (Assert $page) => $page
        ->where('billing.status', 'cancellation_scheduled')->where('billing.can_cancel', false));
});

test('cancellation refuses a mismatched customer without writing to Stripe', function () {
    $site = managementSite();
    managementStripe(function ($method) use ($site) {
        expect($method)->toBe('get');

        return ['id' => 'sub_'.$site->id, 'object' => 'subscription', 'customer' => 'cus_other'];
    });
    $this->actingAs($site->user)->post(route('sites.billing.cancel', $site))->assertSessionHasErrors('billing');
    expect($site->subscriptions()->first()->ends_at)->toBeNull();
});

test('billing restrictions never block free editing or expose billing through publication', function (string $status, bool $deleting) {
    $site = managementSite($status);
    $site->update(['slug' => 'billing-free-site']);
    if ($deleting) {
        $site->user->forceFill(['deletion_requested_at' => now()])->save();
    }
    managementStripe(fn () => throw new RuntimeException('Free editing and publishing must not contact Stripe'));
    $this->actingAs($site->user)->patch(route('sites.update', $site), ['name' => 'Updated Church'])
        ->assertSessionHasNoErrors();
    $this->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    $site->refresh();
    expect($site->name)->toBe('Updated Church')->and($site->published_snapshot)->not->toBeNull();
    $snapshot = json_encode($site->published_snapshot, JSON_THROW_ON_ERROR);
    expect($snapshot)->not->toContain('stripe_id', 'paid_until', 'deletion_requested_at', $site->stripe_id);
    $this->get(route('sites.published.show', $site->slug))->assertOk()->assertDontSee($site->stripe_id);
})->with([['unpaid', false], ['canceled', false], ['active', true]]);
