<?php

use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('publishing from another editor captures ordered pages and independent metadata in one snapshot', function () {
    Storage::fake('s3');
    $site = Site::factory()->create(['name' => 'Grace Church', 'slug' => 'whole-site']);
    $home = $site->homePage()->firstOrFail();
    $home->update(['position' => 2]);
    $about = $site->pages()->create(['name' => 'About', 'position' => 0, 'seo_description' => 'About our church']);
    $visit = $site->pages()->create(['name' => 'Visit', 'position' => 1, 'seo_title' => 'Come worship', 'seo_description' => 'Visit on Sunday']);
    foreach ([$home, $about, $visit] as $page) {
        $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/{$page->id}", 'mime_type' => 'image/png']);
        Storage::disk('s3')->put($asset->storage_key, $page->name);
        $page->update(['social_image_id' => $asset->id]);
        $page->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => $page->name.' content']]);
    }
    $this->actingAs($site->user)->post(route('sites.publish', $site).'?editor_page='.$about->id)
        ->assertRedirect(route('sites.pages.show', [$site, $about]))->assertSessionHasNoErrors();
    $snapshot = $site->fresh()->published_snapshot;
    expect($snapshot['version'])->toBe(2)->and(array_column($snapshot['pages'], 'id'))->toBe([$about->id, $visit->id, $home->id])
        ->and($snapshot['media'])->toHaveCount(3);
    foreach ([[$home, 'Grace Church', null], [$about, 'About | Grace Church', 'About our church'], [$visit, 'Come worship', 'Visit on Sunday']] as [$page, $title, $description]) {
        $url = $page->is_home ? route('sites.published.show', $site->slug) : route('sites.published.pages.show', [$site->slug, $page->path]);
        $response = $this->get($url)->assertOk()->assertSee('<title>'.$title.'</title>', false)
            ->assertSee('property="og:url" content="'.$url.'"', false)->assertSee($page->name.' content')
            ->assertSee(route('sites.published.media.show', [$site->slug, $page->social_image_id]), false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Cache-Control', 'no-store, private');
        $description === null ? $response->assertDontSee('name="description"', false) : $response->assertSee('name="description" content="'.$description.'"', false);
        $this->get(route('sites.pages.show', [$site, $page]))->assertInertia(fn (Assert $result) => $result
            ->where('selected_page.published_url', $url)->where('site.has_unpublished_changes', false));
    }
});

test('draft paths names and deletion leave old public pages intact until republished without redirects', function () {
    $site = Site::factory()->create(['name' => 'Original Church', 'slug' => 'stable-pages']);
    $about = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $visit = $site->pages()->create(['name' => 'Visit', 'position' => 2]);
    $about->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'Published about content']]);
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    $snapshot = $site->fresh()->published_snapshot;
    $oldUrl = route('sites.published.pages.show', [$site->slug, 'about']);
    $newUrl = route('sites.published.pages.show', [$site->slug, 'our-story']);
    $visitUrl = route('sites.published.pages.show', [$site->slug, 'visit']);
    $this->patch(route('sites.pages.settings.update', [$site, $about]), ['path' => 'our-story'])->assertSessionHasNoErrors();
    $this->patch(route('sites.pages.update', [$site, $about]), ['name' => 'Our Story'])->assertSessionHasNoErrors();
    $this->delete(route('sites.pages.destroy', [$site, $visit]))->assertRedirect();
    $newPage = $site->pages()->create(['name' => 'New Page', 'position' => 2]);
    $this->patch(route('sites.update', $site), ['name' => 'Changed Church'])->assertSessionHasNoErrors();
    $this->get($oldUrl)->assertOk()->assertSee('<title>About | Original Church</title>', false);
    $this->get($newUrl)->assertNotFound();
    $this->get($visitUrl)->assertOk();
    expect($site->fresh()->published_snapshot)->toBe($snapshot);
    $this->get(route('sites.pages.show', [$site, $about]))->assertInertia(fn (Assert $result) => $result
        ->where('selected_page.published_url', $oldUrl)->where('site.has_unpublished_changes', true));
    $this->get(route('sites.pages.show', [$site, $newPage]))->assertInertia(fn (Assert $result) => $result->where('selected_page.published_url', null));
    $this->post(route('sites.publish', $site).'?editor_page='.$about->id)->assertRedirect(route('sites.pages.show', [$site, $about]));
    $this->get($oldUrl)->assertNotFound()->assertHeaderMissing('Location');
    $this->get($visitUrl)->assertNotFound();
    $this->get($newUrl)->assertOk()->assertSee('<title>Our Story | Changed Church</title>', false)->assertSee('Published about content');
    $this->get(route('sites.pages.show', [$site, $about]))->assertInertia(fn (Assert $result) => $result->where('selected_page.published_url', $newUrl));
    $this->patch(route('sites.pages.settings.update', [$site, $newPage]), ['path' => 'about'])->assertSessionHasNoErrors();
    $this->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    $this->get($oldUrl)->assertOk()->assertSee('<title>New Page | Changed Church</title>', false)->assertDontSee('Published about content');
});

