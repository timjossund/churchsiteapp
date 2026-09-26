<?php

use App\Models\Site;
use App\Models\SiteBlock;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('owners can create rename read and reorder draft pages without changing publication', function () {
    $site = Site::factory()->create(['slug' => 'pages']);
    $home = $site->homePage()->firstOrFail();
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertRedirect();
    $snapshot = $site->fresh()->published_snapshot;
    $this->post(route('sites.pages.store', $site), ['name' => '  About  '])->assertRedirect();
    $about = $site->pages()->where('is_home', false)->firstOrFail();
    expect($about->name)->toBe('About')->and($about->position)->toBe(1)->and($about->blocks()->count())->toBe(0);
    $this->patch(route('sites.pages.update', [$site, $about]), ['name' => 'Visit'])
        ->assertRedirect(route('sites.show', $site));
    $this->get(route('sites.pages.show', [$site, $about]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('selected_page.id', $about->id)->where('selected_page.name', 'Visit')->missing('pages')->has('blocks', 0));
    $this->patch(route('sites.pages.order', $site), ['expected_order' => [$home->id, $about->id], 'order' => [$about->id, $home->id]])->assertRedirect();
    expect($site->pages()->orderBy('position')->pluck('id')->all())->toBe([$about->id, $home->id])
        ->and($site->homePage()->firstOrFail()->id)->toBe($home->id)
        ->and($site->fresh()->published_snapshot)->toBe($snapshot);
});

test('invalid page names and client controlled identity fields leave pages unchanged', function () {
    $site = Site::factory()->create();
    $home = $site->homePage()->firstOrFail();
    $this->actingAs($site->user);
    foreach (['', '   ', "\u{2003}", str_repeat('a', 256)] as $name) {
        $this->post(route('sites.pages.store', $site), ['name' => $name])->assertSessionHasErrors('name');
        $this->patch(route('sites.pages.update', [$site, $home]), ['name' => $name])->assertSessionHasErrors('name');
    }
    foreach (['site_id' => $site->id, 'is_home' => true, 'position' => 99] as $key => $value) {
        $this->post(route('sites.pages.store', $site), ['name' => 'Injected', $key => $value])->assertSessionHasErrors($key);
        $this->patch(route('sites.pages.update', [$site, $home]), ['name' => 'Injected', $key => $value])->assertSessionHasErrors($key);
    }
    expect($site->pages()->count())->toBe(1)->and($home->fresh()->name)->toBe('Home');
});

test('invalid and stale page orders cannot partially reorder pages', function () {
    $site = Site::factory()->create();
    $home = $site->homePage()->firstOrFail();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $foreign = Site::factory()->create()->homePage()->firstOrFail();
    $current = [$home->id, $page->id];
    $this->actingAs($site->user);
    foreach ([
        ['expected_order' => $current, 'order' => [$home->id]],
        ['expected_order' => $current, 'order' => [$home->id, $home->id]],
        ['expected_order' => $current, 'order' => [$home->id, $foreign->id]],
        ['expected_order' => array_reverse($current), 'order' => $current],
        ['expected_order' => $current, 'order' => [3 => $home->id, 7 => $page->id]],
        ['order' => $current],
    ] as $payload) {
        $this->patch(route('sites.pages.order', $site), $payload)->assertSessionHasErrors();
        expect($site->pages()->orderBy('position')->pluck('id')->all())->toBe($current)
            ->and($site->pages()->orderBy('position')->pluck('position')->all())->toBe([0, 1]);
    }
});

test('page deletion protects Home and preserves other blocks media and publication', function () {
    Storage::fake('s3');
    $site = Site::factory()->create(['slug' => 'delete-pages']);
    $home = $site->homePage()->firstOrFail();
    $homeBlock = SiteBlock::factory()->for($site)->create();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $last = $site->pages()->create(['name' => 'Visit', 'position' => 2]);
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/shared", 'mime_type' => 'image/png']);
    Storage::disk('s3')->put($asset->storage_key, 'image');
    $draft = $page->blocks()->create(['type' => 'image', 'position' => 0, 'content' => ['media_asset_id' => $asset->id]]);
    $site->update(['logo_media_asset_id' => $asset->id]);
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertRedirect();
    $snapshot = $site->fresh()->published_snapshot;
    $timestamp = $site->fresh()->published_at;
    $this->delete(route('sites.pages.destroy', [$site, $home]))->assertSessionHasErrors('page');
    $this->delete(route('sites.pages.destroy', [$site, $page]))->assertRedirect(route('sites.show', $site));
    $this->assertDatabaseMissing('site_blocks', ['id' => $draft->id]);
    $this->assertDatabaseHas('site_blocks', ['id' => $homeBlock->id]);
    expect($last->fresh()->position)->toBe(1)->and($site->fresh()->published_snapshot)->toBe($snapshot)
        ->and($site->fresh()->published_at->equalTo($timestamp))->toBeTrue();
    Storage::disk('s3')->assertExists($asset->storage_key);
    $this->get(route('sites.published.media.show', [$site->slug, $asset]))->assertOk();
    $this->delete(route('sites.pages.destroy', [$site, $page]))->assertNotFound();
});

test('page endpoints reject guests other owners and pages outside the route site', function () {
    $site = Site::factory()->create();
    $page = $site->homePage()->firstOrFail();
    $this->get(route('sites.pages.show', [$site, $page]))->assertRedirect(route('login'));
    $this->post(route('sites.pages.store', $site), ['name' => 'No'])->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create());
    $this->get(route('sites.pages.show', [$site, $page]))->assertNotFound();
    $this->post(route('sites.pages.store', $site), ['name' => 'No'])->assertNotFound();
    $this->patch(route('sites.pages.update', [$site, $page]), ['name' => 'No'])->assertNotFound();
    $this->patch(route('sites.pages.order', $site), ['expected_order' => [$page->id], 'order' => [$page->id]])->assertNotFound();
    $this->delete(route('sites.pages.destroy', [$site, $page]))->assertNotFound();
    $other = Site::factory()->for($site->user)->create();
    $foreignPage = $other->homePage()->firstOrFail();
    $this->actingAs($site->user);
    $this->get(route('sites.pages.show', [$site, $foreignPage]))->assertNotFound();
    $this->patch(route('sites.pages.update', [$site, $foreignPage]), ['name' => 'No'])->assertNotFound();
    $this->delete(route('sites.pages.destroy', [$site, $foreignPage]))->assertNotFound();
    $this->get('/sites/'.$site->id.'/pages/nope')->assertNotFound();
});

