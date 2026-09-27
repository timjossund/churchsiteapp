<?php

use App\Actions\DisconnectCustomHostname;
use App\Actions\ReconcileCustomHostname;
use App\Actions\ReserveCustomHostname;
use App\Actions\SiteBillingSummary;
use App\Actions\StartSiteCheckout;
use App\Models\Site;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

function billingPriceData(): array
{
    return ['id' => 'price_monthly', 'object' => 'price', 'active' => true, 'currency' => 'usd', 'unit_amount' => 1500, 'type' => 'recurring', 'billing_scheme' => 'per_unit', 'transform_quantity' => null, 'product' => 'prod_site', 'recurring' => ['interval' => 'month', 'interval_count' => 1, 'usage_type' => 'licensed', 'trial_period_days' => null]];
}

function billingSubscriptionData(string $customer = 'cus_site'): array
{
    $end = now()->addMonth()->timestamp;

    return [
        'id' => 'sub_site', 'object' => 'subscription', 'customer' => $customer, 'status' => 'active',
        'metadata' => ['type' => 'default'], 'trial_end' => null, 'cancel_at_period_end' => false, 'canceled_at' => null,
        'items' => ['object' => 'list', 'data' => [['id' => 'si_site', 'object' => 'subscription_item', 'price' => billingPriceData(), 'quantity' => 1, 'current_period_end' => $end]]],
        'latest_invoice' => [
            'id' => 'in_site', 'object' => 'invoice', 'status' => 'paid', 'amount_paid' => 1500, 'currency' => 'usd',
            'parent' => ['subscription_details' => ['subscription' => 'sub_site']],
            'lines' => ['object' => 'list', 'data' => [[
                'parent' => ['subscription_item_details' => ['subscription_item' => 'si_site']],
                'pricing' => ['price_details' => ['price' => 'price_monthly']],
                'period' => ['end' => $end],
            ]]],
        ],
    ];
}

function fakeBillingStripe(Closure $handler): void
{
    config(['cashier.secret' => 'sk_test_fake', 'cashier.webhook.secret' => 'whsec_test', 'site-billing.prices.monthly' => 'price_monthly']);
    $http = Mockery::mock(ClientInterface::class);
    $http->shouldReceive('request')->andReturnUsing(function ($method, $url, $headers, $params) use ($handler) {
        $data = $handler($method, parse_url($url, PHP_URL_PATH), $params, $headers);

        return [json_encode($data, JSON_THROW_ON_ERROR), 200, []];
    });
    ApiRequestor::setHttpClient($http);
}

function signedBillingEvent(array $object, string $type = 'customer.subscription.updated', ?string $signature = null): TestResponse
{
    $body = json_encode(['id' => 'evt_test', 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]], JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature ??= 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test');

    return test()->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $body);
}

afterEach(function () {
    ApiRequestor::setHttpClient(new CurlClient);
});

test('checkout is closed by default and enforces authentication and site ownership', function () {
    $site = Site::factory()->create();
    $url = route('sites.billing.checkout', $site);
    $this->post($url, ['interval' => 'monthly'])->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->post($url, ['interval' => 'monthly'])->assertNotFound();
    $this->actingAs($site->user)->post($url, ['interval' => 'monthly'])->assertNotFound();
    $site->user->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($site->user)->post($url, ['interval' => 'monthly'])->assertRedirect(route('verification.notice'));
});

