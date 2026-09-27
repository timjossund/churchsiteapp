<?php

use App\Actions\DisconnectCustomHostname;
use App\Actions\ReconcileCustomHostname;
use App\Actions\ReserveCustomHostname;
use App\Exceptions\DomainProvisioningFailed;
use App\Models\CustomHostname;
use App\Models\Site;
use App\Models\User;
use App\Support\DomainDnsResolver;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

function domainProviderResult(CustomHostname $domain, string $status = 'active', string $ssl = 'active'): array
{
    return [
        'id' => '11111111-1111-4111-8111-111111111111', 'hostname' => $domain->hostname,
        'status' => $status,
        'ownership_verification' => ['type' => 'txt', 'name' => '_cf-custom-hostname.'.$domain->hostname, 'value' => 'provider-proof'],
        'ssl' => ['status' => $ssl, 'validation_records' => [
            ['txt_name' => '_acme-challenge.'.$domain->hostname, 'txt_value' => 'certificate-proof'],
            ['cname' => '_acme-challenge.'.$domain->hostname, 'cname_target' => 'dcv.cloudflare.com'],
        ]],
        'verification_errors' => ['raw-private-provider-error'],
    ];
}

function domainListResponse(array $rows = []): array
{
    return ['success' => true, 'result' => $rows, 'result_info' => ['total_count' => count($rows)]];
}

beforeEach(function () {
    config(['customer-domains.enabled' => true, 'customer-domains.zone_id' => str_repeat('a', 32), 'customer-domains.api_token' => 'test-private-token']);
    Http::preventStrayRequests();
    $this->site = Site::factory()->create();
    $this->subscription = $this->site->subscriptions()->create([
        'type' => 'default', 'stripe_id' => 'sub_'.Str::uuid(), 'stripe_status' => 'active', 'paid_until' => now()->addMonth(),
    ]);
    $this->domain = app(ReserveCustomHostname::class)->handle($this->site->user, $this->site->id, 'www.example.org');
    $this->dns = Mockery::mock(DomainDnsResolver::class);
    $this->dns->shouldReceive('txt')->with($this->domain->ownershipRecordName())->andReturn([$this->domain->ownership_challenge])->byDefault();
    $this->dns->shouldReceive('cnames')->with($this->domain->hostname)->andReturn(['customers.churchsite.app'])->byDefault();
    $this->app->instance(DomainDnsResolver::class, $this->dns);
});

it('creates once using TXT certificates and stores only validated instructions', function () {
    $remote = domainProviderResult($this->domain);
    Http::fakeSequence()->push(domainListResponse())->push(['success' => true, 'result' => $remote])->push(['success' => true, 'result' => $remote]);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    $domain = $this->domain->fresh();
    expect($domain->state)->toBe('ready')->and($domain->verified_at)->not->toBeNull()
        ->and($domain->cname_matches)->toBeTrue()->and($domain->dns_instructions)->toHaveCount(3)
        ->and($domain->dns_instructions[0])->toBe(['purpose' => 'hostname', 'type' => 'TXT', 'name' => '_cf-custom-hostname.www.example.org', 'value' => 'provider-proof'])
        ->and($domain->dns_instructions[1]['purpose'])->toBe('certificate')
        ->and(json_encode($domain->getAttributes()))->not->toContain('raw-private-provider-error', 'test-private-token')
        ->and($domain->check_id)->toBeNull();
    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->data() === ['hostname' => 'www.example.org', 'ssl' => ['method' => 'txt', 'type' => 'dv']]
        && $request->hasHeader('Authorization', 'Bearer test-private-token'));
});

it('requires all four readiness conditions', function (string $hostnameStatus, string $sslStatus, bool $ownership, bool $cname) {
    $this->dns->shouldReceive('txt')->andReturn($ownership ? [$this->domain->ownership_challenge] : ['wrong-account-challenge']);
    $this->dns->shouldReceive('cnames')->andReturn($cname ? ['customers.churchsite.app'] : ['wrong.example.org']);
    Http::fakeSequence()->push(domainListResponse())->push(['success' => true, 'result' => domainProviderResult($this->domain, $hostnameStatus, $sslStatus)]);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->state)->toBe('pending')
        ->and($this->domain->fresh()->hostname_status)->toBe($hostnameStatus)
        ->and($this->domain->fresh()->ssl_status)->toBe($sslStatus);
})->with([
    ['pending', 'active', true, true], ['active', 'pending_validation', true, true], ['blocked', 'active', true, true],
    ['active', 'validation_timed_out', true, true], ['future_status', 'active', true, true],
    ['active', 'active', false, true], ['active', 'active', true, false],
]);