test('nested block operations retain page selection and never change Home blocks', function () {
    $site = Site::factory()->create();
    $homeBlock = SiteBlock::factory()->for($site)->create(['content' => ['body' => 'Home']]);
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $this->actingAs($site->user)->post(route('sites.pages.blocks.store', [$site, $page]), ['type' => 'plain_text'])
        ->assertRedirect(route('sites.pages.show', [$site, $page]));
    $first = $page->blocks()->firstOrFail();
    $this->post(route('sites.pages.blocks.store', [$site, $page]), ['type' => 'about'])->assertRedirect();
    $second = $page->blocks()->orderByDesc('id')->firstOrFail();
    $this->patch(route('sites.pages.blocks.update', [$site, $page, $first]), ['content' => ['body' => 'About copy']])
        ->assertRedirect(route('sites.pages.show', [$site, $page]));
    $this->patch(route('sites.pages.blocks.order', [$site, $page]), [
        'expected_order' => [$first->id, $second->id], 'order' => [$second->id, $first->id],
    ])->assertRedirect(route('sites.pages.show', [$site, $page]));
    expect($page->blocks()->orderBy('position')->pluck('id')->all())->toBe([$second->id, $first->id]);
    $this->delete(route('sites.pages.blocks.destroy', [$site, $page, $second]))->assertRedirect();
    expect($first->fresh()->position)->toBe(0)->and($first->fresh()->content['body'])->toBe('About copy')
        ->and($homeBlock->fresh()->position)->toBe(0)->and($homeBlock->content['body'])->toBe('Home');
    $this->get(route('sites.pages.show', [$site, $page]))->assertInertia(fn (Assert $response) => $response
        ->has('blocks', 1)->where('blocks.0.id', $first->id));
});