test('checkout retry reuses a persisted session without creating another subscription', function () {
    $site = Site::factory()->create();
    app(ReserveCustomHostname::class)->handle($site->user, $site->id, 'www.example.org');
    $created = 0;
    $session = ['id' => 'cs_site', 'object' => 'checkout.session', 'status' => 'open', 'url' => 'https://checkout.stripe.com/c/pay/test'];
    fakeBillingStripe(function ($method, $path, $params, $headers) use (&$created, $session, $site) {
        if ($path === '/v1/prices/price_monthly') {
            return billingPriceData();
        }
        if ($path === '/v1/customers') {
            return ['id' => 'cus_site', 'object' => 'customer'];
        }
        if ($path === '/v1/subscriptions') {
            return ['object' => 'list', 'data' => [], 'has_more' => false];
        }
        if ($path === '/v1/checkout/sessions/cs_site') {
            return $session;
        }
        if ($path === '/v1/checkout/sessions') {
            $created++;
            expect($params['success_url'])->toBe(route('sites.go-live', $site).'?billing=processing')
                ->and($params['cancel_url'])->toBe(route('sites.go-live', $site).'?billing=canceled')
                ->and($params['customer'])->toBe('cus_site')
                ->and($params['managed_payments'])->toBe(['enabled' => 'false'])
                ->and($params['line_items'])->toBe([['price' => 'price_monthly', 'quantity' => 1]])
                ->and($params['subscription_data'])->not->toHaveKeys(['trial_end', 'trial_period_days'])
                ->and(implode(' ', $headers))->toContain('site-checkout-');

            return $session;
        }
        throw new RuntimeException('Unexpected Stripe request: '.$path);
    });
    $action = app(StartSiteCheckout::class);
    $first = $action->handle($site->user, $site->id, 'monthly');
    $second = $action->handle($site->user, $site->id, 'monthly');
    expect($first->id)->toBe($second->id)->and($created)->toBe(1)
        ->and($site->fresh()->hasPaidDomainAccess())->toBeFalse();
    $this->actingAs($site->user)->get(route('sites.go-live', $site).'?billing=processing')->assertOk();
    expect($site->fresh()->hasPaidDomainAccess())->toBeFalse();
});

test('checkout timeout retains customer and idempotency identity for retry', function () {
    $site = Site::factory()->create();
    app(ReserveCustomHostname::class)->handle($site->user, $site->id, 'www.example.org');
    $keys = [];
    fakeBillingStripe(function ($method, $path, $params, $headers) use (&$keys) {
        if ($path === '/v1/prices/price_monthly') {
            return billingPriceData();
        }
        if ($path === '/v1/customers') {
            return ['id' => 'cus_site', 'object' => 'customer'];
        }
        if ($path === '/v1/subscriptions') {
            return ['object' => 'list', 'data' => [], 'has_more' => false];
        }
        if ($path === '/v1/checkout/sessions') {
            $keys[] = array_values(array_filter($headers, fn ($header) => str_starts_with($header, 'Idempotency-Key:')));
            throw new ApiConnectionException('simulated lost response');
        }
        throw new RuntimeException('Unexpected Stripe request');
    });
    for ($i = 0; $i < 2; $i++) {
        try {
            app(StartSiteCheckout::class)->handle($site->user, $site->id, 'monthly');
            $this->fail('Expected timeout');
        } catch (ApiConnectionException) {
            expect($site->fresh()->stripe_id)->toBe('cus_site');
        }
    }
    expect($keys)->toHaveCount(2)->and($keys[0])->not->toBeEmpty()->and($keys[0])->toBe($keys[1]);
});

test('uncertain checkout older than idempotency retention is not replayed', function () {
    $site = Site::factory()->create();
    app(ReserveCustomHostname::class)->handle($site->user, $site->id, 'www.example.org');
    $site->forceFill(['checkout_attempt' => 'old', 'checkout_started_at' => now()->subDay(), 'checkout_price_id' => 'price_monthly'])->save();
    fakeBillingStripe(function ($method, $path) {
        expect($path)->toBe('/v1/prices/price_monthly');

        return billingPriceData();
    });
    app(StartSiteCheckout::class)->handle($site->user, $site->id, 'monthly');
})->throws(ValidationException::class);

