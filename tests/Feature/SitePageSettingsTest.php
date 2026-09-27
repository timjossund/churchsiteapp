<?php

use App\Actions\BuildSitePublicationSnapshot;
use App\Http\Requests\SitePageSettingsRequest;
use App\Models\Site;
use App\Models\SitePage;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('page settings migration preserves existing records and published bytes', function () {
    $site = Site::factory()->create(['slug' => 'legacy-settings']);
    $blank = Site::factory()->create();
    $home = $site->homePage()->firstOrFail();
    $first = $site->pages()->create(['name' => 'About', 'position' => 3]);
    $second = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $symbol = $site->pages()->create(['name' => '❤️', 'position' => 2]);
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/social", 'mime_type' => 'image/png']);
    $metadata = ['seo_title' => ' Original title ', 'seo_description' => "Original\ndescription", 'social_image_id' => $asset->id];
    $home->update($metadata);
    $home->blocks()->create(['type' => 'plain_text', 'position' => 4, 'content' => ['body' => 'Original body']]);
    $first->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'Private body']]);
    $builder = app(BuildSitePublicationSnapshot::class);
    $draftSnapshot = $builder($site);
    $snapshot = [
        'version' => 1, 'site' => array_merge($draftSnapshot['site'], $metadata),
        'blocks' => $home->blocks()->orderBy('position')->get()->map(fn ($block) => $block->only('id', 'type', 'position', 'content'))->all(),
        'media' => $draftSnapshot['media'],
    ];
    $fingerprint = $builder->fingerprint($snapshot);
    $snapshot['draft_fingerprint'] = $fingerprint;
    $site->update(['published_snapshot' => $snapshot, 'published_at' => now()->subDay()]);

    // Reproduce pre-migration columns only in the isolated test database.
    $migration = require database_path('migrations/2026_09_26_000002_add_settings_to_site_pages_table.php');
    $migration->down();
    DB::table('sites')->where('id', $site->id)->update($metadata);
    $sitesBefore = DB::table('sites')->orderBy('id')->get()->toJson();
    $pagesBefore = DB::table('site_pages')->orderBy('id')->get();
    $blocksBefore = DB::table('site_blocks')->orderBy('id')->get()->toJson();
    $mediaBefore = DB::table('media_assets')->orderBy('id')->get()->toJson();
    $migration->up();

    expect(DB::table('sites')->orderBy('id')->get()->toJson())->toBe($sitesBefore)
        ->and(DB::table('site_blocks')->orderBy('id')->get()->toJson())->toBe($blocksBefore)
        ->and(DB::table('media_assets')->orderBy('id')->get()->toJson())->toBe($mediaBefore)
        ->and($home->fresh()->only(array_keys($metadata)))->toBe($metadata)
        ->and($home->fresh()->path)->toBeNull()
        ->and($first->fresh()->path)->toBe('about')
        ->and($second->fresh()->path)->toBe('about-2')
        ->and($symbol->fresh()->path)->toBe('page-'.$symbol->id)
        ->and($blank->homePage()->firstOrFail()->seo_title)->toBeNull()
        ->and($builder($site->fresh()))->toBe($draftSnapshot);
    foreach ($pagesBefore as $page) {
        expect((array) DB::table('site_pages')->select(array_keys((array) $page))->find($page->id))->toBe((array) $page);
        if (! $page->is_home) {
            expect(SitePage::findOrFail($page->id)->only(array_keys($metadata)))
                ->toBe(['seo_title' => null, 'seo_description' => null, 'social_image_id' => null]);
        }
    }
    $this->get(route('sites.published.show', $site->slug))->assertOk()->assertSee('Original body')->assertSee('Original title');
});

