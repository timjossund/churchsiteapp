<?php

use App\Actions\ReserveCustomHostname;
use App\Actions\SiteDomainSummary;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['inertia.ssr.enabled' => false, 'customer-domains.enabled' => true, 'site-billing.checkout_enabled' => true, 'site-billing.prices.monthly' => 'price_month', 'site-billing.prices.annual' => 'price_year']);
    Http::preventStrayRequests();
    $this->owner = User::factory()->create();
    $this->site = Site::factory()->for($this->owner)->create();
});

it('exposes domain setup only on the owned settings page with an explicit safe response', function () {
    $domain = app(ReserveCustomHostname::class)->handle($this->owner, $this->site->id, 'www.example.org');
    $domain->forceFill(['cloudflare_id' => 'private-provider-id', 'cloudflare_zone_id' => 'private-zone', 'error_category' => 'operator_required'])->save();
    $this->get(route('sites.go-live', $this->site))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('sites.go-live', $this->site))->assertNotFound();
    $response = $this->actingAs($this->owner)->get(route('sites.go-live', $this->site));
    $response->assertInertia(fn (Assert $page) => $page->component('Sites/GoLive')
        ->where('domain.hostname', 'www.example.org')->where('domain.status', 'unavailable')
        ->where('domain.poll', false)->has('domain.records', 2)
        ->where('domain.records.0.name', '_churchsite.www.example.org')
        ->where('domain.records.0.value', $domain->ownership_challenge)
        ->missing('domain.id')->missing('domain.cloudflare_id')->missing('domain.operation_id')
        ->missing('domain.cloudflare_zone_id')->missing('domain.error_category'));
    $response->assertDontSee('private-provider-id')->assertDontSee('private-zone')->assertDontSee($domain->operation_id);
    Http::assertNothingSent();
});

it('reports progress without declaring an unpaid or unfinished domain live', function (array $fields, bool $paid, bool $published, string $status) {
    $domain = app(ReserveCustomHostname::class)->handle($this->owner, $this->site->id, 'www.example.org');
    $domain->forceFill($fields)->save();
    if ($paid) {
        $this->site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_summary', 'stripe_status' => 'active', 'paid_until' => now()->addMonth()]);
    }
    if ($published) {
        $this->site->forceFill(['published_at' => now()])->save();
    }
    $summary = app(SiteDomainSummary::class)->handle($this->site->fresh());
    expect($summary['status'])->toBe($status)->and($summary['paid'])->toBe($paid)
        ->and($summary['published'])->toBe($published)->and($summary['hostname'])->toBe('www.example.org');
    Http::assertNothingSent();
})->with([
    'unpaid retained' => [[], false, false, 'unpaid'],
    'dns pending' => [[], true, false, 'dns_pending'],
    'connection pending' => [['verified_at' => now(), 'cname_matches' => true], true, false, 'connection_pending'],
    'ssl pending' => [['verified_at' => now(), 'cname_matches' => true, 'hostname_status' => 'active'], true, false, 'ssl_pending'],
    'unpublished' => [['state' => 'ready', 'cloudflare_id' => 'provider-id', 'verified_at' => now(), 'cname_matches' => true, 'hostname_status' => 'active', 'ssl_status' => 'active'], true, false, 'unpublished'],
    'live' => [['state' => 'ready', 'cloudflare_id' => 'provider-id', 'verified_at' => now(), 'cname_matches' => true, 'hostname_status' => 'active', 'ssl_status' => 'active'], true, true, 'live'],
    'removed' => [['state' => 'removing'], true, true, 'removal_pending'],
    'safe failure' => [['error_category' => 'provider_timeout'], true, true, 'unavailable'],
]);

it('disables new checkout and connection controls under rollout and deletion gates', function () {
    $action = app(SiteDomainSummary::class);
    expect($action->handle($this->site))->toMatchArray(['status' => 'empty', 'can_connect' => true, 'can_checkout' => true, 'can_check' => false, 'poll' => false]);
    config(['customer-domains.enabled' => false]);
    expect($action->handle($this->site))->toMatchArray(['enabled' => false, 'can_connect' => false, 'can_checkout' => false]);
    config(['customer-domains.enabled' => true, 'site-billing.checkout_enabled' => false]);
    expect($action->handle($this->site)['can_checkout'])->toBeFalse();
    config(['site-billing.checkout_enabled' => true]);
    $this->owner->forceFill(['deletion_requested_at' => now()])->save();
    expect($action->handle($this->site->fresh()))->toMatchArray(['can_connect' => false, 'can_checkout' => false]);
});