it('clears previous readiness after fresh negative evidence or expected DNS failure', function (string $failure) {
    $remote = domainProviderResult($this->domain);
    Http::fakeSequence()->push(domainListResponse())->push(['success' => true, 'result' => $remote])->push(['success' => true, 'result' => $remote]);
    $action = app(ReconcileCustomHostname::class);
    $action->handle($this->domain->id);
    expect($this->domain->fresh()->state)->toBe('ready');
    if ($failure === 'dns') {
        $this->dns->shouldReceive('txt')->andThrow(new DomainProvisioningFailed('dns_unavailable'));
    } else {
        $this->dns->shouldReceive($failure)->andReturn([]);
    }
    $action->handle($this->domain->id);
    expect($this->domain->fresh()->state)->toBe('pending');
})->with(['txt', 'cnames', 'dns']);

it('retains an uncertain create without replaying or adopting the matching name', function (bool $found) {
    $remote = domainProviderResult($this->domain);
    Http::fakeSequence()->push(domainListResponse())->pushResponse(Http::failedConnection())
        ->push(domainListResponse($found ? [$remote] : []));
    $action = app(ReconcileCustomHostname::class);
    $action->handle($this->domain->id);
    expect($this->domain->fresh()->provision_started_at)->not->toBeNull()
        ->and($this->domain->fresh()->error_category)->toBe('provider_unavailable');
    $action->handle($this->domain->id);
    expect($this->domain->fresh()->cloudflare_id)->toBeNull()
        ->and($this->domain->fresh()->error_category)->toBe('operator_required');
    Http::assertSentCount(3);
    expect(Http::recorded(fn (Request $request) => $request->method() === 'POST'))->toHaveCount(1);
    Http::assertNotSent(fn (Request $request) => $request->method() === 'DELETE');
})->with([true, false]);

it('does not adopt or delete an existing manual provider hostname', function () {
    Http::fake(['*' => Http::response(domainListResponse([domainProviderResult($this->domain)]))]);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->error_category)->toBe('operator_required')
        ->and($this->domain->fresh()->provision_started_at)->toBeNull()->and($this->domain->fresh()->cloudflare_id)->toBeNull();
    Http::assertSentCount(1);
});

it('classifies provider failures without retaining raw errors', function (int $status, string $category) {
    Http::fake(['*' => Http::response(['success' => false, 'errors' => [['message' => 'raw-secret-token']]], $status)]);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->error_category)->toBe($category)
        ->and(json_encode($this->domain->fresh()->getAttributes()))->not->toContain('raw-secret-token');
})->with([[429, 'rate_limited'], [403, 'configuration'], [401, 'configuration'], [503, 'provider_unavailable'], [400, 'provider_rejected'], [302, 'provider_rejected']]);

it('makes no request with missing credentials or rollout disabled', function (string $key, mixed $value) {
    config([$key => $value]);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    Http::assertNothingSent();
    expect($this->domain->fresh()->state)->not->toBe('ready');
})->with([['customer-domains.zone_id', null], ['customer-domains.api_token', null], ['customer-domains.enabled', false]]);

it('rejects malformed provider data and retains create identity for cleanup', function (string $field) {
    $remote = domainProviderResult($this->domain);
    match ($field) {
        'id' => $remote['id'] = '../bad',
        'hostname' => $remote['hostname'] = 'www.other.org',
        'status' => $remote['status'] = ['active'],
        'ssl' => $remote['ssl'] = 'bad',
        'record' => $remote['ssl']['validation_records'][0]['txt_value'] = "line\nbreak",
    };
    Http::fakeSequence()->push(domainListResponse())->push(['success' => true, 'result' => $remote]);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->error_category)->toBe('provider_response')
        ->and($this->domain->fresh()->verified_at)->toBeNull()
        ->and($this->domain->fresh()->provision_started_at)->not->toBeNull();
    if (! in_array($field, ['id', 'hostname'])) {
        expect($this->domain->fresh()->cloudflare_id)->toBe($remote['id']);
    }
})->with(['id', 'hostname', 'status', 'ssl', 'record']);

it('requires paid access before provisioning and retains configuration on expiry', function () {
    $this->subscription->forceFill(['paid_until' => now()->subMinute()])->save();
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    Http::assertNothingSent();
    expect($this->domain->fresh())->not->toBeNull();
});

it('does not create provider resources for deletion-pending owners', function () {
    $this->site->user->forceFill(['deletion_requested_at' => now()])->save();
    Http::fake(['*' => Http::response(domainListResponse())]);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
});

