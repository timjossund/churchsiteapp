<?php

use App\Actions\DeleteAccountWhenBillingEnds;
use App\Actions\FinalizeSiteDeletion;
use App\Actions\RequestSiteDeletion;
use App\Actions\ReserveCustomHostname;
use App\Models\Site;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToDeleteFile;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;

function fakeSiteCleanupStripe(Closure $handler): void
{
    config(['cashier.secret' => 'sk_test_fake']);
    $http = Mockery::mock(ClientInterface::class);
    $http->shouldReceive('request')->andReturnUsing(function ($method, $url, $headers, $params) use ($handler) {
        return [json_encode($handler($method, parse_url($url, PHP_URL_PATH), $params, $headers), JSON_THROW_ON_ERROR), 200, []];
    });
    ApiRequestor::setHttpClient($http);
}

function requestCleanupFor(Site $site): void
{
    app(RequestSiteDeletion::class)->handle($site->user, $site->id, $site->name);
}

beforeEach(function () {
    Http::preventStrayRequests();
    fakeSiteCleanupStripe(fn () => throw new RuntimeException('Unexpected Stripe request'));
});

afterEach(function () {
    ApiRequestor::setHttpClient(new CurlClient);
});

test('site finalization is opt in and removes only the requested sites inventory', function () {
    $disk = Storage::fake('s3');
    $site = Site::factory()->create();
    $other = Site::factory()->for($site->user)->create();
    foreach ([$site, $other] as $target) {
        $key = "sites/{$target->id}/photo.png";
        $target->mediaAssets()->create(['storage_key' => $key, 'mime_type' => 'image/png']);
        $disk->put($key, 'image');
    }
    $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/already-missing.png", 'mime_type' => 'image/png']);
    $action = app(FinalizeSiteDeletion::class);
    expect($action->handle($site->id))->toBeFalse();
    requestCleanupFor($site);
    $this->artisan('sites:finalize-deletions')->assertSuccessful();
    expect($site->fresh())->toBeNull()->and($other->fresh())->not->toBeNull()
        ->and($other->mediaAssets()->count())->toBe(1)->and($other->pages()->count())->toBe(1)
        ->and($site->user->fresh())->not->toBeNull()->and($action->handle($site->id))->toBeTrue();
    $disk->assertMissing("sites/{$site->id}/photo.png");
    $disk->assertExists("sites/{$other->id}/photo.png");
});

test('uncertain storage cleanup retains the inventory until a confirmed retry', function (string $failure) {
    $site = Site::factory()->create();
    $key = "sites/{$site->id}/photo.png";
    $asset = $site->mediaAssets()->create(['storage_key' => $key, 'mime_type' => 'image/png']);
    requestCleanupFor($site);
    $disk = Mockery::mock(FilesystemAdapter::class);
    Storage::shouldReceive('disk')->with('s3')->andReturn($disk);
    $retry = false;
    $disk->shouldReceive('exists')->with($key)->andReturnUsing(function () use (&$retry, $failure, $key) {
        if ($retry) {
            return false;
        }
        if ($failure === 'exists') {
            throw UnableToCheckFileExistence::forLocation($key);
        }

        return true;
    });
    if ($failure === 'exists') {
        $disk->shouldNotReceive('delete');
    } else {
        $deletion = $disk->shouldReceive('delete')->once()->with($key);
        if ($failure === 'throw') {
            $deletion->andThrow(UnableToDeleteFile::atLocation($key));
        } else {
            $deletion->andReturnFalse();
        }
    }
    expect(app(FinalizeSiteDeletion::class)->handle($site->id))->toBeFalse()
        ->and($asset->fresh())->not->toBeNull()->and($site->fresh()->deletion_requested_at)->not->toBeNull();
    $retry = true;
    expect(app(FinalizeSiteDeletion::class)->handle($site->id))->toBeTrue()->and($asset->fresh())->toBeNull();
})->with(['false', 'throw', 'exists']);