test('cross page block ids and unqualified non Home operations are rejected', function () {
    $site = Site::factory()->create();
    $homeBlock = SiteBlock::factory()->for($site)->create();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $draft = $page->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'Draft']]);
    $this->actingAs($site->user);
    foreach ([route('sites.blocks.update', [$site, $draft]), route('sites.pages.blocks.update', [$site, $page, $homeBlock])] as $url) {
        $this->patch($url, ['content' => ['body' => 'Wrong']])->assertNotFound();
        $this->delete($url)->assertNotFound();
    }
    $this->patch(route('sites.pages.blocks.order', [$site, $page]), [
        'expected_order' => [$draft->id], 'order' => [$homeBlock->id],
    ])->assertSessionHasErrors('order');
    $this->post(route('sites.blocks.store', $site), ['type' => 'plain_text'])->assertRedirect();
    expect($page->blocks()->count())->toBe(1)->and($draft->fresh()->content['body'])->toBe('Draft')
        ->and($site->homePage()->firstOrFail()->blocks()->count())->toBe(2);
    $foreignPage = Site::factory()->for($site->user)->create()->homePage()->firstOrFail();
    $this->post(route('sites.pages.blocks.store', [$site, $foreignPage]), ['type' => 'plain_text'])->assertNotFound();
});

test('hero section links remain within their page and deletion clears only its links', function () {
    $site = Site::factory()->create();
    $homeTarget = SiteBlock::factory()->for($site)->create();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $target = $page->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'Target']]);
    $this->actingAs($site->user)->post(route('sites.pages.blocks.store', [$site, $page]), ['type' => 'hero']);
    $hero = $page->blocks()->where('type', 'hero')->firstOrFail();
    $content = ['heading' => '', 'body' => '', 'button_label' => 'Read', 'link_type' => 'section', 'target_block_id' => $target->id, 'external_url' => ''];
    $this->patch(route('sites.pages.blocks.update', [$site, $page, $hero]), ['content' => $content])->assertSessionHasNoErrors()->assertRedirect();
    $this->patch(route('sites.pages.blocks.update', [$site, $page, $hero]), ['content' => array_replace($content, ['target_block_id' => $homeTarget->id])])
        ->assertSessionHasErrors('content.target_block_id');
    expect($hero->fresh()->content['target_block_id'])->toBe($target->id);
    $this->delete(route('sites.pages.blocks.destroy', [$site, $page, $target]))->assertRedirect();
    expect($hero->fresh()->content['link_type'])->toBe('none')->and($homeTarget->fresh())->not->toBeNull();
});

test('nested uploads enforce page membership while allowing media reuse within the site', function () {
    Storage::fake('s3');
    $site = Site::factory()->create();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $image = $page->blocks()->create(['type' => 'image', 'position' => 0, 'content' => ['media_asset_id' => null]]);
    $this->actingAs($site->user)->post(route('sites.pages.blocks.image.store', [$site, $page, $image]), [
        'image' => UploadedFile::fake()->image('church.png'), 'alt_text' => 'Church',
    ])->assertRedirect(route('sites.pages.show', [$site, $page]));
    $assetId = $image->fresh()->content['media_asset_id'];
    expect($site->mediaAssets()->count())->toBe(1);
    $this->post(route('sites.blocks.image.store', [$site, $image]), ['image' => UploadedFile::fake()->image('wrong.png')])->assertNotFound();
    $home = $site->homePage()->firstOrFail();
    $this->post(route('sites.pages.blocks.image.store', [$site, $home, $image]), ['image' => UploadedFile::fake()->image('wrong.png')])->assertNotFound();
    expect($site->mediaAssets()->count())->toBe(1);
    $this->post(route('sites.blocks.store', $site), ['type' => 'image'])->assertRedirect();
    $homeImage = $home->blocks()->firstOrFail();
    $this->patch(route('sites.blocks.update', [$site, $homeImage]), ['content' => ['media_asset_id' => $assetId]])->assertRedirect();
    $foreignSite = Site::factory()->create();
    $foreign = $foreignSite->mediaAssets()->create(['storage_key' => 'foreign', 'mime_type' => 'image/png']);
    $this->patch(route('sites.pages.blocks.update', [$site, $page, $image]), ['content' => ['media_asset_id' => $foreign->id]])->assertSessionHasErrors('content.media_asset_id');
    expect($image->fresh()->content['media_asset_id'])->toBe($assetId);
});