test('generated page paths normalize names resolve collisions and remain stable after renaming', function () {
    $site = Site::factory()->create();
    $this->actingAs($site->user);
    foreach ([' About Us ', 'About Us', 'About Us', 'Café & Worship', '❤️', str_repeat('a', 255), str_repeat('a', 255)] as $name) {
        $this->post(route('sites.pages.store', $site), ['name' => $name])->assertRedirect()->assertSessionHasNoErrors();
    }
    $pages = $site->pages()->where('is_home', false)->orderBy('id')->get();
    expect($pages->pluck('path')->all())->toBe([
        'about-us', 'about-us-2', 'about-us-3', 'cafe-worship', 'page-'.$pages[4]->id,
        str_repeat('a', 100), str_repeat('a', 98).'-2',
    ]);
    $this->patch(route('sites.pages.update', [$site, $pages[0]]), ['name' => 'New display name'])->assertSessionHasNoErrors();
    expect($pages[0]->fresh()->path)->toBe('about-us');
    $other = Site::factory()->create();
    expect($other->pages()->create(['name' => 'About Us', 'position' => 1])->path)->toBe('about-us');
});

test('page settings save independently while legacy endpoints target Home regardless of return page', function () {
    $site = Site::factory()->create(['slug' => 'settings']);
    $home = $site->homePage()->firstOrFail();
    $about = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $visit = $site->pages()->create(['name' => 'Visit', 'position' => 2]);
    $this->actingAs($site->user);
    foreach ([$home, $about, $visit] as $page) {
        $this->patch(route('sites.pages.settings.update', [$site, $page]), [
            'seo_title' => '  '.$page->name.' title  ', 'seo_description' => '  '.$page->name.' description  ',
        ])->assertRedirect(route('sites.pages.show', [$site, $page]))->assertSessionHasNoErrors();
        expect($page->fresh()->seo_title)->toBe($page->name.' title');
    }
    $this->patch(route('sites.pages.settings.update', [$site, $about]), ['path' => '  ABOUT-OUR-CHURCH  '])->assertSessionHasNoErrors();
    expect($about->fresh()->path)->toBe('about-our-church');
    $this->patch(route('sites.update', $site).'?editor_page='.$visit->id, ['seo_title' => 'New Home title'])
        ->assertRedirect(route('sites.pages.show', [$site, $visit]));
    expect($home->fresh()->seo_title)->toBe('New Home title')
        ->and($visit->fresh()->seo_title)->toBe('Visit title')
        ->and(DB::table('sites')->where('id', $site->id)->value('seo_title'))->toBeNull();
    $this->get(route('sites.pages.show', [$site, $home]))->assertInertia(fn (Assert $response) => $response->where('selected_page.seo_title', 'New Home title'));
    $this->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    expect($site->fresh()->published_snapshot['pages'][0]['seo_title'])->toBe('New Home title');
    $this->patch(route('sites.pages.settings.update', [$site, $about]), ['seo_title' => '', 'seo_description' => '  '])->assertSessionHasNoErrors();
    expect($about->fresh()->seo_title)->toBeNull()->and($about->fresh()->seo_description)->toBeNull();
});

test('invalid page settings preserve prior values', function (array $input, string $error) {
    $site = Site::factory()->create();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1, 'seo_title' => 'Saved title']);
    $site->pages()->create(['name' => 'Taken', 'position' => 2]);
    $before = $page->fresh()->getAttributes();
    $this->actingAs($site->user)->patch(route('sites.pages.settings.update', [$site, $page]), $input)->assertSessionHasErrors($error);
    expect($page->fresh()->getAttributes())->toBe($before);
})->with([
    'duplicate' => [['path' => 'TAKEN'], 'path'],
    'empty' => [['path' => ''], 'path'],
    'null' => [['path' => null], 'path'],
    'long' => [['path' => str_repeat('a', 101)], 'path'],
    'slash' => [['path' => 'about/us'], 'path'],
    'URL' => [['path' => 'https://example.test'], 'path'],
    'query' => [['path' => 'about?q=yes'], 'path'],
    'fragment' => [['path' => 'about#us'], 'path'],
    'space' => [['path' => 'about us'], 'path'],
    'underscore' => [['path' => 'about_us'], 'path'],
    'repeated separator' => [['path' => 'about--us'], 'path'],
    'title' => [['seo_title' => str_repeat('a', 256)], 'seo_title'],
    'description' => [['seo_description' => str_repeat('a', 2001)], 'seo_description'],
    'identity' => [['is_home' => true], 'is_home'],
    'ownership' => [['site_id' => 123], 'site_id'],
    'image assignment' => [['social_image_id' => 123], 'social_image_id'],
]);