test('site cleanup refuses inventory keys outside its safe storage prefix', function (string $suffix) {
    $site = Site::factory()->create();
    $key = $suffix === 'foreign' ? 'sites/999999/photo.png' : "sites/{$site->id}/{$suffix}";
    $asset = $site->mediaAssets()->create(['storage_key' => $key, 'mime_type' => 'image/png']);
    requestCleanupFor($site);
    Storage::shouldReceive('disk')->never();
    expect(app(FinalizeSiteDeletion::class)->handle($site->id))->toBeFalse()
        ->and($asset->fresh())->not->toBeNull();
})->with(['foreign', '../another/photo.png', './photo.png', 'folder/../../photo.png', 'folder\\photo.png', '']);

test('paid site cleanup stops renewal once and removes assets while the paid period remains', function () {
    $this->freezeTime();
    $disk = Storage::fake('s3');
    $site = Site::factory()->create();
    $site->forceFill(['stripe_id' => 'cus_site'])->save();
    $site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_site', 'stripe_status' => 'active', 'paid_until' => now()->addDay()]);
    $domain = app(ReserveCustomHostname::class)->handle($site->user, $site->id, 'www.example.org');
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/photo.png", 'mime_type' => 'image/png']);
    $disk->put($asset->storage_key, 'image');
    requestCleanupFor($site);
    $remote = ['id' => 'sub_site', 'object' => 'subscription', 'customer' => 'cus_site', 'status' => 'active', 'cancel_at_period_end' => false, 'cancel_at' => now()->addDay()->timestamp];
    $updates = 0;
    fakeSiteCleanupStripe(function ($method, $path, $params) use (&$remote, &$updates) {
        if ($path === '/v1/checkout/sessions' || $path === '/v1/subscriptions') {
            expect($params['customer'])->toBe('cus_site');

            return ['object' => 'list', 'data' => $path === '/v1/subscriptions' ? [$remote] : [], 'has_more' => false];
        }
        expect($path)->toBe('/v1/subscriptions/sub_site')->and($method)->toBe('post')
            ->and(array_keys($params))->toBe(['cancel_at_period_end']);
        $updates++;
        $remote['cancel_at_period_end'] = true;

        return $remote;
    });
    $action = app(FinalizeSiteDeletion::class);
    expect($action->handle($site->id))->toBeFalse()->and($action->handle($site->id))->toBeFalse()
        ->and($updates)->toBe(1)->and($domain->fresh())->toBeNull()->and($asset->fresh())->toBeNull()
        ->and($site->fresh()->hasPaidDomainAccess())->toBeFalse();
    $disk->assertMissing($asset->storage_key);
    $remote['status'] = 'canceled';
    expect($action->handle($site->id))->toBeFalse();
    $this->travel(2)->days();
    expect($action->handle($site->id))->toBeTrue()->and($site->fresh())->toBeNull()
        ->and($site->subscriptions()->count())->toBe(0)->and($updates)->toBe(1);
});

test('lost checkout expiration responses and unresolved payments keep deletion offline for retry', function () {
    $site = Site::factory()->create();
    $site->forceFill(['stripe_id' => 'cus_site'])->save();
    requestCleanupFor($site);
    $status = 'open';
    fakeSiteCleanupStripe(function ($method, $path) use (&$status) {
        if ($path === '/v1/checkout/sessions/cs_site/expire') {
            $status = 'expired';
            throw new ApiConnectionException('Response lost after expiration');
        }

        return ['object' => 'list', 'data' => $path === '/v1/checkout/sessions' ? [['id' => 'cs_site', 'status' => $status, 'payment_status' => 'unpaid', 'subscription' => null]] : [], 'has_more' => false];
    });
    $action = app(FinalizeSiteDeletion::class);
    expect($action->handle($site->id))->toBeFalse()->and($site->fresh()->stripe_id)->toBe('cus_site');
    $status = 'complete';
    expect($action->handle($site->id))->toBeFalse()->and($site->fresh()->published_at)->toBeNull();
    $status = 'expired';
    expect($action->handle($site->id))->toBeTrue();
});

