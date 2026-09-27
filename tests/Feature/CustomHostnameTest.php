<?php

use App\Actions\DisconnectCustomHostname;
use App\Actions\ReserveCustomHostname;
use App\Actions\VerifyCustomHostnameOwnership;
use App\Models\CustomHostname;
use App\Models\Site;
use App\Models\User;
use App\Support\DomainDnsResolver;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['customer-domains.enabled' => true]);
    Http::preventStrayRequests();
    $this->owner = User::factory()->create();
    $this->site = Site::factory()->for($this->owner)->create();
    $this->site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_test', 'stripe_status' => 'active', 'paid_until' => now()->addMonth()]);
});

function reserveTestHostname(User $owner, Site $site, string $hostname = 'www.example.org'): CustomHostname
{
    return app(ReserveCustomHostname::class)->handle($owner, $site->id, $hostname);
}

it('reserves normalized intent once with a random stable operation and ownership challenge', function () {
    $this->actingAs($this->owner)->post(route('sites.domain.store', $this->site), ['hostname' => ' WWW.Example.ORG '])->assertRedirect();
    $first = $this->site->customHostname()->firstOrFail();
    $this->post(route('sites.domain.store', $this->site), ['hostname' => 'www.example.org'])->assertRedirect();
    $second = $this->site->customHostname()->firstOrFail();
    expect($second->id)->toBe($first->id)
        ->and($second->operation_id)->toBe($first->operation_id)
        ->and(Str::isUuid($second->operation_id))->toBeTrue()
        ->and($second->ownership_challenge)->toMatch('/\A[a-f0-9]{64}\z/')
        ->and($second->verified_at)->toBeNull()
        ->and($second->state)->toBe('reserved')
        ->and($second->toArray())->toBe(['hostname' => 'www.example.org']);
    Http::assertNothingSent();
});

it('requires owner authentication for both operations', function () {
    $url = route('sites.domain.store', $this->site);
    $this->post($url, ['hostname' => 'www.example.org'])->assertRedirect(route('login'));
    $this->delete($url)->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->post($url, ['hostname' => 'www.example.org'])->assertNotFound();
    $this->delete($url)->assertNotFound();
    expect(CustomHostname::count())->toBe(0);
    Http::assertNothingSent();
});

it('leaves rollout disabled by default', function () {
    config(['customer-domains.enabled' => (require base_path('config/customer-domains.php'))['enabled']]);
    $this->actingAs($this->owner)->post(route('sites.domain.store', $this->site), ['hostname' => 'www.example.org'])->assertNotFound();
    $this->delete(route('sites.domain.destroy', $this->site))->assertNotFound();
    expect(CustomHostname::count())->toBe(0);
});

it('rejects invalid hostnames before external work', function (mixed $hostname) {
    $this->actingAs($this->owner)->postJson(route('sites.domain.store', $this->site), ['hostname' => $hostname])
        ->assertUnprocessable()->assertJsonValidationErrors('hostname');
    expect(CustomHostname::count())->toBe(0);
    Http::assertNothingSent();
})->with([
    'bare' => 'example.org', 'scheme' => 'https://www.example.org', 'port' => 'www.example.org:443',
    'path' => 'www.example.org/page', 'query' => 'www.example.org?a=b', 'fragment' => 'www.example.org#x',
    'credentials' => 'user@www.example.org', 'wildcard' => '*.www.example.org', 'ip' => 'www.127.0.0.1',
    'empty label' => 'www..example.org', 'underscore' => 'www.my_church.org', 'trailing dot' => 'www.example.org.',
    'unicode' => 'www.église.org', 'label start' => 'www.-church.org', 'label end' => 'www.church-.org',
    'long label' => 'www.'.str_repeat('a', 64).'.org', 'long host' => 'www.'.str_repeat(str_repeat('a', 63).'.', 4).'org',
    'platform' => 'www.churchsite.app', 'platform child' => 'www.foo.churchsite.app',
    'target' => 'www.customers.churchsite.app', 'origin' => 'www.origin.churchsite.app',
    'empty' => '', 'null' => null, 'array' => [['www.example.org']],
]);

it('excludes configured platform origin and SaaS target hosts and subdomains', function (string $key, string $value) {
    config([$key => $value]);
    expect(fn () => reserveTestHostname($this->owner, $this->site, 'www.internal.example.net'))
        ->toThrow(ValidationException::class);
})->with([
    ['app.url', 'https://internal.example.net'], ['customer-domains.origin_host', 'internal.example.net'],
    ['customer-domains.cname_target', 'internal.example.net'], ['domain-proxy.proof_host', 'www.internal.example.net'],
]);

it('retains reservations on conflicts without disclosing the other account', function () {
    $first = reserveTestHostname($this->owner, $this->site);
    $other = Site::factory()->create();
    $this->actingAs($other->user)->postJson(route('sites.domain.store', $other), ['hostname' => $first->hostname])
        ->assertUnprocessable()->assertJsonPath('errors.hostname.0', 'This hostname is unavailable.');
    $this->actingAs($this->owner)->postJson(route('sites.domain.store', $this->site), ['hostname' => 'www.other.org'])->assertUnprocessable();
    expect(CustomHostname::count())->toBe(1);
});