test('Home root cannot be changed and path uniqueness is enforced by the database', function () {
    $site = Site::factory()->create();
    $home = $site->homePage()->firstOrFail();
    $this->actingAs($site->user)->patch(route('sites.pages.settings.update', [$site, $home]), ['path' => 'home'])->assertSessionHasErrors('path');
    expect($home->fresh()->path)->toBeNull();
    $site->pages()->create(['name' => 'About', 'position' => 1]);
    expect(fn () => DB::table('site_pages')->insert(['site_id' => $site->id, 'name' => 'Duplicate', 'position' => 2, 'path' => 'about']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('page settings and social images reject guests foreign sites mismatched pages and deleted pages', function () {
    Storage::fake('s3');
    $site = Site::factory()->create();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $other = Site::factory()->for($site->user)->create();
    $operations = fn ($siteId, $pageId) => [
        ['patch', route('sites.pages.settings.update', [$siteId, $pageId]), ['path' => 'changed']],
        ['post', route('sites.pages.social-image.store', [$siteId, $pageId]), ['image' => UploadedFile::fake()->image('social.png')]],
        ['delete', route('sites.pages.social-image.destroy', [$siteId, $pageId]), []],
    ];
    foreach ($operations($site->id, $page->id) as [$method, $url, $input]) {
        $this->$method($url, $input)->assertRedirect(route('login'));
    }
    $this->actingAs(User::factory()->create());
    foreach ($operations($site->id, $page->id) as [$method, $url, $input]) {
        $this->$method($url, $input)->assertNotFound();
    }
    $this->actingAs($site->user);
    foreach ($operations($site->id, $other->homePage()->firstOrFail()->id) as [$method, $url, $input]) {
        $this->$method($url, $input)->assertNotFound();
    }
    $page->delete();
    foreach ($operations($site->id, $page->id) as [$method, $url, $input]) {
        $this->$method($url, $input)->assertNotFound();
    }
    expect($site->mediaAssets()->count())->toBe(0)->and(Storage::disk('s3')->allFiles())->toBe([]);
});

test('page social images upload replace and clear independently without deleting stored media', function () {
    Storage::fake('s3');
    $site = Site::factory()->create(['slug' => 'images']);
    $home = $site->homePage()->firstOrFail();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $this->actingAs($site->user);
    $this->post(route('sites.social-image.store', $site).'?editor_page='.$page->id, ['image' => UploadedFile::fake()->image('home.png')])
        ->assertRedirect(route('sites.pages.show', [$site, $page]));
    $homeAsset = $home->fresh()->socialImage;
    expect($homeAsset)->not->toBeNull()->and($page->fresh()->social_image_id)->toBeNull();
    $this->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    $snapshot = $site->fresh()->published_snapshot;
    foreach (['first', 'second'] as $name) {
        $this->post(route('sites.pages.social-image.store', [$site, $page]), ['image' => UploadedFile::fake()->image($name.'.png'), 'alt_text' => $name])
            ->assertRedirect(route('sites.pages.show', [$site, $page]))->assertSessionHasNoErrors();
        $asset = $page->fresh()->socialImage;
        expect($asset->site_id)->toBe($site->id)->and($asset->alt_text)->toBe($name)
            ->and(Storage::disk('s3')->getVisibility($asset->storage_key))->toBe('private');
    }
    $this->delete(route('sites.pages.social-image.destroy', [$site, $page]))->assertRedirect(route('sites.pages.show', [$site, $page]));
    expect($page->fresh()->social_image_id)->toBeNull()->and($home->fresh()->social_image_id)->toBe($homeAsset->id)
        ->and(Storage::disk('s3')->allFiles())->toHaveCount(3);
    $this->delete(route('sites.social-image.destroy', $site).'?editor_page='.$page->id)->assertRedirect();
    expect($home->fresh()->social_image_id)->toBeNull()->and($site->fresh()->published_snapshot)->toBe($snapshot);
    $this->get(route('sites.published.media.show', [$site->slug, $homeAsset]))->assertOk();
    $this->get(route('sites.published.media.show', [$site->slug, $asset]))->assertNotFound();
});

test('a page deleted during image storage rejects the late upload and cleans the new object', function () {
    $site = Site::factory()->create();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $key = null;
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->andReturnUsing(function ($directory, $file, $name) use ($page, &$key) {
        $page->delete();

        return $key = $directory.'/'.$name;
    });
    $disk->shouldReceive('delete')->once()->withArgs(function ($value) use (&$key) {
        return $value === $key;
    })->andReturnTrue();
    Storage::shouldReceive('disk')->with('s3')->andReturn($disk);
    $this->actingAs($site->user)->post(route('sites.pages.social-image.store', [$site, $page]), ['image' => UploadedFile::fake()->image('late.png')])->assertNotFound();
    expect($site->mediaAssets()->count())->toBe(0)->and($page->fresh())->toBeNull();
});

test('failed page social image storage preserves the saved reference', function () {
    $site = Site::factory()->create();
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/saved", 'mime_type' => 'image/png']);
    $page = $site->pages()->create(['name' => 'About', 'position' => 1, 'social_image_id' => $asset->id]);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->andReturnFalse();
    $disk->shouldReceive('delete')->once()->andReturnTrue();
    Storage::shouldReceive('disk')->with('s3')->andReturn($disk);
    $this->actingAs($site->user)->post(route('sites.pages.social-image.store', [$site, $page]), ['image' => UploadedFile::fake()->image('failed.png')])->assertSessionHasErrors('image');
    expect($page->fresh()->social_image_id)->toBe($asset->id)->and($site->mediaAssets()->count())->toBe(1);
});

test('page deletion after request validation is checked again before saving settings', function () {
    $site = Site::factory()->create();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $this->app->afterResolving(SitePageSettingsRequest::class, function () use ($page) {
        $page->delete();
    });
    $this->actingAs($site->user)->patch(route('sites.pages.settings.update', [$site, $page]), ['seo_title' => 'Late edit'])->assertNotFound();
    expect($page->fresh())->toBeNull();
});

test('a path claimed after validation returns a field error without partial metadata changes', function () {
    $site = Site::factory()->create();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1, 'seo_title' => 'Saved title']);
    $this->app->afterResolving(SitePageSettingsRequest::class, function () use ($site) {
        $site->pages()->create(['name' => 'Claimed', 'position' => 2, 'path' => 'new-path']);
    });
    $this->actingAs($site->user)->patch(route('sites.pages.settings.update', [$site, $page]), ['path' => 'new-path', 'seo_title' => 'Unsaved title'])->assertSessionHasErrors('path');
    expect($page->fresh()->path)->toBe('about')->and($page->fresh()->seo_title)->toBe('Saved title');
});

test('unexpected page image assignment failure rolls back the asset and cleans storage', function () {
    Storage::fake('s3');
    $site = Site::factory()->create();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    SitePage::updating(fn () => throw new RuntimeException('Simulated page settings write failure.'));
    $this->actingAs($site->user)->post(route('sites.pages.social-image.store', [$site, $page]), ['image' => UploadedFile::fake()->image('failed.png')])->assertServerError();
    expect($page->fresh()->social_image_id)->toBeNull()->and($site->mediaAssets()->count())->toBe(0)
        ->and(Storage::disk('s3')->allFiles())->toBe([]);
});

test('page social image validation rejects unsupported oversized and injected inputs before storage', function () {
    Storage::fake('s3');
    $site = Site::factory()->create();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $other = Site::factory()->create();
    $foreign = $other->mediaAssets()->create(['storage_key' => "sites/{$other->id}/foreign", 'mime_type' => 'image/png']);
    $this->actingAs($site->user);
    foreach ([UploadedFile::fake()->create('bad.pdf', 10, 'application/pdf'), UploadedFile::fake()->image('large.png')->size(5121)] as $file) {
        $this->post(route('sites.pages.social-image.store', [$site, $page]), ['image' => $file])->assertSessionHasErrors('image');
    }
    $this->post(route('sites.pages.social-image.store', [$site, $page]), ['image' => UploadedFile::fake()->image('image.png'), 'social_image_id' => $foreign->id])->assertSessionHasErrors('social_image_id');
    expect($site->mediaAssets()->count())->toBe(0)->and(Storage::disk('s3')->allFiles())->toBe([]);
});

test('page media foreign key is enforced and deleting an asset clears references without deleting pages or blocks', function () {
    $site = Site::factory()->create();
    $home = $site->homePage()->firstOrFail();
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/shared", 'mime_type' => 'image/png']);
    $home->update(['social_image_id' => $asset->id]);
    $page = $site->pages()->create(['name' => 'About', 'position' => 1, 'social_image_id' => $asset->id]);
    $block = $page->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'Saved']]);
    expect(fn () => $page->update(['social_image_id' => 999999]))->toThrow(QueryException::class);
    $asset->delete();
    expect($home->fresh()->social_image_id)->toBeNull()->and($page->fresh()->social_image_id)->toBeNull()
        ->and($block->fresh()->content)->toBe(['body' => 'Saved']);
});