test('recovered Stripe customer identity survives a later outage and is reused', function () {
    $site = Site::factory()->create();
    $site->forceFill(['checkout_attempt' => 'lost-response', 'checkout_started_at' => now()])->save();
    requestCleanupFor($site);
    $customers = 0;
    $outage = true;
    fakeSiteCleanupStripe(function ($method, $path, $params, $headers) use (&$customers, &$outage) {
        if ($path === '/v1/customers') {
            $customers++;
            expect(implode(' ', $headers))->toContain('site-customer-lost-response');

            return ['id' => 'cus_recovered', 'object' => 'customer'];
        }
        expect($params['customer'])->toBe('cus_recovered');
        if ($outage) {
            throw new ApiConnectionException('Temporary outage');
        }

        return ['object' => 'list', 'data' => [], 'has_more' => false];
    });
    expect(app(FinalizeSiteDeletion::class)->handle($site->id))->toBeFalse()
        ->and($site->fresh()->stripe_id)->toBe('cus_recovered');
    $outage = false;
    expect(app(FinalizeSiteDeletion::class)->handle($site->id))->toBeTrue()->and($customers)->toBe(1);
});

test('stale unknown Stripe customer identity requires reconciliation without creating a new customer', function () {
    $site = Site::factory()->create();
    $site->forceFill(['checkout_attempt' => 'lost-response', 'checkout_started_at' => now()->subDay()])->save();
    requestCleanupFor($site);
    expect(app(FinalizeSiteDeletion::class)->handle($site->id))->toBeFalse()
        ->and($site->fresh()->checkout_attempt)->toBe('lost-response');
});

test('failed domain removal retains identity and succeeds after a retry', function () {
    config(['customer-domains.zone_id' => str_repeat('a', 32), 'customer-domains.api_token' => 'fake']);
    $site = Site::factory()->create();
    $domain = app(ReserveCustomHostname::class)->handle($site->user, $site->id, 'www.example.org');
    $providerId = '11111111-1111-4111-8111-111111111111';
    $domain->forceFill(['cloudflare_id' => $providerId, 'cloudflare_zone_id' => str_repeat('a', 32), 'provision_started_at' => now()])->save();
    requestCleanupFor($site);
    Http::fake(['*' => Http::response([], 503)]);
    expect(app(FinalizeSiteDeletion::class)->handle($site->id))->toBeFalse()
        ->and($domain->fresh()->cloudflare_id)->toBe($providerId)->and($domain->fresh()->state)->toBe('removing');
    Http::swap(new Factory);
    Http::fakeSequence()->push(['success' => true, 'result' => [['id' => $providerId, 'hostname' => $domain->hostname]], 'result_info' => ['total_count' => 1]])
        ->push(['success' => true, 'result' => ['id' => $providerId]]);
    expect(app(FinalizeSiteDeletion::class)->handle($site->id))->toBeTrue()->and($domain->fresh())->toBeNull();
});

test('unknown domain creation response keeps site cleanup pending for operator resolution', function () {
    config(['customer-domains.zone_id' => str_repeat('a', 32), 'customer-domains.api_token' => 'fake']);
    $site = Site::factory()->create();
    $domain = app(ReserveCustomHostname::class)->handle($site->user, $site->id, 'www.example.org');
    $domain->forceFill(['cloudflare_zone_id' => str_repeat('a', 32), 'provision_started_at' => now()])->save();
    requestCleanupFor($site);
    Http::fake(['*' => Http::response(['success' => true, 'result' => [], 'result_info' => ['total_count' => 0]])]);
    expect(app(FinalizeSiteDeletion::class)->handle($site->id))->toBeFalse()
        ->and($domain->fresh()->error_category)->toBe('operator_required')->and($site->fresh())->not->toBeNull();
});

