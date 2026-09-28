<?php

use App\Actions\BuildSitePublicationSnapshot;
use App\Http\Controllers\PublishedSiteController;
use App\Models\Site;
use Illuminate\Support\Facades\Storage;

function textBlockContent(string $type, array $button = []): array
{
    $base = match ($type) {
        'plain_text' => ['body' => 'Welcome'],
        'text_image' => ['heading' => 'Visit us', 'body' => 'Welcome', 'media_asset_id' => null],
        default => ['heading' => 'Visit us', 'body' => 'Welcome'],
    };

    return [...$base, ...$button];
}

function textButton(string $type, ?int $target = null): array
{
    return [
        'button_label' => $type === 'none' ? '' : 'Learn more',
        'link_type' => $type,
        'target_block_id' => $type === 'section' ? $target : null,
        'target_page_id' => $type === 'page' ? $target : null,
        'external_url' => $type === 'external' ? 'https://example.test/visit' : '',
    ];
}

test('each text block saves every supported button destination and legacy content', function (string $blockType, string $linkType) {
    $site = Site::factory()->create();
    $page = $site->pages()->create(['name' => 'Visit', 'path' => 'visit', 'position' => 1, 'is_home' => false]);
    $section = $site->blocks()->create(['type' => 'plain_text', 'position' => 1, 'content' => ['body' => 'Target']]);
    $block = $site->blocks()->create(['type' => $blockType, 'position' => 0, 'content' => textBlockContent($blockType)]);
    $this->actingAs($site->user);

    $this->patch(route('sites.blocks.update', [$site, $block]), ['content' => textBlockContent($blockType)])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($block->fresh()->content)->toBe(textBlockContent($blockType));

    $button = textButton($linkType, $linkType === 'page' ? $page->id : $section->id);
    $this->patch(route('sites.blocks.update', [$site, $block]), ['content' => textBlockContent($blockType, $button)])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($block->fresh()->content)->toBe(textBlockContent($blockType, $button));
})->with(['about', 'heading_text', 'plain_text', 'text_image'])->with(['none', 'section', 'page', 'external']);

test('text buttons reject foreign and off-page destinations', function (string $linkType) {
    $site = Site::factory()->create();
    $other = Site::factory()->for($site->user)->create();
    $otherPage = $site->pages()->create(['name' => 'Other', 'path' => 'other', 'position' => 1, 'is_home' => false]);
    $offPage = $otherPage->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'Other']]);
    $block = $site->blocks()->create(['type' => 'about', 'position' => 0, 'content' => textBlockContent('about')]);
    $target = $linkType === 'page' ? $other->homePage()->firstOrFail()->id : $offPage->id;

    $this->actingAs($site->user)->patchJson(route('sites.blocks.update', [$site, $block]), [
        'content' => textBlockContent('about', textButton($linkType, $target)),
    ])->assertUnprocessable()->assertJsonValidationErrors('content.'.($linkType === 'page' ? 'target_page_id' : 'target_block_id'));
    expect($block->fresh()->content)->toBe(textBlockContent('about'));
})->with(['page', 'section']);

test('text buttons reject unsafe URLs and missing labels', function (array $changes, string $field) {
    $site = Site::factory()->create();
    $block = $site->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => textBlockContent('plain_text')]);
    $this->actingAs($site->user)->patchJson(route('sites.blocks.update', [$site, $block]), [
        'content' => textBlockContent('plain_text', [...textButton('external'), ...$changes]),
    ])->assertUnprocessable()->assertJsonValidationErrors('content.'.$field);
})->with([
    [['external_url' => 'javascript:alert(1)'], 'external_url'],
    [['button_label' => ''], 'button_label'],
]);

test('a text button cannot target itself or carry unsupported button fields', function () {
    $site = Site::factory()->create();
    $block = $site->blocks()->create(['type' => 'about', 'position' => 0, 'content' => textBlockContent('about')]);
    $this->actingAs($site->user)->patchJson(route('sites.blocks.update', [$site, $block]), [
        'content' => textBlockContent('about', textButton('section', $block->id)),
    ])->assertUnprocessable()->assertJsonValidationErrors('content.target_block_id');

    $image = $site->blocks()->create(['type' => 'image', 'position' => 1, 'content' => ['media_asset_id' => null]]);
    $this->patchJson(route('sites.blocks.update', [$site, $image]), [
        'content' => ['media_asset_id' => null, ...textButton('external')],
    ])->assertUnprocessable()->assertJsonValidationErrors('content');
});

