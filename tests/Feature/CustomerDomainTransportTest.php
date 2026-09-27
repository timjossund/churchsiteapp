<?php

use App\Actions\BuildSitePublicationSnapshot;
use App\Actions\ReserveCustomHostname;
use App\Models\Site;
use App\Support\PublishedAssets;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['app.url' => 'https://churchsite.app', 'domain-proxy.enabled' => true, 'domain-proxy.secret' => str_repeat('a', 64), 'domain-proxy.proof_host' => 'test.timjossund.com', 'customer-domains.enabled' => true]);
    Storage::fake('s3');
    $this->site = Site::factory()->create(['name' => 'Published Church', 'slug' => 'published-church']);
    $this->site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_live', 'stripe_status' => 'active', 'paid_until' => now()->addMonth()]);
    $this->about = $this->site->pages()->create(['name' => 'About', 'path' => 'about', 'is_home' => false, 'position' => 1]);
    $this->asset = $this->site->mediaAssets()->create(['storage_key' => 'sites/'.$this->site->id.'/image.png', 'mime_type' => 'image/png', 'alt_text' => 'Published image']);
    Storage::disk('s3')->put($this->asset->storage_key, 'published-image-bytes');
    $this->site->update(['logo_media_asset_id' => $this->asset->id]);
    $this->site->homePage()->first()->update(['social_image_id' => $this->asset->id]);
    $this->site->update(['published_snapshot' => app(BuildSitePublicationSnapshot::class)($this->site), 'published_at' => now()]);
    $this->domain = app(ReserveCustomHostname::class)->handle($this->site->user, $this->site->id, 'www.example.org');
    $this->domain->forceFill(['state' => 'ready', 'cloudflare_id' => 'managed-id', 'verified_at' => now(), 'cname_matches' => true, 'hostname_status' => 'active', 'ssl_status' => 'active'])->save();
    $this->withHeaders(['Authorization' => 'Bearer '.str_repeat('a', 64), 'X-Churchsite-Proxy-Version' => '2', 'X-Churchsite-Original-Url' => 'https://www.example.org/']);
});

function customerRequest(string $path = '/', string $method = 'GET')
{
    return test()->withHeader('X-Churchsite-Original-Url', 'https://www.example.org'.$path)
        ->{strtolower($method)}('https://churchsite.app/_domain/request');
}

it('renders frozen pages with same-host links and no session or preview noindex', function () {
    $this->site->update(['name' => 'Private draft']);
    $this->about->update(['name' => 'Draft page', 'path' => 'changed']);
    customerRequest()->assertOk()->assertSee('Published Church')->assertDontSee('Private draft')
        ->assertSee('https://www.example.org/about', false)->assertSee('https://www.example.org/_media/'.$this->asset->id, false)
        ->assertDontSee('noindex')->assertHeaderMissing('X-Robots-Tag')->assertHeaderMissing('Set-Cookie')
        ->assertHeader('X-Churchsite-Content', 'html')->assertHeader('X-Churchsite-Response-Version', '2');
    expect($this->app['request']->hasSession())->toBeFalse();
    customerRequest('/about')->assertOk()->assertSee('<title>About | Published Church</title>', false)
        ->assertSee('property="og:url" content="https://www.example.org/about"', false);
    customerRequest('/changed')->assertNotFound();
    customerRequest('/', 'HEAD')->assertOk()->assertContent('')->assertHeader('X-Churchsite-Content', 'html');
    expect(config('app.url'))->toBe('https://churchsite.app');
});

it('denies each ineligible state without exposing the owner', function (string $state) {
    match ($state) {
        'unknown' => $this->domain->delete(),
        'removed' => $this->domain->forceFill(['state' => 'removing'])->save(),
        'unverified' => $this->domain->forceFill(['verified_at' => null])->save(),
        'unpaid' => $this->site->subscriptions()->update(['paid_until' => now()->subMinute()]),
        'ssl' => $this->domain->forceFill(['ssl_status' => 'pending'])->save(),
        'unpublished' => $this->site->update(['published_at' => null]),
        'malformed' => $this->site->update(['published_snapshot' => ['version' => 2]]),
        'disabled' => config(['customer-domains.enabled' => false]),
    };
    customerRequest()->assertNotFound()->assertExactJson(['error' => 'not_found'])->assertHeaderMissing('Set-Cookie');
    customerRequest('/build/assets/fake.js')->assertNotFound();
})->with(['unknown', 'removed', 'unverified', 'unpaid', 'ssl', 'unpublished', 'malformed', 'disabled']);

it('denies the shared protocol control paths', function () {
    $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/domain-proxy-v2.json')), true, 512, JSON_THROW_ON_ERROR);
    foreach ($fixture['denied_paths'] as $path) {
        customerRequest($path)->assertNotFound()->assertExactJson(['error' => 'not_found'])->assertHeaderMissing('Set-Cookie');
    }
});