test('unsigned webhooks and missing webhook configuration fail closed', function () {
    config(['cashier.webhook.secret' => null]);
    signedBillingEvent(['id' => 'sub_site', 'customer' => 'cus_site'])->assertStatus(503);
    config(['cashier.webhook.secret' => 'whsec_test']);
    signedBillingEvent(['id' => 'sub_site', 'customer' => 'cus_site'], signature: 'invalid')->assertStatus(400);
});

test('webhooks use current provider state and repeated old events cannot restore access', function () {
    $site = Site::factory()->create();
    $site->forceFill(['stripe_id' => 'cus_site'])->save();
    $remote = billingSubscriptionData();
    fakeBillingStripe(function ($method, $path) use (&$remote) {
        expect($path)->toBe('/v1/subscriptions/sub_site');

        return $remote;
    });
    $oldEvent = $remote;
    signedBillingEvent($oldEvent)->assertOk();
    signedBillingEvent($oldEvent)->assertOk();
    expect($site->subscriptions()->count())->toBe(1)
        ->and($site->fresh()->hasPaidDomainAccess())->toBeTrue();
    $remote['status'] = 'canceled';
    $remote['canceled_at'] = now()->timestamp;
    signedBillingEvent($oldEvent)->assertOk();
    expect($site->fresh()->hasPaidDomainAccess())->toBeFalse();
});

test('pending and unpaid subscriptions never grant domain access', function (string $status, string $invoiceStatus) {
    $site = Site::factory()->create();
    $site->forceFill(['stripe_id' => 'cus_site'])->save();
    $remote = billingSubscriptionData();
    $remote['status'] = $status;
    $remote['latest_invoice']['status'] = $invoiceStatus;
    fakeBillingStripe(fn () => $remote);
    signedBillingEvent($remote)->assertOk();
    expect($site->fresh()->hasPaidDomainAccess())->toBeFalse();
})->with([['incomplete', 'open'], ['past_due', 'open'], ['active', 'open'], ['trialing', 'paid']]);

test('paid cancellation grace ends at the confirmed paid period', function () {
    $site = Site::factory()->create();
    $site->forceFill(['stripe_id' => 'cus_site'])->save();
    $remote = billingSubscriptionData();
    $remote['cancel_at_period_end'] = true;
    fakeBillingStripe(fn () => $remote);
    signedBillingEvent($remote)->assertOk();
    expect($site->fresh()->hasPaidDomainAccess())->toBeTrue();
    $this->travel(32)->days();
    expect($site->fresh()->hasPaidDomainAccess())->toBeFalse();
});

test('a webhook cannot attach another customers subscription to a site', function () {
    $site = Site::factory()->create();
    $site->forceFill(['stripe_id' => 'cus_site'])->save();
    fakeBillingStripe(fn () => billingSubscriptionData('cus_other'));
    signedBillingEvent(['id' => 'sub_site', 'customer' => 'cus_site'])->assertOk();
    expect($site->subscriptions()->count())->toBe(0);
});

test('Stripe read failures cause webhook retry without granting access', function () {
    $site = Site::factory()->create();
    $site->forceFill(['stripe_id' => 'cus_site'])->save();
    fakeBillingStripe(fn () => throw new ApiConnectionException('simulated outage'));
    signedBillingEvent(['id' => 'sub_site', 'customer' => 'cus_site'])->assertStatus(503);
    expect($site->fresh()->hasPaidDomainAccess())->toBeFalse();
});

