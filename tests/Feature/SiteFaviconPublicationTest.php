<?php

use App\Actions\BuildSitePublicationSnapshot;
use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('s3');
    $this->site = Site::factory()->create(['slug' => 'favicon-church']);
    $this->about = $this->site->pages()->create(['name' => 'About', 'path' => 'about', 'position' => 1, 'is_home' => false]);
    $this->actingAs($this->site->user);
});

test('unset favicons preserve the legacy snapshot shape fingerprint and default links', function () {
    $this->post(route('sites.publish', $this->site))->assertRedirect();
    $snapshot = $this->site->fresh()->published_snapshot;
    expect($snapshot['site'])->not->toHaveKey('favicon_media_asset_id');
    expect(app(BuildSitePublicationSnapshot::class)->fingerprint(app(BuildSitePublicationSnapshot::class)($this->site->fresh())))
        ->toBe($snapshot['draft_fingerprint']);
    $this->get(route('sites.show', $this->site))->assertInertia(fn (Assert $page) => $page->where('site.has_unpublished_changes', false));
    $this->get(route('sites.published.show', $this->site->slug))->assertOk()
        ->assertSee('href="/favicon.ico"', false)->assertSee('href="/favicon.svg"', false);
});

test('all pages freeze favicon choice and bytes until publish and restore the default after removal', function () {
    $site = $this->site;
    $this->post(route('sites.publish', $site))->assertRedirect();
    $this->post(route('sites.favicon.store', $site), ['image' => UploadedFile::fake()->image('first.png', 32, 32)])->assertRedirect();
    $first = $site->fresh()->faviconMediaAsset;
    $firstUrl = route('sites.published.media.show', [$site->slug, $first]);
    $firstBytes = Storage::disk('s3')->get($first->storage_key);
    $this->get($firstUrl)->assertNotFound();
    $this->get(route('sites.show', $site))->assertInertia(fn (Assert $page) => $page->where('site.has_unpublished_changes', true));
    $this->get(route('sites.published.show', $site->slug))->assertSee('href="/favicon.ico"', false);
    $this->post(route('sites.publish', $site))->assertRedirect();
    foreach ([route('sites.published.show', $site->slug), route('sites.published.pages.show', [$site->slug, 'about'])] as $url) {
        $html = $this->get($url)->assertOk()->assertSee('type="image/png" href="'.$firstUrl.'"', false)
            ->assertDontSee('/favicon.ico', false)->assertDontSee('/favicon.svg', false)->getContent();
        expect(substr_count($html, 'rel="icon"'))->toBe(1);
    }
    $this->get($firstUrl)->assertOk()->assertStreamedContent($firstBytes)->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $published = $site->fresh()->published_snapshot;
    $this->post(route('sites.favicon.store', $site), ['image' => UploadedFile::fake()->image('second.png', 16, 16)])->assertRedirect();
    $second = $site->fresh()->faviconMediaAsset;
    $secondUrl = route('sites.published.media.show', [$site->slug, $second]);
    expect($site->fresh()->published_snapshot)->toBe($published);
    $this->get(route('sites.published.show', $site->slug))->assertSee($firstUrl, false)->assertDontSee($secondUrl, false);
    $this->get($firstUrl)->assertOk()->assertStreamedContent($firstBytes);
    $this->get($secondUrl)->assertNotFound();
    $this->post(route('sites.publish', $site))->assertRedirect();
    $this->get($firstUrl)->assertNotFound();
    $this->get($secondUrl)->assertOk();
    $this->delete(route('sites.favicon.destroy', $site))->assertRedirect();
    $this->get(route('sites.published.show', $site->slug))->assertSee($secondUrl, false);
    $this->get($secondUrl)->assertOk();
    $this->post(route('sites.publish', $site))->assertRedirect();
    $this->get(route('sites.published.show', $site->slug))->assertSee('href="/favicon.ico"', false)->assertDontSee($secondUrl, false);
    $this->get($secondUrl)->assertNotFound();
    $this->get(route('sites.show', $site))->assertInertia(fn (Assert $page) => $page->where('site.has_unpublished_changes', false));
    Storage::disk('s3')->assertExists($first->storage_key);
    Storage::disk('s3')->assertExists($second->storage_key);
});