it('retains a resumable checkout plan without disclosing checkout identity or trusting return parameters', function () {
    app(ReserveCustomHostname::class)->handle($this->owner, $this->site->id, 'www.example.org');
    $this->site->forceFill(['checkout_attempt' => 'private-attempt', 'checkout_price_id' => 'price_year'])->save();
    foreach (['canceled', 'processing'] as $return) {
        $this->actingAs($this->owner)->get(route('sites.go-live', $this->site).'?billing='.$return)
            ->assertInertia(fn (Assert $page) => $page->where('domain.status', 'payment_pending')
                ->where('domain.paid', false)->where('domain.checkout_interval', 'annual')
                ->where('domain.can_checkout', true)->where('domain.poll', true)
                ->missing('domain.checkout_attempt')->missing('domain.checkout_price_id'));
    }
});

it('prevents a second subscription and replacement while removal is pending', function () {
    $domain = app(ReserveCustomHostname::class)->handle($this->owner, $this->site->id, 'www.example.org');
    $this->site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_summary', 'stripe_status' => 'incomplete']);
    expect(app(SiteDomainSummary::class)->handle($this->site->fresh())['can_checkout'])->toBeFalse();
    $domain->forceFill(['state' => 'removing'])->save();
    expect(app(SiteDomainSummary::class)->handle($this->site->fresh()))->toMatchArray(['status' => 'removal_pending', 'can_connect' => false, 'can_disconnect' => false, 'can_checkout' => false, 'can_check' => true, 'poll' => true]);
});

it('does not label disabled or incomplete provider identity as live', function () {
    $domain = app(ReserveCustomHostname::class)->handle($this->owner, $this->site->id, 'www.example.org');
    $domain->forceFill(['state' => 'ready', 'verified_at' => now(), 'cname_matches' => true, 'hostname_status' => 'active', 'ssl_status' => 'active'])->save();
    $this->site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_summary', 'stripe_status' => 'active', 'paid_until' => now()->addMonth()]);
    $this->site->forceFill(['published_at' => now()])->save();
    expect(app(SiteDomainSummary::class)->handle($this->site->fresh())['status'])->toBe('checking');
    $domain->forceFill(['cloudflare_id' => 'provider-id'])->save();
    config(['customer-domains.enabled' => false]);
    expect(app(SiteDomainSummary::class)->handle($this->site->fresh()))->toMatchArray(['status' => 'paused', 'poll' => false, 'can_check' => false, 'can_disconnect' => false]);
});

it('keeps polling while a new subscription awaits payment confirmation but stops after paid access expires', function () {
    app(ReserveCustomHostname::class)->handle($this->owner, $this->site->id, 'www.example.org');
    $this->site->forceFill(['checkout_attempt' => 'attempt', 'checkout_price_id' => 'price_month'])->save();
    $subscription = $this->site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_summary', 'stripe_status' => 'incomplete']);
    expect(app(SiteDomainSummary::class)->handle($this->site->fresh()))->toMatchArray(['status' => 'payment_pending', 'poll' => true, 'can_checkout' => false]);
    $subscription->forceFill(['stripe_status' => 'active', 'paid_until' => now()->subMinute()])->save();
    expect(app(SiteDomainSummary::class)->handle($this->site->fresh()))->toMatchArray(['status' => 'unpaid', 'poll' => false, 'can_checkout' => false]);
});

it('keeps settings focused on editing and protects the Go Live page without calling providers', function () {
    $this->actingAs($this->owner)->get(route('sites.show', $this->site))
        ->assertInertia(fn (Assert $page) => $page->component('Sites/Settings')->has('pages')->missing('billing')->missing('domain'));
    $this->get(route('sites.go-live', $this->site))
        ->assertInertia(fn (Assert $page) => $page->component('Sites/GoLive')
            ->where('site.id', $this->site->id)->where('site.name', $this->site->name)
            ->has('billing')->where('domain.status', 'empty')->missing('pages')->missing('site.stripe_id'));
    $this->owner->forceFill(['email_verified_at' => null])->save();
    $this->get(route('sites.go-live', $this->site))->assertRedirect(route('verification.notice'));
    Http::assertNothingSent();
});

it('returns domain changes and status refreshes to Go Live', function () {
    $this->site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_redirect', 'stripe_status' => 'active', 'paid_until' => now()->addMonth()]);
    $this->actingAs($this->owner)->post(route('sites.domain.store', $this->site), ['hostname' => 'www.example.org'])
        ->assertRedirect(route('sites.go-live', $this->site));
    $this->delete(route('sites.domain.destroy', $this->site))->assertRedirect(route('sites.go-live', $this->site));
    // Removing an unprovisioned reservation needs no provider call.
    $this->post(route('sites.domain.check', $this->site))->assertRedirect(route('sites.go-live', $this->site));
    expect($this->site->customHostname()->exists())->toBeFalse();
    Http::assertNothingSent();
});
