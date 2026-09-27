<?php

use App\Actions\DeleteAccountWhenBillingEnds;
use App\Actions\StartSiteCheckout;
use App\Models\Site;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

function fakeDeletionStripe(Closure $handler): void
{
    config(['cashier.secret' => 'sk_test_fake']);
    $http = Mockery::mock(ClientInterface::class);
    $http->shouldReceive('request')->andReturnUsing(function ($method, $url, $headers, $params) use ($handler) {
        return [json_encode($handler($method, parse_url($url, PHP_URL_PATH), $params), JSON_THROW_ON_ERROR), 200, []];
    });
    ApiRequestor::setHttpClient($http);
}

function deletionSubscription(string $id, string $customer, int $end): array
{
    return ['id' => $id, 'object' => 'subscription', 'customer' => $customer, 'status' => 'active', 'cancel_at_period_end' => false, 'cancel_at' => null, 'items' => ['data' => [['current_period_end' => $end]]]];
}

afterEach(function () {
    ApiRequestor::setHttpClient(new CurlClient);
});

test('deletion cancels every renewal and waits for the final subscription end', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $sites = Site::factory()->for($user)->count(2)->create();
    $remote = [];
    foreach ($sites as $i => $site) {
        $site->forceFill(['stripe_id' => 'cus_'.$i])->save();
        $site->subscriptions()->create([
            'type' => 'default', 'stripe_id' => 'sub_'.$i, 'stripe_status' => 'active',
            'paid_until' => now()->addDays(($i + 1) * 10),
        ]);
        $remote['sub_'.$i] = deletionSubscription('sub_'.$i, 'cus_'.$i, now()->addDays(($i + 1) * 10)->timestamp);
    }
    $updates = [];
    fakeDeletionStripe(function ($method, $path, $params) use (&$remote, &$updates) {
        if ($path === '/v1/checkout/sessions') {
            return ['object' => 'list', 'data' => [], 'has_more' => false];
        }
        if ($path === '/v1/subscriptions') {
            return ['object' => 'list', 'data' => array_values(array_filter($remote, fn ($sub) => $sub['customer'] === $params['customer'])), 'has_more' => false];
        }
        $id = basename($path);
        $updates[] = $id;
        expect(array_keys($params))->toBe(['cancel_at_period_end']);
        expect(filter_var($params['cancel_at_period_end'], FILTER_VALIDATE_BOOLEAN))->toBeTrue();
        $remote[$id]['cancel_at_period_end'] = true;

        return $remote[$id];
    });
    $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect(route('profile.edit'));
    $this->assertAuthenticatedAs($user);
    expect($updates)->toBe(['sub_0', 'sub_1'])
        ->and($user->fresh()->deletion_scheduled_for->timestamp)->toBe(now()->addDays(20)->timestamp)
        ->and($user->fresh()->sites()->count())->toBe(2)
        ->and($sites[0]->fresh()->hasPaidDomainAccess())->toBeTrue();
    $this->actingAs($user->fresh())->get(route('profile.edit'))->assertInertia(fn ($page) => $page
        ->component('settings/Profile')
        ->where('deletionRequestedAt', $user->fresh()->deletion_requested_at->toIso8601String())
        ->where('deletionScheduledFor', $user->fresh()->deletion_scheduled_for->toIso8601String()));
    $this->artisan('accounts:finalize-deletions')->assertSuccessful();
    expect($updates)->toHaveCount(2);
    $this->travel(11)->days();
    $remote['sub_0']['status'] = 'canceled';
    $this->artisan('accounts:finalize-deletions')->assertSuccessful();
    expect($user->fresh())->not->toBeNull();
    $this->travel(10)->days();
    $remote['sub_1']['status'] = 'canceled';
    $this->artisan('accounts:finalize-deletions')->assertSuccessful();
    $this->artisan('accounts:finalize-deletions')->assertSuccessful();
    expect($user->fresh())->toBeNull()->and(Site::query()->count())->toBe(0);
});