it('saves a late create identity for removal without reviving a disconnected name', function () {
    $remote = domainProviderResult($this->domain);
    Http::fake(function (Request $request) use ($remote) {
        expect(DB::transactionLevel())->toBe(1); // RefreshDatabase's outer test transaction only.
        if ($request->method() === 'POST') {
            app(DisconnectCustomHostname::class)->handle($this->site->user, $this->site->id);

            return Http::response(['success' => true, 'result' => $remote]);
        }

        return Http::response(domainListResponse());
    });
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->cloudflare_id)->toBe($remote['id'])
        ->and($this->domain->fresh()->state)->toBe('removing')->and($this->domain->fresh()->verified_at)->toBeNull();
});

it('never applies a stale response over a newer check generation', function () {
    $remote = domainProviderResult($this->domain);
    $this->domain->forceFill(['cloudflare_id' => $remote['id'], 'cloudflare_zone_id' => str_repeat('a', 32)])->save();
    $newCheck = (string) Str::uuid();
    Http::fake(function () use ($remote, $newCheck) {
        $this->domain->forceFill(['check_id' => $newCheck, 'state' => 'pending', 'error_category' => 'dns_unavailable'])->save();

        return Http::response(['success' => true, 'result' => $remote]);
    });
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->state)->toBe('pending')->and($this->domain->fresh()->check_id)->toBe($newCheck)
        ->and($this->domain->fresh()->error_category)->toBe('dns_unavailable');
});

it('skips overlapping work but recovers an expired database lease', function () {
    $this->domain->forceFill(['check_id' => (string) Str::uuid(), 'check_started_at' => now()])->save();
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    Http::assertNothingSent();
    $this->travel(3)->minutes();
    Http::fakeSequence()->push(domainListResponse())->push(['success' => true, 'result' => domainProviderResult($this->domain)]);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->state)->toBe('ready')->and($this->domain->fresh()->check_id)->toBeNull();
});

it('retries removal by exact managed ID and releases after confirmed absence', function () {
    $remote = domainProviderResult($this->domain);
    $this->domain->forceFill(['cloudflare_id' => $remote['id'], 'cloudflare_zone_id' => str_repeat('a', 32), 'provision_started_at' => now()])->save();
    app(DisconnectCustomHostname::class)->handle($this->site->user, $this->site->id);
    Http::fakeSequence()->push(domainListResponse([$remote]))->pushResponse(Http::failedConnection())->push(domainListResponse());
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->state)->toBe('removing');
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh())->toBeNull();
});

it('releases a confirmed successful removal and never deletes a different remote ID', function (bool $matches) {
    $remote = domainProviderResult($this->domain);
    $this->domain->forceFill(['cloudflare_id' => $matches ? $remote['id'] : (string) Str::uuid(), 'cloudflare_zone_id' => str_repeat('a', 32), 'provision_started_at' => now()])->save();
    app(DisconnectCustomHostname::class)->handle($this->site->user, $this->site->id);
    Http::fakeSequence()->push(domainListResponse([$remote]))->push(['success' => true, 'result' => ['id' => $remote['id']]]);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    if ($matches) {
        expect($this->domain->fresh())->toBeNull();
        Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/'.$remote['id']));
    } else {
        expect($this->domain->fresh()->error_category)->toBe('operator_required');
        Http::assertNotSent(fn (Request $request) => $request->method() === 'DELETE');
    }
})->with([true, false]);

it('does not release uncertain create reservations during disconnect', function () {
    $this->domain->forceFill(['provision_started_at' => now(), 'cloudflare_zone_id' => str_repeat('a', 32)])->save();
    app(DisconnectCustomHostname::class)->handle($this->site->user, $this->site->id);
    Http::fake(['*' => Http::response(domainListResponse())]);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->state)->toBe('removing')->and($this->domain->fresh()->error_category)->toBe('operator_required');
});

it('releases a never-provisioned reservation locally', function () {
    app(DisconnectCustomHostname::class)->handle($this->site->user, $this->site->id);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh())->toBeNull();
    Http::assertNothingSent();
});

it('rejects changed zone configuration without remote writes', function () {
    $this->domain->forceFill(['cloudflare_zone_id' => str_repeat('b', 32), 'provision_started_at' => now()])->save();
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->error_category)->toBe('operator_required');
    Http::assertNothingSent();
});