test('page order rolls back every position when a write fails', function () {
    $site = Site::factory()->create();
    $home = $site->homePage()->firstOrFail();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $writes = 0;
    DB::listen(function ($query) use (&$writes) {
        if (str_starts_with($query->sql, 'update') && str_contains($query->sql, 'site_pages') && ++$writes === 2) {
            throw new RuntimeException('Simulated page write failure.');
        }
    });
    $this->actingAs($site->user)->patch(route('sites.pages.order', $site), [
        'expected_order' => [$home->id, $page->id], 'order' => [$page->id, $home->id],
    ])->assertServerError();
    expect($home->fresh()->position)->toBe(0)->and($page->fresh()->position)->toBe(1);
});

test('a page removed after validation is resolved again before block mutation', function () {
    $site = Site::factory()->create();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $block = $page->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'Draft']]);
    $deleted = false;
    SiteBlock::retrieved(function ($retrieved) use ($page, $block, &$deleted) {
        if ($retrieved->id === $block->id && ! $deleted) {
            $deleted = true;
            $page->delete();
        }
    });
    $this->actingAs($site->user)->patch(route('sites.pages.blocks.update', [$site, $page, $block]), ['content' => ['body' => 'Stale write']])->assertNotFound();
    $this->assertDatabaseMissing('site_pages', ['id' => $page->id]);
    $this->assertDatabaseMissing('site_blocks', ['id' => $block->id]);
});

test('all nested block mutations require the owning account', function () {
    Storage::fake('s3');
    $site = Site::factory()->create();
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $block = $page->blocks()->create(['type' => 'image', 'position' => 0, 'content' => ['media_asset_id' => null]]);
    $operations = [
        ['post', route('sites.pages.blocks.store', [$site, $page]), ['type' => 'plain_text']],
        ['patch', route('sites.pages.blocks.update', [$site, $page, $block]), ['content' => ['media_asset_id' => null]]],
        ['patch', route('sites.pages.blocks.order', [$site, $page]), ['expected_order' => [$block->id], 'order' => [$block->id]]],
        ['delete', route('sites.pages.blocks.destroy', [$site, $page, $block]), []],
        ['post', route('sites.pages.blocks.image.store', [$site, $page, $block]), ['image' => UploadedFile::fake()->image('no.png')]],
    ];
    foreach ($operations as [$method, $url, $payload]) {
        $this->$method($url, $payload)->assertRedirect(route('login'));
    }
    $this->actingAs(User::factory()->create());
    foreach ($operations as [$method, $url, $payload]) {
        $this->$method($url, $payload)->assertNotFound();
    }
    expect($page->blocks()->count())->toBe(1)->and($block->fresh()->content['media_asset_id'])->toBeNull()
        ->and($site->mediaAssets()->count())->toBe(0);
});

test('shared settings publishing and media changes return to the selected page', function () {
    Storage::fake('s3');
    $site = Site::factory()->create(['slug' => 'shared-settings']);
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $query = '?editor_page='.$page->id;
    $destination = route('sites.pages.show', [$site, $page]);
    $this->actingAs($site->user);
    $this->patch(route('sites.update', $site).$query, ['name' => 'New name', 'theme_key' => 'clean'])->assertRedirect($destination);
    $this->post(route('sites.logo.store', $site).$query, ['image' => UploadedFile::fake()->image('logo.png')])->assertRedirect($destination);
    $asset = $site->fresh()->logoMediaAsset;
    $this->patch(route('sites.media.update', [$site, $asset]).$query, ['alt_text' => 'Shared logo'])->assertRedirect($destination);
    $this->delete(route('sites.logo.destroy', $site).$query)->assertRedirect($destination);
    $this->post(route('sites.social-image.store', $site).$query, ['image' => UploadedFile::fake()->image('social.png')])->assertRedirect($destination);
    $this->delete(route('sites.social-image.destroy', $site).$query)->assertRedirect($destination);
    $this->post(route('sites.publish', $site).$query)->assertRedirect($destination);
    $ids = $site->pages()->orderBy('position')->pluck('id')->all();
    $this->patch(route('sites.pages.order', $site).$query, ['expected_order' => $ids, 'order' => array_reverse($ids)])->assertRedirect($destination);
    $this->get($destination)->assertInertia(fn (Assert $response) => $response
        ->where('selected_page.id', $page->id)->where('site.theme_key', 'clean')->where('site.name', 'New name'));
});