it('serves only referenced frozen media and rejects cross-site keys and traversal', function () {
    customerRequest('/_media/'.$this->asset->id)->assertOk()->assertStreamedContent('published-image-bytes')->assertHeader('Content-Type', 'image/png');
    customerRequest('/_media/'.$this->asset->id, 'HEAD')->assertOk()->assertContent('');
    $draft = $this->site->mediaAssets()->create(['storage_key' => 'sites/'.$this->site->id.'/draft.png', 'mime_type' => 'image/png']);
    customerRequest('/_media/'.$draft->id)->assertNotFound();
    foreach (['sites/999/other.png', 'sites/'.$this->site->id.'/../999/other.png'] as $key) {
        $snapshot = $this->site->published_snapshot;
        $snapshot['media'][0]['storage_key'] = $key;
        $this->site->update(['published_snapshot' => $snapshot]);
        customerRequest('/_media/'.$this->asset->id)->assertNotFound();
    }
});

it('serves all compiled published assets and font dependencies through the narrow allowlist', function () {
    $assets = app(PublishedAssets::class)->manifest();
    $html = customerRequest()->assertOk();
    foreach (array_merge($assets['styles'], $assets['scripts']) as $url) {
        $html->assertSee($url, false);
    }
    foreach ($assets['files'] as $file) {
        customerRequest('/build/'.$file)->assertOk()->assertHeader('X-Churchsite-Content', 'asset')->assertHeaderMissing('Set-Cookie');
        customerRequest('/build/'.$file, 'HEAD')->assertOk()->assertContent('');
    }
    $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
    customerRequest('/build/'.$manifest['resources/js/app.ts']['file'])->assertNotFound();
    customerRequest('/build/assets/../../.env')->assertNotFound();
});

it('denies forged authentication and unsupported methods for live content', function () {
    customerRequest('/', 'POST')->assertStatus(405)->assertHeaderMissing('Set-Cookie');
    $this->withHeader('Authorization', 'Bearer visitor');
    customerRequest()->assertForbidden();
});

it('keeps free subdirectory publication independent of paid domain access', function () {
    $this->site->subscriptions()->update(['paid_until' => now()->subMinute()]);
    customerRequest()->assertNotFound();
    $this->get('https://churchsite.app/s/published-church')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('prevents publishing reserved page paths and generates usable addresses for reserved names', function () {
    $page = $this->site->pages()->create(['name' => 'Login', 'position' => 2, 'is_home' => false]);
    expect($page->fresh()->path)->toBe('login-2');
    $this->actingAs($this->site->user)->patch('https://churchsite.app'.route('sites.pages.settings.update', [$this->site, $this->about], false), ['path' => 'login'])->assertSessionHasErrors('path');
    $this->about->update(['path' => 'dashboard']);
    $this->post('https://churchsite.app'.route('sites.publish', $this->site, false))->assertSessionHasErrors('publish');
});

it('denies unreferenced snapshot media and another tenants media', function () {
    $other = Site::factory()->create();
    $asset = $other->mediaAssets()->create(['storage_key' => 'sites/'.$other->id.'/private.png', 'mime_type' => 'image/png']);
    Storage::disk('s3')->put($asset->storage_key, 'other tenant private image');
    customerRequest('/_media/'.$asset->id)->assertNotFound();
    $snapshot = $this->site->published_snapshot;
    $snapshot['media'][] = ['id' => $asset->id, 'storage_key' => $asset->storage_key, 'mime_type' => 'image/png'];
    $this->site->update(['published_snapshot' => $snapshot]);
    customerRequest('/_media/'.$asset->id)->assertNotFound();
});

it('preserves escaped text and safe external links through customer rendering', function () {
    $snapshot = $this->site->published_snapshot;
    $snapshot['site']['name'] = '<script>alert(1)</script>';
    $snapshot['pages'][0]['blocks'] = [['id' => 1, 'type' => 'hero', 'content' => ['heading' => '<unsafe>', 'button_label' => 'Partner', 'link_type' => 'external', 'external_url' => 'https://example.net/partner']]];
    $this->site->update(['published_snapshot' => $snapshot]);
    customerRequest()->assertOk()->assertSee('&lt;unsafe&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('href="https://example.net/partner"', false)->assertSee('rel="noopener noreferrer"', false);
});

it('ignores sharing queries while keeping canonical same-host metadata', function () {
    customerRequest('/about?utm_source=shared&next=https://evil.example')
        ->assertOk()->assertSee('property="og:url" content="https://www.example.org/about"', false)
        ->assertDontSee('utm_source')->assertDontSee('evil.example');
    customerRequest('/?')->assertOk();
});