it('protects and throttles owner checks', function () {
    $url = route('sites.domain.check', $this->site);
    $this->post($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->post($url)->assertNotFound();
    $this->subscription->forceFill(['paid_until' => now()->subDay()])->save();
    $this->actingAs($this->site->user);
    for ($i = 0; $i < 6; $i++) {
        $this->post($url)->assertRedirect();
    }
    $this->post($url)->assertStatus(429);
    Http::assertNothingSent();
});

it('bounds the scheduled batch and configures five-minute overlap protection', function () {
    $this->subscription->forceFill(['paid_until' => now()->subDay()])->save();
    Site::factory()->count(51)->create()->each(function (Site $site) {
        app(ReserveCustomHostname::class)->handle($site->user, $site->id, 'www.church'.$site->id.'.org');
    });
    $this->artisan('domains:reconcile')->assertSuccessful();
    expect(CustomHostname::whereNotNull('last_checked_at')->count())->toBe(50);
    $event = collect(Schedule::events())->first(fn ($event) => str_contains($event->command ?? '', 'domains:reconcile'));
    expect($event->expression)->toBe('*/5 * * * *')->and($event->withoutOverlapping)->toBeTrue();
    $this->artisan('domains:reconcile')->assertSuccessful();
    expect(CustomHostname::whereNull('last_checked_at')->count())->toBe(0);
    Http::assertNothingSent();
});

it('does not swallow unexpected programming failures', function () {
    Http::fake(fn () => throw new LogicException('unexpected'));
    expect(fn () => app(ReconcileCustomHostname::class)->handle($this->domain->id))->toThrow(LogicException::class);
    expect($this->domain->fresh()->check_id)->toBeNull();
});

it('loses readiness when provider status regresses and reuses the same managed record', function () {
    Http::fakeSequence()->push(domainListResponse())
        ->push(['success' => true, 'result' => domainProviderResult($this->domain)])
        ->push(['success' => true, 'result' => domainProviderResult($this->domain, 'active', 'pending_validation')]);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->state)->toBe('ready');
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->state)->toBe('pending')->and($this->domain->fresh()->ssl_status)->toBe('pending_validation');
    expect(Http::recorded(fn (Request $request) => $request->method() === 'POST'))->toHaveCount(1);
});

it('fails closed on malformed lists rather than treating them as absence', function (array $body) {
    Http::fake(['*' => Http::response($body)]);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->error_category)->toBe('provider_response');
    Http::assertSentCount(1);
    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
})->with([
    [['success' => true, 'result' => []]],
    [['success' => false, 'result' => [], 'result_info' => ['total_count' => 0]]],
    [['success' => true, 'result' => [], 'result_info' => ['total_count' => 1]]],
]);

it('retains the reservation on unconfirmed removal acknowledgements', function () {
    $remote = domainProviderResult($this->domain);
    $this->domain->forceFill(['cloudflare_id' => $remote['id'], 'cloudflare_zone_id' => str_repeat('a', 32), 'provision_started_at' => now()])->save();
    app(DisconnectCustomHostname::class)->handle($this->site->user, $this->site->id);
    Http::fakeSequence()->push(domainListResponse([$remote]))->push(['success' => true, 'result' => ['id' => 'wrong-id']]);
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->state)->toBe('removing')->and($this->domain->fresh()->error_category)->toBe('provider_response');
});

it('ignores read responses after disconnect and rejects mismatched provider identities', function (bool $disconnect) {
    $remote = domainProviderResult($this->domain);
    $this->domain->forceFill(['cloudflare_id' => $remote['id'], 'cloudflare_zone_id' => str_repeat('a', 32)])->save();
    Http::fake(function () use ($remote, $disconnect) {
        if ($disconnect) {
            app(DisconnectCustomHostname::class)->handle($this->site->user, $this->site->id);
        } else {
            $remote['id'] = (string) Str::uuid();
        }

        return Http::response(['success' => true, 'result' => $remote]);
    });
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->state)->toBe($disconnect ? 'removing' : 'pending')
        ->and($this->domain->fresh()->verified_at)->toBeNull()
        ->and($this->domain->fresh()->cloudflare_id)->toBe($remote['id']);
})->with([true, false]);

it('retains a successful create identity when billing invalidates its readiness lease', function () {
    $remote = domainProviderResult($this->domain);
    Http::fake(function (Request $request) use ($remote) {
        if ($request->method() === 'POST') {
            // The billing webhook revokes this in-flight check when paid access changes.
            $this->domain->fresh()->forceFill(['state' => 'pending', 'check_id' => null, 'check_started_at' => null])->save();

            return Http::response(['success' => true, 'result' => $remote]);
        }

        return Http::response(domainListResponse());
    });
    app(ReconcileCustomHostname::class)->handle($this->domain->id);
    expect($this->domain->fresh()->cloudflare_id)->toBe($remote['id'])
        ->and($this->domain->fresh()->state)->toBe('pending')->and($this->domain->fresh()->verified_at)->toBeNull();
});