test('an ended subscription can start a replacement checkout and retries reuse it', function (string $terminalStatus) {
    $site = Site::factory()->create();
    app(ReserveCustomHostname::class)->handle($site->user, $site->id, 'www.example.org');
    $site->forceFill(['stripe_id' => 'cus_site', 'checkout_attempt' => 'old-attempt', 'checkout_started_at' => now()->subYear(), 'checkout_price_id' => 'price_annual', 'checkout_session_id' => 'cs_old'])->save();
    $site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_old', 'stripe_status' => $terminalStatus]);
    $writes = 0;
    $newSession = ['id' => 'cs_new', 'object' => 'checkout.session', 'status' => 'open', 'url' => 'https://checkout.stripe.com/c/pay/new'];
    $terminal = ['id' => 'sub_old', 'object' => 'subscription', 'customer' => 'cus_site', 'status' => $terminalStatus];
    fakeBillingStripe(function ($method, $path, $params, $headers) use (&$writes, $newSession, $terminal) {
        if ($path === '/v1/prices/price_monthly') {
            return billingPriceData();
        }
        if ($path === '/v1/checkout/sessions/cs_old') {
            return ['id' => 'cs_old', 'object' => 'checkout.session', 'status' => 'complete', 'customer' => 'cus_site', 'subscription' => 'sub_old'];
        }
        if ($path === '/v1/subscriptions/sub_old') {
            return $terminal;
        }
        if ($path === '/v1/subscriptions') {
            return ['object' => 'list', 'data' => [$terminal], 'has_more' => false];
        }
        if ($path === '/v1/checkout/sessions') {
            $writes++;
            expect($params['customer'])->toBe('cus_site')->and($params['line_items'][0]['price'])->toBe('price_monthly')
                ->and(implode(' ', $headers))->not->toContain('site-checkout-old-attempt');

            return $newSession;
        }
        if ($path === '/v1/checkout/sessions/cs_new') {
            return $newSession;
        }
        throw new RuntimeException('Unexpected provider call: '.$path);
    });
    $action = app(StartSiteCheckout::class);
    expect($action->handle($site->user, $site->id, 'monthly')->id)->toBe('cs_new');
    $attempt = $site->fresh()->checkout_attempt;
    expect($action->handle($site->user, $site->id, 'monthly')->id)->toBe('cs_new')
        ->and($writes)->toBe(1)->and($attempt)->not->toBe('old-attempt')
        ->and($site->fresh()->checkout_attempt)->toBe($attempt)->and($site->fresh()->hasPaidDomainAccess())->toBeFalse();
})->with(['canceled', 'incomplete_expired']);

test('completed checkout cannot be replaced while its provider commitment is unresolved', function (string $scenario) {
    $site = Site::factory()->create();
    app(ReserveCustomHostname::class)->handle($site->user, $site->id, 'www.example.org');
    $site->forceFill(['stripe_id' => 'cus_site', 'checkout_attempt' => 'old-attempt', 'checkout_started_at' => now()->subYear(), 'checkout_price_id' => 'price_monthly', 'checkout_session_id' => 'cs_old'])->save();
    fakeBillingStripe(function ($method, $path) use ($scenario) {
        expect($method)->toBe('get');
        if ($path === '/v1/prices/price_monthly') {
            return billingPriceData();
        }
        if ($path === '/v1/checkout/sessions/cs_old') {
            return ['id' => 'cs_old', 'object' => 'checkout.session', 'status' => 'complete',
                'customer' => $scenario === 'wrong-session-customer' ? 'cus_other' : 'cus_site',
                'subscription' => $scenario === 'unresolved' ? null : 'sub_old'];
        }
        expect($path)->toBe('/v1/subscriptions/sub_old');
        if ($scenario === 'outage') {
            throw new ApiConnectionException('simulated outage');
        }

        return ['id' => 'sub_old', 'object' => 'subscription',
            'customer' => $scenario === 'wrong-subscription-customer' ? 'cus_other' : 'cus_site',
            'status' => $scenario === 'wrong-subscription-customer' ? 'canceled' : $scenario];
    });
    try {
        app(StartSiteCheckout::class)->handle($site->user, $site->id, 'monthly');
        $this->fail('A new checkout must not be created');
    } catch (ValidationException|ApiConnectionException) {
        expect($site->fresh()->checkout_session_id)->toBe('cs_old')
            ->and($site->fresh()->checkout_attempt)->toBe('old-attempt');
    }
})->with(['active', 'incomplete', 'past_due', 'unresolved', 'wrong-session-customer', 'wrong-subscription-customer', 'outage']);