test('invalid or foreign return pages are rejected before shared mutations', function () {
    Storage::fake('s3');
    $site = Site::factory()->create(['slug' => 'safe-return']);
    $other = Site::factory()->for($site->user)->create();
    $foreignPage = $other->homePage()->firstOrFail();
    $this->actingAs($site->user);
    foreach ([$foreignPage->id, 999999] as $pageId) {
        $query = '?editor_page='.$pageId;
        $this->patch(route('sites.update', $site).$query, ['name' => 'Wrong'])->assertNotFound();
        $this->post(route('sites.publish', $site).$query)->assertNotFound();
        $this->post(route('sites.logo.store', $site).$query, ['image' => UploadedFile::fake()->image('no.png')])->assertNotFound();
    }
    foreach (['https://example.test', 'bad', ['bad']] as $value) {
        $this->patch(route('sites.update', $site).'?'.http_build_query(['editor_page' => $value]), ['name' => 'Wrong'])
            ->assertSessionHasErrors('editor_page');
    }
    expect($site->fresh()->name)->not->toBe('Wrong')
        ->and($site->published_snapshot)->toBeNull()
        ->and($site->mediaAssets()->count())->toBe(0);
    expect(Storage::disk('s3')->allFiles())->toBe([]);
});

test('site settings and page editor expose separate workspaces', function () {
    $site = Site::factory()->create(['theme_key' => 'bold', 'footer' => ['text' => 'Welcome']]);
    $home = $site->homePage()->firstOrFail();
    $block = $home->blocks()->create(['site_id' => $site->id, 'type' => 'hero', 'position' => 0, 'content' => ['heading' => 'Home content']]);
    $this->actingAs($site->user)->get(route('sites.show', $site))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Sites/Settings')->has('pages', 1)
        ->where('pages.0.id', $home->id)
        ->where('site.theme_key', 'bold')->where('site.footer.text', 'Welcome')
        ->missing('blocks')->missing('selected_page'));
    $this->get(route('sites.pages.show', [$site, $home]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Sites/Show')->where('selected_page.id', $home->id)
        ->where('blocks.0.id', $block->id)->missing('pages')
        ->where('site.theme_key', 'bold')->where('site.footer.text', 'Welcome'));
    $this->post(route('sites.pages.store', $site), ['name' => 'Visit'])
        ->assertRedirect(route('sites.show', $site));
    $visit = $site->pages()->where('is_home', false)->firstOrFail();
    $this->patch(route('sites.pages.update', [$site, $visit]), ['name' => 'About'])
        ->assertRedirect(route('sites.show', $site));
    $this->delete(route('sites.pages.destroy', [$site, $visit]))
        ->assertRedirect(route('sites.show', $site));
});

test('empty pages open with shared site images after creation', function (array $imageFields) {
    $site = Site::factory()->create();
    $asset = $site->mediaAssets()->create([
        'storage_key' => "sites/{$site->id}/shared-image",
        'mime_type' => 'image/png',
        'alt_text' => 'Church logo',
    ]);
    $site->update(array_fill_keys($imageFields, $asset->id));
    $this->actingAs($site->user)
        ->post(route('sites.pages.store', $site), ['name' => 'New page'])
        ->assertRedirect(route('sites.show', $site));
    $newPage = $site->pages()->where('is_home', false)->firstOrFail();

    foreach ([$site->homePage()->firstOrFail(), $newPage] as $selectedPage) {
        $this->get(route('sites.pages.show', [$site, $selectedPage]))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($site, $asset, $selectedPage, $imageFields) {
                $page->component('Sites/Show')
                    ->where('selected_page.id', $selectedPage->id)
                    ->has('blocks', 0);
                foreach (['logo_media_asset_id' => 'logo', 'social_image_id' => 'social_image'] as $field => $prop) {
                    if (in_array($field, $imageFields, true)) {
                        $page->where("site.{$prop}.media_asset_id", $asset->id)
                            ->where("site.{$prop}.url", route('sites.media.show', [$site, $asset]));
                    } else {
                        $page->where("site.{$prop}", null);
                    }
                }
            });
    }
})->with([
    'logo' => [['logo_media_asset_id']],
    'social image' => [['social_image_id']],
    'same image used for both' => [['logo_media_asset_id', 'social_image_id']],
]);