test('legacy publications keep frozen Home metadata blocks and media until explicit whole-site publication', function (bool $additionalPage) {
    Storage::fake('s3');
    $site = Site::factory()->create(['name' => 'Draft church', 'slug' => 'legacy-public']);
    $home = $site->homePage()->firstOrFail();
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/legacy", 'mime_type' => 'image/png']);
    Storage::disk('s3')->put($asset->storage_key, 'legacy image');
    $snapshot = [
        'version' => 1,
        'site' => ['name' => 'Published church', 'slug' => $site->slug, 'theme_key' => 'bold', 'footer' => ['text' => 'Legacy footer'], 'logo_media_asset_id' => null, 'seo_title' => 'Legacy Home title', 'seo_description' => 'Legacy description', 'social_image_id' => $asset->id],
        'blocks' => [['id' => 1234, 'type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'Legacy frozen body']]],
        'media' => [['id' => $asset->id, 'storage_key' => $asset->storage_key, 'mime_type' => 'image/png', 'alt_text' => 'Legacy image']],
        'draft_fingerprint' => str_repeat('a', 64),
    ];
    $site->update(['published_snapshot' => $snapshot, 'published_at' => now()->subDay()]);
    $home->update(['seo_title' => 'New Home title']);
    $page = $additionalPage ? $site->pages()->create(['name' => 'About', 'position' => 1]) : null;
    $bytes = DB::table('sites')->where('id', $site->id)->value('published_snapshot');
    $this->get(route('sites.published.show', $site->slug))->assertOk()->assertSee('Legacy frozen body')
        ->assertSee('<title>Legacy Home title</title>', false)->assertSee('Legacy description')->assertSee('Legacy footer');
    $this->get(route('sites.published.media.show', [$site->slug, $asset]))->assertOk()->assertStreamedContent('legacy image');
    $this->get(route('sites.published.pages.show', [$site->slug, 'about']))->assertNotFound();
    $this->actingAs($site->user)->get(route('sites.pages.show', [$site, $home]))->assertInertia(fn (Assert $result) => $result
        ->where('selected_page.published_url', route('sites.published.show', $site->slug))->where('site.has_unpublished_changes', true));
    if ($page) {
        $this->get(route('sites.pages.show', [$site, $page]))->assertInertia(fn (Assert $result) => $result->where('selected_page.published_url', null));
    }
    expect(DB::table('sites')->where('id', $site->id)->value('published_snapshot'))->toBe($bytes);
    $this->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    expect($site->fresh()->published_snapshot['version'])->toBe(2);
    $this->get(route('sites.published.show', $site->slug))->assertOk()->assertSee('<title>New Home title</title>', false)->assertDontSee('Legacy frozen body');
    if ($page) {
        $this->get(route('sites.published.pages.show', [$site->slug, 'about']))->assertOk();
    }
    $this->get(route('sites.published.media.show', [$site->slug, $asset]))->assertNotFound();
})->with([false, true]);

test('the whole-site fingerprint detects any saved page change', function (string $change) {
    $site = Site::factory()->create(['slug' => 'fingerprint']);
    $home = $site->homePage()->firstOrFail();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $block = $page->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'Original']]);
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    match ($change) {
        'name' => $page->update(['name' => 'Changed']),
        'path' => $page->update(['path' => 'new-path']),
        'order' => $page->update(['position' => -1]),
        'title' => $page->update(['seo_title' => 'Custom']),
        'description' => $page->update(['seo_description' => 'Description']),
        'block' => $block->update(['content' => ['body' => 'Changed']]),
        'delete' => $page->delete(),
        'add' => $site->pages()->create(['name' => 'New', 'position' => 2]),
    };
    $this->get(route('sites.pages.show', [$site, $home]))->assertInertia(fn (Assert $result) => $result->where('site.has_unpublished_changes', true));
    $this->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    $this->get(route('sites.show', $site))->assertInertia(fn (Assert $result) => $result->where('site.has_unpublished_changes', false));
})->with(['name', 'path', 'order', 'title', 'description', 'block', 'delete', 'add']);