test('a delayed historical webhook cannot displace the current renewal', function () {
    $this->freezeTime();
    $site = Site::factory()->create();
    $site->forceFill(['stripe_id' => 'cus_site'])->save();
    $current = $site->subscriptions()->create([
        'type' => 'default', 'stripe_id' => 'sub_current', 'stripe_status' => 'active',
        'stripe_price' => 'price_monthly', 'paid_until' => now()->addMonth(),
    ]);
    $this->travel(1)->hour();
    $old = billingSubscriptionData();
    $old['id'] = 'sub_old';
    $old['status'] = 'canceled';
    $old['canceled_at'] = now()->subMonth()->timestamp;
    $end = now()->addDays(20)->timestamp;
    $writes = [];
    fakeBillingStripe(function ($method, $path, $params) use ($old, $end, &$writes) {
        if ($path === '/v1/subscriptions/sub_old') {
            expect($method)->toBe('get');

            return $old;
        }
        expect($path)->toBe('/v1/subscriptions/sub_current');
        if ($method === 'post') {
            expect(filter_var($params['cancel_at_period_end'], FILTER_VALIDATE_BOOLEAN))->toBeTrue();
            $writes[] = $path;
        }

        return ['id' => 'sub_current', 'object' => 'subscription', 'customer' => 'cus_site', 'status' => 'active',
            'cancel_at_period_end' => $method === 'post', 'cancel_at' => $method === 'post' ? $end : null];
    });
    signedBillingEvent($old, 'customer.subscription.deleted')->assertOk();
    expect($site->subscriptions()->where('stripe_id', 'sub_old')->exists())->toBeTrue();
    $summary = app(SiteBillingSummary::class)->handle($site);
    expect($summary['status'])->toBe('active')->and($summary['can_cancel'])->toBeTrue();
    $this->actingAs($site->user)->post(route('sites.billing.cancel', $site))->assertSessionHasNoErrors();
    expect($writes)->toBe(['/v1/subscriptions/sub_current'])->and($current->fresh()->ends_at->timestamp)->toBe($end);
    signedBillingEvent($old, 'customer.subscription.deleted')->assertOk();
    expect(app(SiteBillingSummary::class)->handle($site)['status'])->toBe('cancellation_scheduled');
});

test('billing selection isolates sites and types and orders terminal history by end date', function () {
    $site = Site::factory()->create();
    $other = Site::factory()->create();
    $latest = $site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_latest', 'stripe_status' => 'canceled', 'ends_at' => now()->subDay()]);
    $site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_old', 'stripe_status' => 'canceled', 'ends_at' => now()->subYear()]);
    $site->subscriptions()->create(['type' => 'other', 'stripe_id' => 'sub_other_type', 'stripe_status' => 'active']);
    $other->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_other_site', 'stripe_status' => 'active']);
    expect($site->billingSubscription()->id)->toBe($latest->id);
    $pending = $site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_pending', 'stripe_status' => 'incomplete']);
    expect($site->billingSubscription()->id)->toBe($pending->id);
});

function fakeDomainCheckout(): void
{
    fakeBillingStripe(function ($method, $path) {
        return match ($path) {
            '/v1/prices/price_monthly' => billingPriceData(),
            '/v1/customers' => ['id' => 'cus_site', 'object' => 'customer'],
            '/v1/subscriptions' => ['object' => 'list', 'data' => [], 'has_more' => false],
            '/v1/checkout/sessions', '/v1/checkout/sessions/cs_domain' => [
                'id' => 'cs_domain', 'object' => 'checkout.session', 'status' => 'open', 'url' => 'https://checkout.stripe.com/c/pay/domain',
            ],
            default => throw new RuntimeException('Unexpected Stripe request'),
        };
    });
}