test('unused site metadata never becomes a fallback for Home metadata', function () {
    $site = Site::factory()->create(['slug' => 'no-fallback']);
    DB::table('sites')->where('id', $site->id)->update(['seo_title' => 'Obsolete title', 'seo_description' => 'Obsolete description']);
    $this->actingAs($site->user)->get(route('sites.pages.show', [$site, $site->homePage()->firstOrFail()]))
        ->assertInertia(fn (Assert $response) => $response->where('selected_page.seo_title', null)->where('selected_page.seo_description', null));
    $this->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    expect($site->fresh()->published_snapshot['pages'][0]['seo_title'])->toBeNull()
        ->and($site->fresh()->published_snapshot['pages'][0]['seo_description'])->toBeNull();
});

test('page editors expose only their own metadata with page-specific default titles and scoped images', function () {
    $site = Site::factory()->create(['name' => 'Grace Church', 'slug' => 'grace']);
    $home = $site->homePage()->firstOrFail();
    $home->update(['seo_title' => 'Preserved Home title', 'seo_description' => 'Preserved Home description']);
    $about = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $visit = $site->pages()->create(['name' => 'Visit', 'position' => 2, 'seo_title' => 'Come visit']);
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/social", 'mime_type' => 'image/png']);
    $about->update(['social_image_id' => $asset->id]);
    $this->actingAs($site->user);
    foreach ([$home, $about, $visit] as $selected) {
        $this->get(route('sites.pages.show', [$site, $selected]))->assertOk()->assertInertia(fn (Assert $response) => $response
            ->component('Sites/Show')->where('selected_page.id', $selected->id)
            ->where('selected_page.path', $selected->path)
            ->where('selected_page.seo_title', $selected->seo_title)
            ->where('selected_page.seo_description', $selected->seo_description)
            ->where('selected_page.default_title', $selected->is_home ? 'Grace Church' : $selected->name.' | Grace Church')
            ->where('selected_page.social_image', $selected->id === $about->id ? [
                'media_asset_id' => $asset->id, 'url' => route('sites.media.show', [$site, $asset]), 'alt_text' => null,
            ] : null)
            ->missing('site.seo_title')->missing('site.seo_description')->missing('site.social_image'));
    }
    $this->get(route('sites.show', $site))->assertInertia(fn (Assert $response) => $response
        ->component('Sites/Settings')->missing('selected_page')->missing('site.seo_title')->missing('site.social_image')->where('site.slug', 'grace'));
    $other = Site::factory()->create();
    $foreign = $other->mediaAssets()->create(['storage_key' => "sites/{$other->id}/social", 'mime_type' => 'image/png']);
    $about->update(['social_image_id' => $foreign->id]);
    $this->get(route('sites.pages.show', [$site, $about]))->assertInertia(fn (Assert $response) => $response->where('selected_page.social_image', null));
});