test('favicon and logo sharing one owned PNG produces one media entry', function () {
    $this->post(route('sites.favicon.store', $this->site), ['image' => UploadedFile::fake()->image('icon.png', 32, 32)])->assertRedirect();
    $asset = $this->site->fresh()->faviconMediaAsset;
    $this->site->update(['logo_media_asset_id' => $asset->id]);
    $this->post(route('sites.publish', $this->site))->assertRedirect();
    expect($this->site->fresh()->published_snapshot['media'])->toHaveCount(1);
});

test('invalid draft favicon references fail publishing without replacing the live snapshot', function (string $invalid) {
    $this->post(route('sites.publish', $this->site))->assertRedirect();
    $snapshot = $this->site->fresh()->published_snapshot;
    $assetSite = $invalid === 'other owner' ? Site::factory()->create() : $this->site;
    $asset = $assetSite->mediaAssets()->create([
        'storage_key' => "sites/{$assetSite->id}/".($invalid === 'traversal' ? '../outside' : 'icon'),
        'mime_type' => $invalid === 'JPEG' ? 'image/jpeg' : 'image/png',
    ]);
    $this->site->update(['favicon_media_asset_id' => $asset->id]);
    $this->post(route('sites.publish', $this->site))->assertSessionHasErrors('publish');
    expect($this->site->fresh()->published_snapshot)->toBe($snapshot);
    $this->get(route('sites.published.show', $this->site->slug))->assertOk()->assertSee('href="/favicon.ico"', false);
})->with(['other owner', 'JPEG', 'traversal']);

test('legacy publications do not read the draft favicon', function () {
    $snapshot = app(BuildSitePublicationSnapshot::class)($this->site);
    $snapshot['version'] = 1;
    $snapshot['blocks'] = [];
    unset($snapshot['pages']);
    $this->site->update(['published_at' => now(), 'published_snapshot' => $snapshot]);
    $this->post(route('sites.favicon.store', $this->site), ['image' => UploadedFile::fake()->image('icon.png', 32, 32)])->assertRedirect();
    $this->get(route('sites.published.show', $this->site->slug))->assertOk()->assertSee('href="/favicon.ico"', false);
    $this->get(route('sites.published.media.show', [$this->site->slug, $this->site->fresh()->favicon_media_asset_id]))->assertNotFound();
});

test('malformed frozen favicon ids cannot become public URLs', function (mixed $id) {
    $snapshot = app(BuildSitePublicationSnapshot::class)($this->site);
    $snapshot['site']['favicon_media_asset_id'] = $id;
    $this->site->update(['published_at' => now(), 'published_snapshot' => $snapshot]);
    $this->get(route('sites.published.show', $this->site->slug))->assertNotFound();
})->with([0, -1, '1', 1.5, [[]], 'https://evil.example/icon.png', 999999]);

test('frozen favicon media requires PNG and a safe same-site key', function (string $invalid) {
    $this->post(route('sites.favicon.store', $this->site), ['image' => UploadedFile::fake()->image('icon.png', 32, 32)])->assertRedirect();
    $snapshot = app(BuildSitePublicationSnapshot::class)($this->site->fresh());
    $assetId = $snapshot['site']['favicon_media_asset_id'];
    match ($invalid) {
        'other site' => $snapshot['media'][0]['storage_key'] = 'sites/999999/private',
        'traversal' => $snapshot['media'][0]['storage_key'] = "sites/{$this->site->id}/../private",
        'backslash' => $snapshot['media'][0]['storage_key'] = "sites/{$this->site->id}/private\\outside",
        'JPEG' => $snapshot['media'][0]['mime_type'] = 'image/jpeg',
        'missing' => $snapshot['media'] = [],
    };
    $this->site->update(['published_at' => now(), 'published_snapshot' => $snapshot]);
    $this->get(route('sites.published.show', $this->site->slug))->assertNotFound();
    $this->get(route('sites.published.media.show', [$this->site->slug, $assetId]))->assertNotFound();
})->with(['other site', 'traversal', 'backslash', 'JPEG', 'missing']);