test('checkout requires valid retained domain intent before any Stripe request', function (string $intent) {
    config(['site-billing.checkout_enabled' => true, 'customer-domains.enabled' => true]);
    $site = Site::factory()->create();
    if ($intent !== 'missing') {
        $domain = app(ReserveCustomHostname::class)->handle($site->user, $site->id, 'www.example.org');
        $domain->forceFill($intent === 'removing' ? ['state' => 'removing'] : ['hostname' => 'invalid'])->save();
    }
    fakeBillingStripe(fn () => throw new RuntimeException('No Stripe request allowed'));
    $this->actingAs($site->user)->postJson(route('sites.billing.checkout', $site), ['interval' => 'monthly'])
        ->assertUnprocessable()->assertJsonValidationErrors('hostname');
})->with(['missing', 'removing', 'invalid']);

test('domain connection starts immediate checkout and repeated submissions resume the same session', function () {
    config(['site-billing.checkout_enabled' => true, 'customer-domains.enabled' => true]);
    $site = Site::factory()->create();
    fakeDomainCheckout();
    $this->actingAs($site->user)->withHeader('X-Inertia', 'true');
    for ($i = 0; $i < 2; $i++) {
        $this->post(route('sites.domain.store', $site), ['hostname' => 'www.example.org', 'interval' => 'monthly'])
            ->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://checkout.stripe.com/c/pay/domain');
    }
    expect($site->customHostname()->count())->toBe(1)->and($site->fresh()->checkout_session_id)->toBe('cs_domain')
        ->and($site->fresh()->hasPaidDomainAccess())->toBeFalse();
    $this->withoutHeader('X-Inertia')->get(route('sites.go-live', $site).'?billing=success')->assertOk();
    expect($site->fresh()->hasPaidDomainAccess())->toBeFalse()->and($site->customHostname()->first()->verified_at)->toBeNull();
});

test('a canceled checkout return retains intent and can resume checkout', function () {
    config(['site-billing.checkout_enabled' => true, 'customer-domains.enabled' => true]);
    $site = Site::factory()->create();
    fakeDomainCheckout();
    $this->actingAs($site->user)->post(route('sites.domain.store', $site), ['hostname' => 'www.example.org', 'interval' => 'monthly'])->assertRedirect();
    $operation = $site->customHostname()->first()->operation_id;
    $this->get(route('sites.show', $site))->assertOk();
    $this->post(route('sites.billing.checkout', $site), ['interval' => 'monthly'])->assertRedirect();
    expect($site->customHostname()->first()->operation_id)->toBe($operation)
        ->and($site->fresh()->checkout_session_id)->toBe('cs_domain')->and($site->fresh()->hasPaidDomainAccess())->toBeFalse();
});

test('failed payment setup keeps resumable domain intent', function () {
    config(['site-billing.checkout_enabled' => true, 'customer-domains.enabled' => true]);
    $site = Site::factory()->create();
    fakeBillingStripe(fn () => throw new ApiConnectionException('offline'));
    $this->actingAs($site->user)->from(route('sites.show', $site))->post(route('sites.domain.store', $site), ['hostname' => 'www.example.org', 'interval' => 'monthly'])
        ->assertRedirect(route('sites.show', $site))->assertSessionHasErrors('billing');
    $operation = $site->customHostname()->first()->operation_id;
    fakeDomainCheckout();
    $this->post(route('sites.billing.checkout', $site), ['interval' => 'monthly'])->assertRedirect();
    expect($site->customHostname()->first()->operation_id)->toBe($operation);
});