test('account deletion cannot cascade inventory while site cleanup is pending', function () {
    $site = Site::factory()->create();
    $user = $site->user;
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/photo.png", 'mime_type' => 'image/png']);
    requestCleanupFor($site);
    $removed = false;
    $disk = Mockery::mock(FilesystemAdapter::class);
    Storage::shouldReceive('disk')->with('s3')->andReturn($disk);
    $disk->shouldReceive('exists')->with($asset->storage_key)->andReturnTrue();
    $disk->shouldReceive('delete')->with($asset->storage_key)->andReturnUsing(function () use (&$removed) {
        return $removed;
    });
    expect(app(DeleteAccountWhenBillingEnds::class)->request($user))->toBeFalse()
        ->and($user->fresh())->not->toBeNull()->and($asset->fresh())->not->toBeNull();
    $removed = true;
    expect(app(DeleteAccountWhenBillingEnds::class)->finalize($user->id))->toBeTrue()
        ->and($user->fresh())->toBeNull()->and($asset->fresh())->toBeNull();
});

test('billing confirmed during file cleanup prevents final site deletion', function () {
    $site = Site::factory()->create();
    $site->forceFill(['stripe_id' => 'cus_site'])->save();
    $subscription = $site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_site', 'stripe_status' => 'canceled', 'paid_until' => now()->subDay()]);
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/photo.png", 'mime_type' => 'image/png']);
    requestCleanupFor($site);
    fakeSiteCleanupStripe(fn () => ['object' => 'list', 'data' => [], 'has_more' => false]);
    $disk = Mockery::mock(FilesystemAdapter::class);
    Storage::shouldReceive('disk')->with('s3')->andReturn($disk);
    $disk->shouldReceive('exists')->with($asset->storage_key)->andReturnTrue();
    $disk->shouldReceive('delete')->with($asset->storage_key)->andReturnUsing(function () use ($subscription) {
        $subscription->forceFill(['paid_until' => now()->addMonth()])->save();

        return true;
    });
    expect(app(FinalizeSiteDeletion::class)->handle($site->id))->toBeFalse()
        ->and($site->fresh())->not->toBeNull()->and($asset->fresh())->toBeNull()
        ->and($site->fresh()->hasPaidDomainAccess())->toBeFalse();
});

test('unexpected cleanup failures remain visible and preserve the deletion request', function () {
    $site = Site::factory()->create();
    $site->forceFill(['stripe_id' => 'cus_site'])->save();
    requestCleanupFor($site);
    try {
        app(FinalizeSiteDeletion::class)->handle($site->id);
        $this->fail('Unexpected failures must propagate');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Unexpected Stripe request')
            ->and($site->fresh()->deletion_requested_at)->not->toBeNull();
    }
});

test('a lost renewal cancellation response converges on retry without a second cancellation', function () {
    $site = Site::factory()->create();
    $site->forceFill(['stripe_id' => 'cus_site'])->save();
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/photo.png", 'mime_type' => 'image/png']);
    $disk = Storage::fake('s3');
    $disk->put($asset->storage_key, 'image');
    requestCleanupFor($site);
    $canceled = false;
    $updates = 0;
    fakeSiteCleanupStripe(function ($method, $path) use (&$canceled, &$updates) {
        if ($path === '/v1/checkout/sessions') {
            return ['object' => 'list', 'data' => [], 'has_more' => false];
        }
        if ($method === 'post') {
            expect($path)->toBe('/v1/subscriptions/sub_site');
            $updates++;
            $canceled = true;
            throw new ApiConnectionException('Response lost');
        }

        return ['object' => 'list', 'data' => [[
            'id' => 'sub_site', 'object' => 'subscription', 'customer' => 'cus_site',
            'status' => 'active', 'cancel_at_period_end' => $canceled, 'cancel_at' => now()->addDay()->timestamp,
        ]], 'has_more' => false];
    });
    $action = app(FinalizeSiteDeletion::class);
    expect($action->handle($site->id))->toBeFalse()->and($asset->fresh())->toBeNull();
    $disk->assertMissing($asset->storage_key);
    expect($action->handle($site->id))->toBeFalse()->and($updates)->toBe(1)
        ->and($site->fresh()->stripe_id)->toBe('cus_site')->and($site->fresh()->deletion_requested_at)->not->toBeNull();
});