test('deleting a text button destination clears the draft without changing the publication', function (string $linkType) {
    $site = Site::factory()->create();
    $page = $site->pages()->create(['name' => 'Visit', 'path' => 'visit', 'position' => 1, 'is_home' => false]);
    $section = $site->blocks()->create(['type' => 'plain_text', 'position' => 1, 'content' => ['body' => 'Target']]);
    $button = textButton($linkType, $linkType === 'page' ? $page->id : $section->id);
    $block = $site->blocks()->create(['type' => 'about', 'position' => 0, 'content' => textBlockContent('about', $button)]);
    $snapshot = app(BuildSitePublicationSnapshot::class)($site);
    $site->update(['published_snapshot' => $snapshot, 'published_at' => now()]);

    $url = $linkType === 'page' ? route('sites.pages.destroy', [$site, $page]) : route('sites.blocks.destroy', [$site, $section]);
    $this->actingAs($site->user)->delete($url)->assertRedirect();

    expect($block->fresh()->content)->toBe(textBlockContent('about', textButton('none')))
        ->and($site->fresh()->published_snapshot)->toBe($snapshot);
})->with(['page', 'section']);

test('published text buttons use snapshot links and escape labels', function (string $blockType, string $linkType) {
    $site = Site::factory()->create(['slug' => 'text-links']);
    $page = $site->pages()->create(['name' => 'Visit', 'path' => 'visit', 'position' => 1, 'is_home' => false]);
    $section = $site->blocks()->create(['type' => 'plain_text', 'position' => 1, 'content' => ['body' => 'Target']]);
    $button = textButton($linkType, $linkType === 'page' ? $page->id : $section->id);
    $button['button_label'] = 'Learn <more>';
    $block = $site->blocks()->create(['type' => $blockType, 'position' => 0, 'content' => textBlockContent($blockType, $button)]);
    $site->update(['published_snapshot' => app(BuildSitePublicationSnapshot::class)($site), 'published_at' => now()]);
    $block->update(['content' => textBlockContent($blockType, textButton('none'))]);

    $href = match ($linkType) {
        'section' => '#block-'.$section->id,
        'page' => route('sites.published.pages.show', [$site->slug, 'visit']),
        'external' => 'https://example.test/visit',
    };
    $html = $this->get(route('sites.published.show', $site->slug))->assertOk()->getContent();
    expect($html)->toContain('href="'.$href.'"')->toContain('Learn &lt;more&gt;');
    if ($linkType === 'external') {
        expect($html)->toContain('target="_blank" rel="noopener noreferrer"');
    }

    $rendered = app(PublishedSiteController::class)->renderSite($site)->original->getData()['blocks'];
    expect(collect($rendered)->firstWhere('id', $block->id)['hero_href'])->toBe($href);
})->with(['about', 'heading_text', 'plain_text', 'text_image'])->with(['section', 'page', 'external']);

test('published Text and Image buttons stay below the copy across from the image', function (string $layout) {
    Storage::fake('s3');
    $site = Site::factory()->create(['slug' => 'text-image-button-position']);
    $asset = $site->mediaAssets()->create([
        'storage_key' => "sites/{$site->id}/text-image-button-position",
        'mime_type' => 'image/png',
        'alt_text' => 'A church gathering.',
    ]);
    Storage::disk('s3')->put($asset->storage_key, 'image', ['visibility' => 'private']);
    $content = [
        ...textBlockContent('text_image', textButton('external')),
        'media_asset_id' => $asset->id,
        'style' => ['layout' => $layout],
    ];
    $site->blocks()->create(['type' => 'text_image', 'position' => 0, 'content' => $content]);
    $site->update(['published_snapshot' => app(BuildSitePublicationSnapshot::class)($site), 'published_at' => now()]);

    $html = $this->get(route('sites.published.show', $site->slug))->assertOk()->getContent();
    $sectionStart = strpos($html, 'data-block-type="text_image"');
    $sectionEnd = strpos($html, '</section>', $sectionStart);
    $section = substr($html, $sectionStart, $sectionEnd - $sectionStart);
    $copyPosition = strpos($section, 'Welcome');
    $buttonPosition = strpos($section, '>Learn more</a>');
    $imagePosition = strpos($section, '<figure');

    expect($sectionStart)->not->toBeFalse()
        ->and($copyPosition)->not->toBeFalse()
        ->and($buttonPosition)->not->toBeFalse()
        ->and($imagePosition)->not->toBeFalse()
        ->and($copyPosition)->toBeLessThan($buttonPosition)
        ->and($buttonPosition)->toBeLessThan($imagePosition);

    if ($layout === 'image_left') {
        expect($section)->toContain('md:order-2')->toContain('md:order-1');
    } else {
        expect($section)->toContain('md:order-2');
    }
})->with(['image_left', 'image_right']);

test('published text blocks omit stale and unsafe destinations', function (string $linkType, ?int $target, string $url) {
    $site = Site::factory()->create(['slug' => 'stale-text-link']);
    $button = textButton($linkType, $target);
    $button['external_url'] = $url;
    $site->blocks()->create(['type' => 'about', 'position' => 0, 'content' => textBlockContent('about', $button)]);
    $site->update(['published_snapshot' => app(BuildSitePublicationSnapshot::class)($site), 'published_at' => now()]);

    $html = $this->get(route('sites.published.show', $site->slug))->assertOk()->getContent();
    expect($html)->not->toContain('>Learn more</a>');
})->with([
    ['section', 999999, ''],
    ['page', 999999, ''],
    ['external', null, 'javascript:alert(1)'],
]);