it('blocks new intent during account deletion but permits idempotent disconnect', function () {
    $domain = reserveTestHostname($this->owner, $this->site);
    $domain->forceFill(['verified_at' => now(), 'cloudflare_id' => 'managed-id'])->save();
    $this->owner->forceFill(['deletion_requested_at' => now()])->save();
    $this->actingAs($this->owner)->postJson(route('sites.domain.store', $this->site), ['hostname' => $domain->hostname])->assertUnprocessable();
    $this->delete(route('sites.domain.destroy', $this->site))->assertRedirect();
    $this->delete(route('sites.domain.destroy', $this->site))->assertRedirect();
    expect($domain->fresh()->state)->toBe('removing')->and($domain->fresh()->verified_at)->toBeNull()
        ->and($domain->fresh()->cloudflare_id)->toBe('managed-id')
        ->and($domain->fresh()->operation_id)->toBe($domain->operation_id);
    Http::assertNothingSent();
});

it('keeps the reservation while removal is uncertain', function () {
    $domain = reserveTestHostname($this->owner, $this->site);
    app(DisconnectCustomHostname::class)->handle($this->owner, $this->site->id);
    $this->actingAs($this->owner)->postJson(route('sites.domain.store', $this->site), ['hostname' => $domain->hostname])->assertUnprocessable();
    $this->postJson(route('sites.domain.store', $this->site), ['hostname' => 'www.other.org'])->assertUnprocessable();
    expect(CustomHostname::count())->toBe(1);
});

it('verifies exact application TXT evidence independently of provider status and clears mismatches', function () {
    $domain = reserveTestHostname($this->owner, $this->site);
    $domain->forceFill(['hostname_status' => 'active', 'ssl_status' => 'active'])->save();
    $dns = Mockery::mock(DomainDnsResolver::class);
    $dns->shouldReceive('txt')->with('_churchsite.www.example.org')->once()->andReturn([$domain->ownership_challenge]);
    (new VerifyCustomHostnameOwnership($dns))->handle($domain);
    expect($domain->fresh()->verified_at)->not->toBeNull();
    $dns->shouldReceive('txt')->with($domain->ownershipRecordName())->once()->andReturn([' '.$domain->ownership_challenge, strtoupper($domain->ownership_challenge)]);
    (new VerifyCustomHostnameOwnership($dns))->handle($domain->fresh());
    expect($domain->fresh()->verified_at)->toBeNull()->and($domain->fresh()->last_checked_at)->not->toBeNull();
});

it('discards DNS evidence returned after disconnect', function () {
    $domain = reserveTestHostname($this->owner, $this->site);
    $dns = Mockery::mock(DomainDnsResolver::class);
    $dns->shouldReceive('txt')->once()->andReturnUsing(function () use ($domain) {
        app(DisconnectCustomHostname::class)->handle($this->owner, $this->site->id);

        return [$domain->ownership_challenge];
    });
    (new VerifyCustomHostnameOwnership($dns))->handle($domain);
    expect($domain->fresh()->verified_at)->toBeNull()->and($domain->fresh()->state)->toBe('removing');
});

it('enforces database constraints even when competing writes bypass reservation checks', function (string $column) {
    $domain = reserveTestHostname($this->owner, $this->site);
    $domain->forceFill(['cloudflare_id' => 'managed-id'])->save();
    $other = Site::factory()->create();
    $attributes = [
        'site_id' => $other->id, 'hostname' => 'www.other.org', 'cloudflare_id' => 'another-id',
        'operation_id' => (string) Str::uuid(), 'ownership_challenge' => bin2hex(random_bytes(32)),
    ];
    $attributes[$column] = $domain->getAttribute($column);
    expect(fn () => (new CustomHostname)->forceFill($attributes)->save())->toThrow(UniqueConstraintViolationException::class);
})->with(['site_id', 'hostname', 'cloudflare_id', 'operation_id']);

it('prevents cascade deletion from losing remote cleanup identity', function () {
    reserveTestHostname($this->owner, $this->site);
    expect(fn () => $this->owner->delete())->toThrow(QueryException::class);
});

it('accepts the DNS length boundaries', function () {
    $hostname = 'www.'.str_repeat('a', 63).'.'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 57);
    expect(strlen($hostname))->toBe(253);
    expect(reserveTestHostname($this->owner, $this->site, $hostname)->hostname)->toBe($hostname);
});

it('uses a fresh challenge after confirmed local removal and ignores the previous generation', function () {
    $old = reserveTestHostname($this->owner, $this->site);
    // This reserved record has never started remote work; simulate confirmed cleanup.
    app(DisconnectCustomHostname::class)->handle($this->owner, $this->site->id);
    $old->delete();
    $current = reserveTestHostname($this->owner, $this->site);
    $dns = Mockery::mock(DomainDnsResolver::class);
    $dns->shouldReceive('txt')->once()->andReturn([$old->ownership_challenge]);
    (new VerifyCustomHostnameOwnership($dns))->handle($old);
    expect($current->ownership_challenge)->not->toBe($old->ownership_challenge)
        ->and($current->operation_id)->not->toBe($old->operation_id)
        ->and($current->fresh()->verified_at)->toBeNull();
});
