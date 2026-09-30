<?php

use App\Actions\BuildSitePublicationSnapshot;
use App\Http\Controllers\PublishedSiteController;
use App\Http\Controllers\SiteBlockController;
use App\Http\Requests\UpdateSiteBlockRequest;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function heroContentForTest(array $overrides = []): array
{
    return array_replace([
        'heading' => 'Welcome', 'body' => 'Join us', 'button_label' => '',
        'link_type' => 'none', 'target_block_id' => null, 'external_url' => '',
    ], $overrides);
}

test('legacy hero content saves without adding optional settings', function () {
    $site = Site::factory()->create();
    $content = heroContentForTest();
    $block = $site->blocks()->create(['type' => 'hero', 'position' => 0, 'content' => $content]);

    $this->actingAs($site->user)->patch(route('sites.blocks.update', [$site, $block]), [
        'content' => $content,
    ])->assertRedirect();

    expect($block->fresh()->content)->toBe($content);
});

test('hero settings save with a same-site image and an explicitly hidden welcome label', function (string $height, string $overlay, string $motion) {
    $site = Site::factory()->create();
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/hero", 'mime_type' => 'image/png']);
    $block = $site->blocks()->create(['type' => 'hero', 'position' => 0, 'content' => heroContentForTest()]);
    $content = heroContentForTest([
        'welcome_label' => '', 'media_asset_id' => $asset->id,
        'style' => ['height' => $height, 'overlay' => $overlay, 'motion' => $motion, 'alignment' => 'center', 'background' => 'soft'],
    ]);

    $this->actingAs($site->user)->patch(route('sites.blocks.update', [$site, $block]), ['content' => $content])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect($block->fresh()->content)->toBe($content);
})->with([
    ['current', 'light', 'normal'], ['medium', 'medium', 'fixed'], ['full', 'dark', 'half'],
]);

test('invalid hero options do not change saved content', function (array $overrides, string $error) {
    $site = Site::factory()->create();
    $original = heroContentForTest();
    $block = $site->blocks()->create(['type' => 'hero', 'position' => 0, 'content' => $original]);

    $this->actingAs($site->user)->patchJson(route('sites.blocks.update', [$site, $block]), [
        'content' => heroContentForTest($overrides),
    ])->assertUnprocessable()->assertJsonValidationErrors($error);

    expect($block->fresh()->content)->toBe($original);
})->with([
    [['style' => ['text_background' => 'false']], 'content.style.text_background'],
    [['style' => ['text_background' => 0]], 'content.style.text_background'],
    [['style' => ['height' => '200px']], 'content.style.height'],
    [['style' => ['overlay' => 0.5]], 'content.style.overlay'],
    [['style' => ['motion' => 'fast']], 'content.style.motion'],
    [['style' => ['css' => 'color:red']], 'content.style'],
    [['style' => ['layout' => 'image_left']], 'content.style.layout'],
    [['welcome_label' => ['invalid']], 'content.welcome_label'],
    [['media_asset_id' => ''], 'content.media_asset_id'],
    [['media_asset_id' => 999999], 'content.media_asset_id'],
]);

test('non-hero blocks reject hero-only style keys', function (string $key, string $value) {
    $site = Site::factory()->create();
    $block = $site->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'Original']]);

    $this->actingAs($site->user)->patchJson(route('sites.blocks.update', [$site, $block]), [
        'content' => ['body' => 'Changed', 'style' => [$key => $value]],
    ])->assertUnprocessable()->assertJsonValidationErrors('content.style');

    expect($block->fresh()->content)->toBe(['body' => 'Original']);
})->with([['height', 'full'], ['overlay', 'dark'], ['motion', 'fixed']]);