test('publication media is deduplicated across pages and remains available until no published page references it', function () {
    Storage::fake('s3');
    $site = Site::factory()->create(['slug' => 'shared-assets']);
    $home = $site->homePage()->firstOrFail();
    $about = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $visit = $site->pages()->create(['name' => 'Visit', 'position' => 2]);
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/shared", 'mime_type' => 'image/png']);
    $private = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/private", 'mime_type' => 'image/png']);
    Storage::disk('s3')->put($asset->storage_key, 'shared');
    $site->update(['logo_media_asset_id' => $asset->id]);
    $about->update(['social_image_id' => $asset->id]);
    $visit->update(['social_image_id' => $asset->id]);
    $home->blocks()->create(['type' => 'image', 'position' => 0, 'content' => ['media_asset_id' => $asset->id]]);
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    expect($site->fresh()->published_snapshot['media'])->toHaveCount(1);
    $this->get(route('sites.published.media.show', [$site->slug, $private]))->assertNotFound();
    $site->update(['logo_media_asset_id' => null]);
    $home->blocks()->delete();
    $about->delete();
    $url = route('sites.published.media.show', [$site->slug, $asset]);
    $this->get($url)->assertOk();
    $this->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    $this->get($url)->assertOk();
    $visit->update(['social_image_id' => null]);
    $this->get($url)->assertOk();
    $this->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    $this->get($url)->assertNotFound();
    Storage::disk('s3')->assertExists($asset->storage_key);
});

test('invalid media on an additional page aborts the whole publication and keeps editors available', function () {
    $site = Site::factory()->create(['slug' => 'atomic-pages']);
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    $saved = $site->fresh();
    $other = Site::factory()->create();
    $asset = $other->mediaAssets()->create(['storage_key' => "sites/{$other->id}/foreign", 'mime_type' => 'image/png']);
    $page->update(['social_image_id' => $asset->id]);
    $site->homePage()->firstOrFail()->update(['seo_title' => 'Unpublished']);
    $this->post(route('sites.publish', $site))->assertSessionHasErrors('publish');
    expect($site->fresh()->published_snapshot)->toBe($saved->published_snapshot)
        ->and($site->fresh()->published_at->equalTo($saved->published_at))->toBeTrue();
    $this->get(route('sites.published.show', $site->slug))->assertOk()->assertDontSee('Unpublished');
    $this->get(route('sites.pages.show', [$site, $page]))->assertOk()->assertInertia(fn (Assert $result) => $result->where('site.has_unpublished_changes', true));
});

test('page routes coexist with media routes and reject unknown or nested addresses', function () {
    Storage::fake('s3');
    $site = Site::factory()->create(['slug' => 'route-check']);
    $page = $site->pages()->create(['name' => 'Media', 'position' => 1]);
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/image", 'mime_type' => 'image/png']);
    Storage::disk('s3')->put($asset->storage_key, 'image');
    $page->update(['social_image_id' => $asset->id]);
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    $this->get('/s/route-check/media')->assertOk()->assertSee('<title>Media | '.$site->name.'</title>', false);
    $this->get('/s/route-check/media/'.$asset->id)->assertOk()->assertStreamedContent('image');
    foreach (['missing', 'media/deeper/path', 'unsafe_path', 'Media'] as $path) {
        $this->get('/s/route-check/'.$path)->assertNotFound();
    }
    $this->get('/s/missing/media')->assertNotFound();
});

test('malformed and unsupported publication snapshots return not found', function (string $corruption) {
    $site = Site::factory()->create(['slug' => 'malformed']);
    $site->pages()->create(['name' => 'About', 'position' => 1]);
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    $snapshot = $site->fresh()->published_snapshot;
    match ($corruption) {
        'version' => $snapshot['version'] = 99,
        'pages' => $snapshot['pages'] = null,
        'site' => $snapshot['site'] = [],
        'blocks' => $snapshot['pages'][0]['blocks'] = [null],
        'media' => $snapshot['media'] = ['bad'],
        'home' => $snapshot['pages'][0]['is_home'] = false,
        'path' => $snapshot['pages'][0]['path'] = 'home',
        'empty path' => $snapshot['pages'][1]['path'] = '',
        'blank path' => $snapshot['pages'][1]['path'] = '   ',
    };
    $site->update(['published_snapshot' => $snapshot]);
    $this->get(route('sites.published.show', $site->slug))->assertNotFound();
    $this->get(route('sites.published.media.show', [$site->slug, 123]))->assertNotFound();
})->with(['version', 'pages', 'site', 'blocks', 'media', 'home', 'path', 'empty path', 'blank path']);
