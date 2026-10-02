<?php

use App\Actions\ReserveCustomHostname;
use App\Models\Site;
use App\Models\SiteBlock;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
    Http::preventStrayRequests();
});

test('dashboard distinguishes blank and nonblank drafts across all pages', function (bool $hasBlocks, bool $otherPage) {
    $site = Site::factory()->create();
    if ($hasBlocks) {
        $page = $otherPage
            ? $site->pages()->create(['name' => 'About', 'position' => 1, 'is_home' => false])
            : $site->homePage()->firstOrFail();
        SiteBlock::factory()->create(['site_id' => $site->id, 'page_id' => $page->id]);
    }

    $this->actingAs($site->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('sites.0.has_blocks', $hasBlocks)
        ->where('sites.0.published_at', null)
        ->where('sites.0.published_url', null));
    Http::assertNothingSent();
})->with([[false, false], [true, false], [true, true]]);

test('published cards retain publication state after draft changes', function (bool $hasBlocks) {
    $site = Site::factory()->create(['slug' => 'published-church', 'published_at' => now()]);
    if ($hasBlocks) {
        SiteBlock::factory()->create(['site_id' => $site->id, 'content' => ['body' => 'New draft']]);
    }

    $this->actingAs($site->user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('sites.0.has_blocks', $hasBlocks)
        ->where('sites.0.published_at', $site->published_at->toJSON())
        ->where('sites.0.published_url', route('sites.published.show', $site->slug)));
    Http::assertNothingSent();
})->with([false, true]);

test('dashboard selects only a serving domain and suppresses offline links', function (string $state) {
    config(['customer-domains.enabled' => true]);
    $owner = User::factory()->create();
    $site = Site::factory()->for($owner)->create(['slug' => 'domain-church', 'published_at' => now()]);
    $domain = app(ReserveCustomHostname::class)->handle($owner, $site->id, 'www.example.org');
    $domain->forceFill([
        'state' => 'ready', 'cloudflare_id' => 'private-provider-id', 'verified_at' => now(),
        'cname_matches' => true, 'hostname_status' => 'active', 'ssl_status' => 'active',
    ])->save();
    $subscription = $site->subscriptions()->create([
        'type' => 'default', 'stripe_id' => 'sub_cards', 'stripe_status' => 'active',
        'paid_until' => now()->addMonth(),
    ]);
    match ($state) {
        'pending' => $domain->forceFill(['state' => 'pending', 'ssl_status' => 'pending'])->save(),
        'disconnected' => $domain->forceFill(['cname_matches' => false])->save(),
        'removed' => $domain->delete(),
        'unpaid' => $subscription->forceFill(['paid_until' => now()->subMinute()])->save(),
        'disabled' => config(['customer-domains.enabled' => false]),
        'unpublished' => $site->update(['published_at' => null]),
        'deleting' => $site->forceFill(['deletion_requested_at' => now()])->save(),
        default => null,
    };
    $expectedUrl = match ($state) {
        'ready' => 'https://www.example.org/',
        'unpublished', 'deleting' => null,
        default => route('sites.published.show', $site->slug),
    };

    $this->actingAs($owner)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('sites.0.published_url', $expectedUrl)
        ->missing('sites.0.custom_hostname')->missing('sites.0.slug')->missing('sites.0.stripe_id'))
        ->assertDontSee('private-provider-id')->assertDontSee('sub_cards');
    Http::assertNothingSent();
})->with(['ready', 'pending', 'disconnected', 'removed', 'unpaid', 'disabled', 'unpublished', 'deleting']);