test('republishing Home updates public content and media without publishing other pages', function () {
    Storage::fake('s3');
    $site = Site::factory()->create(['slug' => 'republish-home']);
    $home = $site->homePage()->firstOrFail();
    $assets = collect(['old', 'new', 'private'])->map(function ($name) use ($site) {
        $asset = $site->mediaAssets()->create([
            'storage_key' => "sites/{$site->id}/{$name}",
            'mime_type' => 'image/png',
        ]);
        Storage::disk('s3')->put($asset->storage_key, $name);

        return $asset;
    });
    [$oldImage, $newImage, $privateImage] = $assets->all();
    $text = $home->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'Original Home']]);
    $image = $home->blocks()->create(['type' => 'image', 'position' => 1, 'content' => ['media_asset_id' => $oldImage->id]]);
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertRedirect();
    $publishedUrl = route('sites.published.show', $site->slug);
    $originalSnapshot = $site->fresh()->published_snapshot;
    $draft = $site->pages()->create(['name' => 'Private page', 'position' => 1]);
    $draftText = $draft->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'Private original']]);
    $draft->blocks()->create(['type' => 'image', 'position' => 1, 'content' => ['media_asset_id' => $privateImage->id]]);
    $deleted = $site->pages()->create(['name' => 'Removed draft', 'position' => 2]);
    $deleted->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'Removed private copy']]);

    $this->patch(route('sites.pages.blocks.update', [$site, $draft, $draftText]), ['content' => ['body' => 'Changed private copy']])->assertSessionHasNoErrors();
    $this->delete(route('sites.pages.destroy', [$site, $deleted]))->assertRedirect();
    $this->get(route('sites.pages.show', [$site, $deleted]))->assertNotFound();
    $this->post(route('sites.pages.blocks.store', [$site, $deleted]), ['type' => 'plain_text'])->assertNotFound();
    $this->patch(route('sites.pages.blocks.update', [$site, $home, $text]), ['content' => ['body' => 'Updated Home']])->assertSessionHasNoErrors();
    $this->patch(route('sites.pages.blocks.update', [$site, $home, $image]), ['content' => ['media_asset_id' => $newImage->id]])->assertSessionHasNoErrors();
    $this->patch(route('sites.update', $site), ['theme_key' => 'clean', 'footer' => ['text' => 'Shared footer']])->assertSessionHasNoErrors();

    foreach ([$home, $draft] as $page) {
        $this->get(route('sites.pages.show', [$site, $page]))->assertInertia(fn (Assert $response) => $response
            ->where('site.theme_key', 'clean')->where('site.footer.text', 'Shared footer')
            ->where('site.has_unpublished_changes', true));
    }
    expect($site->fresh()->published_snapshot)->toBe($originalSnapshot);
    $this->get($publishedUrl)->assertOk()->assertSee('Original Home')->assertDontSee('Updated Home');
    $this->get(route('sites.published.media.show', [$site->slug, $oldImage]))->assertOk();
    $this->get(route('sites.published.media.show', [$site->slug, $newImage]))->assertNotFound();

    $this->post(route('sites.publish', $site))->assertSessionHasNoErrors()->assertRedirect();
    $this->get($publishedUrl)->assertOk()->assertSee('Updated Home')->assertSee('Shared footer')
        ->assertDontSee('Original Home')->assertDontSee('Changed private copy')->assertDontSee('Removed private copy');
    $this->get(route('sites.published.media.show', [$site->slug, $newImage]))->assertOk();
    $this->get(route('sites.published.media.show', [$site->slug, $oldImage]))->assertNotFound();
    $this->get(route('sites.published.media.show', [$site->slug, $privateImage]))->assertNotFound();
    $this->get(route('sites.show', $site))->assertInertia(fn (Assert $response) => $response
        ->where('site.published_url', $publishedUrl)->where('site.has_unpublished_changes', false));
    expect(array_column($site->fresh()->published_snapshot['blocks'], 'id'))->toBe([$text->id, $image->id])
        ->and(array_column($site->fresh()->published_snapshot['media'], 'id'))->toBe([$newImage->id]);
    Storage::disk('s3')->assertExists($oldImage->storage_key);
    Storage::disk('s3')->assertExists($privateImage->storage_key);
});