test('hero images and updates remain scoped to the owner and site', function () {
    Storage::fake('s3');
    $site = Site::factory()->create();
    $otherSite = Site::factory()->for($site->user)->create();
    $asset = $otherSite->mediaAssets()->create(['storage_key' => "sites/{$otherSite->id}/hero", 'mime_type' => 'image/png']);
    $original = heroContentForTest();
    $block = $site->blocks()->create(['type' => 'hero', 'position' => 0, 'content' => $original]);

    $this->postJson(route('sites.blocks.image.store', [$site, $block]), [
        'image' => UploadedFile::fake()->image('hero.png'),
    ])->assertUnauthorized();
    $this->actingAs(User::factory()->create())->patchJson(route('sites.blocks.update', [$site, $block]), [
        'content' => heroContentForTest(['welcome_label' => 'Changed']),
    ])->assertNotFound();
    $this->postJson(route('sites.blocks.image.store', [$site, $block]), [
        'image' => UploadedFile::fake()->image('hero.png'),
    ])->assertNotFound();
    $this->actingAs($site->user)->patchJson(route('sites.blocks.update', [$site, $block]), [
        'content' => heroContentForTest(['media_asset_id' => $asset->id]),
    ])->assertUnprocessable()->assertJsonValidationErrors('content.media_asset_id');

    expect($block->fresh()->content)->toBe($original)
        ->and($site->mediaAssets()->count())->toBe(0);
});