test('paid connection and replacement reuse billing without Stripe calls', function () {
    config(['customer-domains.enabled' => true]);
    $site = Site::factory()->create();
    $subscription = $site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_paid', 'stripe_status' => 'active', 'paid_until' => now()->addMonth()]);
    $before = $subscription->fresh()->getAttributes();
    fakeBillingStripe(fn () => throw new RuntimeException('A second subscription must not be created'));
    $this->actingAs($site->user)->post(route('sites.domain.store', $site), ['hostname' => 'www.first.org'])->assertSessionHasNoErrors();
    $first = $site->customHostname()->first();
    $this->delete(route('sites.domain.destroy', $site))->assertSessionHasNoErrors();
    expect($first->fresh()->state)->toBe('removing');
    app(ReconcileCustomHostname::class)->handle($first->id);
    $this->post(route('sites.domain.store', $site), ['hostname' => 'www.second.org'])->assertSessionHasNoErrors();
    expect($site->customHostname()->first()->hostname)->toBe('www.second.org')
        ->and($subscription->fresh()->getAttributes())->toBe($before)->and($site->subscriptions()->count())->toBe(1);
});

test('paid time expiry denies serving immediately and resubscription requires fresh readiness', function () {
    $this->freezeTime();
    $site = Site::factory()->create();
    $site->forceFill(['stripe_id' => 'cus_site'])->save();
    $domain = app(ReserveCustomHostname::class)->handle($site->user, $site->id, 'www.example.org');
    $domain->forceFill(['state' => 'ready', 'cloudflare_id' => 'managed', 'hostname_status' => 'active', 'ssl_status' => 'active', 'verified_at' => now(), 'cname_matches' => true])->save();
    $site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_old', 'stripe_status' => 'active', 'paid_until' => now()->addMinute()]);
    expect($domain->isReadyToServe())->toBeTrue();
    $this->travel(2)->minutes();
    expect($domain->fresh()->isReadyToServe())->toBeFalse()->and($domain->fresh()->cloudflare_id)->toBe('managed');
    fakeBillingStripe(fn () => billingSubscriptionData());
    signedBillingEvent(billingSubscriptionData())->assertOk();
    expect($site->fresh()->hasPaidDomainAccess())->toBeTrue()
        ->and($domain->fresh()->state)->toBe('pending')->and($domain->fresh()->verified_at)->toBeNull()
        ->and($domain->fresh()->cloudflare_id)->toBe('managed')->and($domain->fresh()->isReadyToServe())->toBeFalse();
});

test('payment reconciliation never revives an explicitly disconnected domain', function () {
    $site = Site::factory()->create();
    $site->forceFill(['stripe_id' => 'cus_site'])->save();
    $domain = app(ReserveCustomHostname::class)->handle($site->user, $site->id, 'www.example.org');
    app(DisconnectCustomHostname::class)->handle($site->user, $site->id);
    fakeBillingStripe(fn () => billingSubscriptionData());
    signedBillingEvent(billingSubscriptionData())->assertOk();
    expect($site->fresh()->hasPaidDomainAccess())->toBeTrue()->and($domain->fresh()->state)->toBe('removing');
});

test('failed payment revokes readiness while preserving the connection for recovery', function () {
    $site = Site::factory()->create();
    $site->forceFill(['stripe_id' => 'cus_site'])->save();
    $domain = app(ReserveCustomHostname::class)->handle($site->user, $site->id, 'www.example.org');
    $domain->forceFill(['state' => 'ready', 'cloudflare_id' => 'managed', 'hostname_status' => 'active', 'ssl_status' => 'active', 'verified_at' => now(), 'cname_matches' => true])->save();
    $site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_site', 'stripe_status' => 'active', 'paid_until' => now()->addMonth()]);
    $remote = billingSubscriptionData();
    $remote['status'] = 'past_due';
    $remote['latest_invoice']['status'] = 'open';
    fakeBillingStripe(fn () => $remote);
    signedBillingEvent($remote)->assertOk();
    expect($site->fresh()->hasPaidDomainAccess())->toBeFalse()->and($domain->fresh()->isReadyToServe())->toBeFalse()
        ->and($domain->fresh()->cloudflare_id)->toBe('managed')->and($domain->fresh()->state)->toBe('pending');
});