test('a partial Stripe failure keeps deletion pending but still cancels other sites', function () {
    $user = User::factory()->create();
    $sites = Site::factory()->for($user)->count(2)->create();
    foreach ($sites as $i => $site) {
        $site->forceFill(['stripe_id' => 'cus_'.$i])->save();
    }
    $fail = true;
    $canceled = [];
    fakeDeletionStripe(function ($method, $path, $params) use (&$fail, &$canceled) {
        if ($path === '/v1/checkout/sessions') {
            if ($params['customer'] === 'cus_0' && $fail) {
                throw new ApiConnectionException('outage');
            }

            return ['object' => 'list', 'data' => [], 'has_more' => false];
        }
        if ($path === '/v1/subscriptions') {
            $i = substr($params['customer'], -1);
            $sub = deletionSubscription('sub_'.$i, 'cus_'.$i, now()->addDay()->timestamp);
            $sub['cancel_at_period_end'] = in_array('sub_'.$i, $canceled, true);

            return ['object' => 'list', 'data' => [$sub], 'has_more' => false];
        }
        $id = basename($path);
        $canceled[] = $id;
        $sub = deletionSubscription($id, 'cus_'.substr($id, -1), now()->addDay()->timestamp);
        $sub['cancel_at_period_end'] = true;

        return $sub;
    });
    expect(app(DeleteAccountWhenBillingEnds::class)->request($user))->toBeFalse()
        ->and($user->fresh()->deletion_requested_at)->not->toBeNull()
        ->and($user->fresh()->deletion_scheduled_for)->toBeNull()
        ->and($canceled)->toBe(['sub_1']);
    $fail = false;
    expect(app(DeleteAccountWhenBillingEnds::class)->finalize($user->id))->toBeFalse()
        ->and($canceled)->toBe(['sub_1', 'sub_0'])
        ->and($user->fresh()->deletion_scheduled_for)->not->toBeNull();
});

test('an elapsed local date does not delete an account while Stripe is unavailable', function () {
    $user = User::factory()->create();
    $user->forceFill(['deletion_requested_at' => now()->subDays(5), 'deletion_scheduled_for' => now()->subDay()])->save();
    Site::factory()->for($user)->create()->forceFill(['stripe_id' => 'cus_site'])->save();
    fakeDeletionStripe(fn () => throw new ApiConnectionException('outage'));
    $this->artisan('accounts:finalize-deletions')->assertSuccessful();
    expect($user->fresh())->not->toBeNull()->and($user->fresh()->deletion_scheduled_for)->toBeNull();
});

test('pending deletion blocks new checkout before any Stripe request', function () {
    $user = User::factory()->create();
    $user->forceFill(['deletion_requested_at' => now()])->save();
    $site = Site::factory()->for($user)->create();
    fakeDeletionStripe(fn () => throw new RuntimeException('Unexpected Stripe request'));
    app(StartSiteCheckout::class)->handle($user, $site->id, 'monthly');
})->throws(ValidationException::class);

test('deletion expires an open checkout before removing a non-subscribed account', function () {
    $user = User::factory()->create();
    Site::factory()->for($user)->create()->forceFill(['stripe_id' => 'cus_site'])->save();
    $expired = false;
    fakeDeletionStripe(function ($method, $path) use (&$expired) {
        if ($path === '/v1/checkout/sessions') {
            return ['object' => 'list', 'data' => [['id' => 'cs_site', 'status' => 'open']], 'has_more' => false];
        }
        if ($path === '/v1/checkout/sessions/cs_site/expire') {
            $expired = true;

            return ['id' => 'cs_site', 'object' => 'checkout.session', 'status' => 'expired'];
        }
        expect($expired)->toBeTrue();

        return ['object' => 'list', 'data' => [], 'has_more' => false];
    });
    expect(app(DeleteAccountWhenBillingEnds::class)->request($user))->toBeTrue()
        ->and($user->fresh())->toBeNull()->and($expired)->toBeTrue();
});

test('an unresolved completed payment holds deletion', function () {
    $user = User::factory()->create();
    Site::factory()->for($user)->create()->forceFill(['stripe_id' => 'cus_site'])->save();
    fakeDeletionStripe(fn ($method, $path) => ['object' => 'list', 'data' => $path === '/v1/checkout/sessions' ? [['id' => 'cs_site', 'status' => 'complete', 'payment_status' => 'unpaid', 'subscription' => null]] : [], 'has_more' => false]);
    expect(app(DeleteAccountWhenBillingEnds::class)->request($user))->toBeFalse()
        ->and($user->fresh())->not->toBeNull();
});

test('a finalizer never deletes an account without a deletion request', function () {
    $user = User::factory()->create();
    expect(app(DeleteAccountWhenBillingEnds::class)->finalize($user->id))->toBeFalse()
        ->and($user->fresh())->not->toBeNull();
});