test('hero upload replacement and clearing preserve the previous published image', function () {
    Storage::fake('s3');
    $site = Site::factory()->create();
    $page = $site->homePage()->firstOrFail();
    $content = heroContentForTest(['welcome_label' => 'Come visit', 'style' => ['motion' => 'half']]);
    $block = $page->blocks()->create(['type' => 'hero', 'position' => 0, 'content' => $content]);
    $this->actingAs($site->user);

    $this->post(route('sites.pages.blocks.image.store', [$site, $page, $block]), [
        'image' => UploadedFile::fake()->image('hero.png')->size(5120),
    ])->assertRedirect(route('sites.pages.show', [$site, $page]))->assertSessionHasNoErrors();
    $first = $site->mediaAssets()->sole();
    expect($block->fresh()->content)->toBe([...$content, 'media_asset_id' => $first->id]);
    expect(Storage::disk('s3')->getVisibility($first->storage_key))->toBe('private');

    $snapshot = app(BuildSitePublicationSnapshot::class)($site);
    $site->update(['published_snapshot' => $snapshot, 'published_at' => now()]);
    expect($snapshot['pages'][0]['blocks'][0]['content']['media_asset_id'])->toBe($first->id)
        ->and($snapshot['media'][0]['id'])->toBe($first->id);

    $this->post(route('sites.blocks.image.store', [$site, $block]), [
        'image' => UploadedFile::fake()->image('replacement.jpg'),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $secondId = $block->fresh()->content['media_asset_id'];
    expect($secondId)->not->toBe($first->id);
    $this->patch(route('sites.blocks.update', [$site, $block]), [
        'content' => [...$content, 'media_asset_id' => null],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($block->fresh()->content['media_asset_id'])->toBeNull()
        ->and($site->fresh()->published_snapshot)->toBe($snapshot)
        ->and($site->mediaAssets()->count())->toBe(2);
    foreach ($site->mediaAssets as $asset) {
        Storage::disk('s3')->assertExists($asset->storage_key);
    }
});

test('both hero buttons support page section and external destinations', function (string $type) {
    $site = Site::factory()->create();
    $page = $site->homePage()->firstOrFail();
    $section = $site->blocks()->create(['type' => 'plain_text', 'position' => 1, 'content' => ['body' => 'Hello']]);
    $button = ['button_label' => 'Visit', 'link_type' => $type, 'target_block_id' => $type === 'section' ? $section->id : null,
        'target_page_id' => $type === 'page' ? $page->id : null, 'external_url' => $type === 'external' ? 'https://example.test/visit' : ''];
    $block = $site->blocks()->create(['type' => 'hero', 'position' => 0, 'content' => heroContentForTest()]);
    $content = heroContentForTest([...$button, 'secondary_button' => $button]);
    $this->actingAs($site->user)->patch(route('sites.blocks.update', [$site, $block]), ['content' => $content])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($block->fresh()->content)->toBe($content);
})->with(['page', 'section', 'external']);

test('both hero buttons reject foreign pages and off-page sections', function (string $slot, string $type) {
    $site = Site::factory()->create();
    $other = Site::factory()->for($site->user)->create();
    $otherPage = $site->pages()->create(['name' => 'Other', 'path' => 'other', 'position' => 1, 'is_home' => false]);
    $target = $otherPage->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'Other']]);
    $block = $site->blocks()->create(['type' => 'hero', 'position' => 0, 'content' => heroContentForTest()]);
    $button = ['button_label' => 'Visit', 'link_type' => $type, 'target_block_id' => $type === 'section' ? $target->id : null,
        'target_page_id' => $type === 'page' ? $other->homePage()->firstOrFail()->id : null, 'external_url' => ''];
    $content = heroContentForTest($slot === 'primary' ? $button : ['secondary_button' => $button]);
    $prefix = $slot === 'primary' ? 'content.' : 'content.secondary_button.';
    $this->actingAs($site->user)->patchJson(route('sites.blocks.update', [$site, $block]), ['content' => $content])
        ->assertUnprocessable()->assertJsonValidationErrors($prefix.($type === 'page' ? 'target_page_id' : 'target_block_id'));
    expect($block->fresh()->content)->toBe(heroContentForTest());
})->with(['primary', 'secondary'])->with(['page', 'section']);

test('deleting destinations disables both draft buttons without changing publication', function (string $type) {
    $site = Site::factory()->create();
    $page = $site->pages()->create(['name' => 'Visit', 'path' => 'visit', 'position' => 1, 'is_home' => false]);
    $section = $site->blocks()->create(['type' => 'plain_text', 'position' => 1, 'content' => ['body' => 'Hello']]);
    $button = ['button_label' => 'Visit', 'link_type' => $type, 'target_block_id' => $type === 'section' ? $section->id : null,
        'target_page_id' => $type === 'page' ? $page->id : null, 'external_url' => ''];
    $block = $site->blocks()->create(['type' => 'hero', 'position' => 0, 'content' => heroContentForTest([...$button, 'secondary_button' => $button])]);
    $snapshot = app(BuildSitePublicationSnapshot::class)($site);
    $site->update(['published_snapshot' => $snapshot, 'published_at' => now()]);
    $url = $type === 'page' ? route('sites.pages.destroy', [$site, $page]) : route('sites.blocks.destroy', [$site, $section]);
    $this->actingAs($site->user)->delete($url)->assertRedirect();
    expect($block->fresh()->content['link_type'])->toBe('none')
        ->and($block->fresh()->content['secondary_button']['link_type'])->toBe('none')
        ->and($site->fresh()->published_snapshot)->toBe($snapshot);
})->with(['page', 'section']);

test('published hero page links resolve snapshot paths on platform and custom domains', function () {
    $site = Site::factory()->create(['slug' => 'hero-links']);
    $page = $site->pages()->create(['name' => 'Visit', 'path' => 'visit', 'position' => 1, 'is_home' => false]);
    $button = ['button_label' => 'Visit', 'link_type' => 'page', 'target_block_id' => null, 'target_page_id' => $page->id, 'external_url' => ''];
    $site->blocks()->create(['type' => 'hero', 'position' => 0, 'content' => heroContentForTest([...$button, 'secondary_button' => $button])]);
    $publish = function () use ($site): void {
        $site->update(['published_snapshot' => app(BuildSitePublicationSnapshot::class)($site), 'published_at' => now()]);
    };
    $publish();
    $page->update(['path' => 'new-visit']);
    $controller = app(PublishedSiteController::class);
    $blocks = $controller->renderSite($site)->original->getData()['blocks'];
    expect($blocks[0]['hero_href'])->toBe(route('sites.published.pages.show', [$site->slug, 'visit']))
        ->and($blocks[0]['hero_secondary_href'])->toBe($blocks[0]['hero_href']);
    $publish();
    $blocks = $controller->renderSite($site, null, 'www.example.test')->original->getData()['blocks'];
    expect($blocks[0]['hero_href'])->toBe('https://www.example.test/new-visit')
        ->and($blocks[0]['hero_secondary_href'])->toBe($blocks[0]['hero_href']);
    $page->delete();
    $publish();
    $blocks = $controller->renderSite($site)->original->getData()['blocks'];
    expect($blocks[0]['hero_href'])->toBeNull()->and($blocks[0]['hero_secondary_href'])->toBeNull();
});

test('hero save rechecks destinations after validation', function (string $type) {
    $site = Site::factory()->create();
    $block = $site->blocks()->create(['type' => 'hero', 'position' => 0, 'content' => heroContentForTest()]);
    $request = Mockery::mock(UpdateSiteBlockRequest::class);
    $request->shouldReceive('route')->with('block')->andReturn($block->id);
    $request->shouldReceive('route')->with('page')->andReturn(null);
    $request->shouldReceive('user')->andReturn($site->user);
    $request->shouldReceive('validated')->with('content')->andReturn(heroContentForTest([
        'secondary_button' => ['button_label' => 'Visit', 'link_type' => $type,
            'target_block_id' => $type === 'section' ? 999999 : null,
            'target_page_id' => $type === 'page' ? 999999 : null, 'external_url' => ''],
    ]));
    try {
        app(SiteBlockController::class)->update($request, $site->id);
        $this->fail('A missing target must be rejected even after request validation.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('content.secondary_button.'.($type === 'page' ? 'target_page_id' : 'target_block_id'));
    }
    expect($block->fresh()->content)->toBe(heroContentForTest());
})->with(['page', 'section']);

test('switching button modes clears inactive destinations', function () {
    $site = Site::factory()->create();
    $block = $site->blocks()->create(['type' => 'hero', 'position' => 0, 'content' => heroContentForTest()]);
    $button = ['button_label' => 'Old label', 'link_type' => 'none', 'target_block_id' => 999999, 'target_page_id' => 999999, 'external_url' => 'https://example.test/old'];
    $content = heroContentForTest([...$button, 'link_type' => 'external', 'secondary_button' => $button]);
    $this->actingAs($site->user)->patch(route('sites.blocks.update', [$site, $block]), ['content' => $content])
        ->assertRedirect()->assertSessionHasNoErrors();
    $saved = $block->fresh()->content;
    expect($saved['target_block_id'])->toBeNull()->and($saved['target_page_id'])->toBeNull()
        ->and($saved['external_url'])->toBe('https://example.test/old')
        ->and($saved['secondary_button'])->toBe(['button_label' => '', 'link_type' => 'none', 'target_block_id' => null, 'target_page_id' => null, 'external_url' => '']);
});

test('secondary buttons reject unsafe URLs and missing labels', function (array $changes, string $error) {
    $site = Site::factory()->create();
    $block = $site->blocks()->create(['type' => 'hero', 'position' => 0, 'content' => heroContentForTest()]);
    $button = array_replace(['button_label' => 'Visit', 'link_type' => 'external', 'target_block_id' => null, 'target_page_id' => null, 'external_url' => 'https://example.test'], $changes);
    $this->actingAs($site->user)->patchJson(route('sites.blocks.update', [$site, $block]), [
        'content' => heroContentForTest(['secondary_button' => $button]),
    ])->assertUnprocessable()->assertJsonValidationErrors('content.secondary_button.'.$error);
})->with([[['external_url' => 'javascript:alert(1)'], 'external_url'], [['button_label' => ''], 'button_label']]);

test('published heroes render escaped labels image presets and both buttons from the snapshot', function () {
    $site = Site::factory()->create(['slug' => 'styled-hero']);
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/hero", 'mime_type' => 'image/png']);
    $button = ['button_label' => 'Second <script>', 'link_type' => 'external', 'target_block_id' => null, 'target_page_id' => null, 'external_url' => 'https://example.test/visit'];
    $block = $site->blocks()->create(['type' => 'hero', 'position' => 0, 'content' => heroContentForTest([
        'welcome_label' => 'Welcome <script>', 'media_asset_id' => $asset->id,
        'style' => ['height' => 'full', 'overlay' => 'light', 'motion' => 'fixed',
            'spacing' => 'spacious', 'content_width' => 'narrow', 'heading_size' => 'large', 'background' => 'accent'],
        'button_label' => 'Home', 'link_type' => 'page', 'target_page_id' => $site->homePage()->firstOrFail()->id,
        'secondary_button' => $button,
    ])]);
    $site->update(['published_snapshot' => app(BuildSitePublicationSnapshot::class)($site), 'published_at' => now()]);
    $block->update(['content' => heroContentForTest(['welcome_label' => 'Unpublished edit'])]);
    $this->get(route('sites.published.show', $site->slug))->assertOk()
        ->assertSee('Welcome &lt;script&gt;', false)->assertSee('Second &lt;script&gt;', false)
        ->assertSee('data-height="full"', false)->assertSee('data-overlay="light"', false)
        ->assertSee('data-motion="fixed"', false)->assertSee('data-has-image="true"', false)
        ->assertSee('data-spacing="spacious"', false)->assertSee('data-content-width="narrow"', false)
        ->assertSee('data-heading-size="large"', false)->assertSee('data-background="accent"', false)
        ->assertSee('href="https://example.test/visit"', false)
        ->assertSee('src="'.route('sites.published.media.show', [$site->slug, $asset]).'"', false)
        ->assertDontSee('Unpublished edit');
    $block->update(['content' => heroContentForTest(['welcome_label' => '', 'heading' => 'Hello'])]);
    $site->update(['published_snapshot' => app(BuildSitePublicationSnapshot::class)($site)]);
    $this->get(route('sites.published.show', $site->slug))->assertOk()
        ->assertDontSee('data-has-image="true"', false)->assertDontSee('>Welcome</p>', false);
});

test('hero text background choice is saved and frozen until republished', function () {
    $site = Site::factory()->create(['slug' => 'hero-text-background']);
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/hero", 'mime_type' => 'image/png']);
    $content = heroContentForTest(['media_asset_id' => $asset->id]);
    $block = $site->blocks()->create(['type' => 'hero', 'position' => 0, 'content' => $content]);
    $site->update(['published_snapshot' => app(BuildSitePublicationSnapshot::class)($site), 'published_at' => now()]);
    $this->get(route('sites.published.show', $site->slug))->assertOk()
        ->assertSee('data-text-background="true"', false);

    foreach ([false, true] as $enabled) {
        $content['style'] = ['text_background' => $enabled, 'overlay' => 'dark'];
        $this->actingAs($site->user)->patch(route('sites.blocks.update', [$site, $block]), ['content' => $content])
            ->assertRedirect()->assertSessionHasNoErrors();
        expect($block->fresh()->content['style']['text_background'])->toBe($enabled);
        $this->get(route('sites.published.show', $site->slug))->assertOk()
            ->assertSee('data-text-background="'.($enabled ? 'false' : 'true').'"', false);
        $site->update(['published_snapshot' => app(BuildSitePublicationSnapshot::class)($site)]);
        $this->get(route('sites.published.show', $site->slug))->assertOk()
            ->assertSee('data-text-background="'.($enabled ? 'true' : 'false').'"', false)
            ->assertSee('data-overlay="dark"', false)->assertSee('data-has-image="true"', false);
    }
});

test('non-hero blocks cannot save the text background setting', function () {
    $site = Site::factory()->create();
    $block = $site->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'Original']]);
    $this->actingAs($site->user)->patchJson(route('sites.blocks.update', [$site, $block]), [
        'content' => ['body' => 'Changed', 'style' => ['text_background' => false]],
    ])->assertUnprocessable()->assertJsonValidationErrors('content.style');
    expect($block->fresh()->content)->toBe(['body' => 'Original']);
});
