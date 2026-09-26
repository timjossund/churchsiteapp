<?php

use App\Models\Site;
use Illuminate\Support\Facades\Storage;

test('a visitor can view every published block type with the selected theme and metadata', function (string $theme) {
    Storage::fake('s3');
    $site = Site::factory()->create([
        'name' => 'Grace Church',
        'slug' => "grace-{$theme}",
        'theme_key' => $theme,
        'footer' => ['text' => 'Join us this Sunday'],
        'seo_title' => 'Grace Church Home',
        'seo_description' => 'A welcoming church in town.',
    ]);
    $asset = $site->mediaAssets()->create([
        'storage_key' => "sites/{$site->id}/published-photo",
        'mime_type' => 'image/png',
        'alt_text' => 'The congregation worships together',
    ]);
    Storage::disk('s3')->put($asset->storage_key, 'published image', ['visibility' => 'private']);
    $site->update([
        'logo_media_asset_id' => $asset->id,
        'social_image_id' => $asset->id,
    ]);
    $hero = $site->blocks()->create([
        'type' => 'hero',
        'position' => 0,
        'content' => [
            'heading' => 'Welcome home',
            'body' => 'We would love to meet you.',
            'button_label' => 'Who we are',
            'link_type' => 'section',
            'target_block_id' => null,
            'external_url' => '',
        ],
    ]);
    $about = $site->blocks()->create([
        'type' => 'about',
        'position' => 1,
        'content' => ['heading' => 'Our church', 'body' => 'Serving our neighbors.'],
    ]);
    $hero->update(['content' => array_merge($hero->content, ['target_block_id' => $about->id])]);
    $site->blocks()->createMany([
        ['type' => 'plain_text', 'position' => 2, 'content' => ['body' => 'A simple message.']],
        ['type' => 'heading_text', 'position' => 3, 'content' => ['heading' => 'Our mission', 'body' => 'Love God and neighbor.']],
        ['type' => 'service_times', 'position' => 4, 'content' => ['heading' => 'Gather with us', 'entries' => [
            ['day' => 'sunday', 'time' => '10:30', 'label' => 'Worship service'],
        ]]],
        ['type' => 'contact', 'position' => 5, 'content' => ['heading' => 'Get in touch', 'email' => 'hello@example.test', 'phone' => '+1 (555) 123-4567']],
        ['type' => 'image', 'position' => 6, 'content' => ['media_asset_id' => $asset->id]],
        ['type' => 'text_image', 'position' => 7, 'content' => ['heading' => 'Come as you are', 'body' => 'There is a place for you.', 'media_asset_id' => $asset->id]],
        ['type' => 'video', 'position' => 8, 'content' => ['url' => 'https://www.youtube.com/watch?v=abcdefghijk']],
        ['type' => 'hero', 'position' => 9, 'content' => [
            'heading' => 'Learn more', 'body' => '', 'button_label' => 'Visit our partner',
            'link_type' => 'external', 'target_block_id' => null, 'external_url' => 'https://example.test/partner',
        ]],
        ['type' => 'video', 'position' => 10, 'content' => ['url' => 'https://vimeo.com/123456789']],
    ]);

    $this->actingAs($site->user)->post(route('sites.publish', $site));
    auth()->logout();

    $response = $this->get(route('sites.published.show', $site->slug))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('<title>Grace Church Home</title>', false)
        ->assertSee('name="description" content="A welcoming church in town."', false)
        ->assertSee('property="og:image"', false)
        ->assertSee(route('sites.published.media.show', [$site->slug, $asset->id]), false)
        ->assertSee('data-theme="'.$theme.'"', false)
        ->assertSee('Welcome home')
        ->assertSee('#block-'.$about->id, false)
        ->assertSee('A simple message.')
        ->assertSee('Our mission')
        ->assertSee('10:30 AM')
        ->assertSee('mailto:hello@example.test', false)
        ->assertSee('tel:+15551234567', false)
        ->assertSee('The congregation worships together')
        ->assertSee('https://www.youtube-nocookie.com/embed/abcdefghijk', false)
        ->assertSee('https://player.vimeo.com/video/123456789', false)
        ->assertSee('href="https://example.test/partner"', false)
        ->assertSee('target="_blank" rel="noopener noreferrer"', false)
        ->assertSee('Join us this Sunday');

    expect($response->getContent())->not->toContain($asset->storage_key);
})->with(['warm', 'clean', 'bold']);

test('public rendering escapes text and omits unsafe links and unsupported video URLs', function () {
    $site = Site::factory()->create([
        'slug' => 'safe-rendering',
        'seo_title' => '<script>alert("head")</script>',
        'seo_description' => '<script>alert("description")</script>',
    ]);
    $site->blocks()->createMany([
        ['type' => 'plain_text', 'position' => 0, 'content' => ['body' => '<script>alert("body")</script>']],
        ['type' => 'hero', 'position' => 1, 'content' => [
            'heading' => 'Safe heading', 'body' => '', 'button_label' => 'Unsafe link',
            'link_type' => 'external', 'target_block_id' => null, 'external_url' => 'javascript:alert(1)',
        ]],
        ['type' => 'video', 'position' => 2, 'content' => ['url' => 'https://youtube.com.evil.test/watch?v=abcdefghijk']],
    ]);
    $this->actingAs($site->user)->post(route('sites.publish', $site));
    auth()->logout();

    $html = $this->get(route('sites.published.show', $site->slug))->assertOk()->getContent();
    expect($html)
        ->toContain('&lt;script&gt;alert(&quot;head&quot;)&lt;/script&gt;')
        ->toContain('&lt;script&gt;alert(&quot;description&quot;)&lt;/script&gt;')
        ->toContain('&lt;script&gt;alert(&quot;body&quot;)&lt;/script&gt;')
        ->not->toContain('href="javascript:')
        ->not->toContain('youtube.com.evil.test')
        ->not->toContain('<iframe');
});

test('published rendering applies curated block styles from the published snapshot', function () {
    Storage::fake('s3');
    $site = Site::factory()->create(['slug' => 'block-style-rendering']);
    $asset = $site->mediaAssets()->create([
        'storage_key' => "sites/{$site->id}/block-style-image",
        'mime_type' => 'image/png',
        'alt_text' => 'A welcoming church.',
    ]);
    Storage::disk('s3')->put($asset->storage_key, 'block image', ['visibility' => 'private']);
    $textImage = $site->blocks()->create([
        'type' => 'text_image',
        'position' => 0,
        'content' => [
            'heading' => 'Welcome',
            'body' => 'A place for everyone.',
            'media_asset_id' => $asset->id,
            'style' => [
                'layout' => 'image_left',
                'alignment' => 'center',
                'background' => 'soft',
            ],
        ],
    ]);
    $site->blocks()->create([
        'type' => 'about',
        'position' => 1,
        'content' => ['heading' => 'About', 'body' => 'Our story.'],
    ]);

    $this->actingAs($site->user)->post(route('sites.publish', $site));
    auth()->logout();

    $html = $this->get(route('sites.published.show', $site->slug))
        ->assertOk()
        ->assertSee('md:order-1', false)
        ->assertSee('md:order-2', false)
        ->assertSee('text-center bg-[var(--site-preview-soft)]', false)
        ->getContent();

    expect($site->fresh()->published_snapshot['blocks'][0]['content']['style'])->toBe([
        'layout' => 'image_left',
        'alignment' => 'center',
        'background' => 'soft',
    ]);

    $this->actingAs($site->user)
        ->patch(route('sites.blocks.update', [$site, $textImage]), [
            'content' => [
                'heading' => 'Welcome',
                'body' => 'A place for everyone.',
                'media_asset_id' => $asset->id,
                'style' => [
                    'layout' => 'image_right',
                    'alignment' => 'left',
                    'background' => 'theme',
                ],
            ],
        ])->assertRedirect(route('sites.show', $site));
    auth()->logout();

    $this->get(route('sites.published.show', $site->slug))
        ->assertOk()
        ->assertSee('text-center bg-[var(--site-preview-soft)]', false)
        ->assertSee('md:order-1', false);
});

test('unknown and unpublished sites return not found', function () {
    $unpublished = Site::factory()->create(['slug' => 'not-published']);

    $this->get(route('sites.published.show', $unpublished->slug))->assertNotFound();
    $this->get(route('sites.published.show', 'does-not-exist'))->assertNotFound();
});

test('an empty published site renders its shell without optional metadata or media', function () {
    $site = Site::factory()->create([
        'name' => 'Small Church',
        'slug' => 'small-church',
    ]);
    $this->actingAs($site->user)->post(route('sites.publish', $site));
    auth()->logout();

    $this->get(route('sites.published.show', $site->slug))
        ->assertOk()
        ->assertSee('<title>Small Church</title>', false)
        ->assertSee('data-theme="warm"', false)
        ->assertDontSee('name="description"', false)
        ->assertDontSee('property="og:image"', false);
});

test('public media stays tied to the published snapshot after draft edits', function () {
    Storage::fake('s3');
    $site = Site::factory()->create(['slug' => 'published-media']);
    $publishedAsset = $site->mediaAssets()->create([
        'storage_key' => "sites/{$site->id}/published-photo",
        'mime_type' => 'image/png',
        'alt_text' => 'Published description',
    ]);
    Storage::disk('s3')->put($publishedAsset->storage_key, 'published image bytes', ['visibility' => 'private']);
    $block = $site->blocks()->create([
        'type' => 'image',
        'position' => 0,
        'content' => ['media_asset_id' => $publishedAsset->id],
    ]);
    $this->actingAs($site->user)->post(route('sites.publish', $site));
    $publicUrl = route('sites.published.media.show', [$site->slug, $publishedAsset->id]);
    $publishedHtml = $this->get(route('sites.published.show', $site->slug))->assertOk()->getContent();

    $draftAsset = $site->mediaAssets()->create([
        'storage_key' => "sites/{$site->id}/draft-photo",
        'mime_type' => 'image/png',
        'alt_text' => 'Draft image',
    ]);
    Storage::disk('s3')->put($draftAsset->storage_key, 'draft image bytes', ['visibility' => 'private']);
    $this->patch(route('sites.blocks.update', [$site, $block]), [
        'content' => ['media_asset_id' => null],
    ])->assertRedirect(route('sites.show', $site));
    $this->patch(route('sites.media.update', [$site, $publishedAsset]), [
        'alt_text' => 'Changed draft description',
    ])->assertRedirect(route('sites.show', $site));
    $otherSite = Site::factory()->create();
    $foreignAsset = $otherSite->mediaAssets()->create([
        'storage_key' => "sites/{$otherSite->id}/foreign-photo",
        'mime_type' => 'image/png',
    ]);
    Storage::disk('s3')->put($foreignAsset->storage_key, 'foreign image bytes', ['visibility' => 'private']);
    auth()->logout();

    $this->get(route('sites.published.show', $site->slug))
        ->assertOk()
        ->assertSee('Published description')
        ->assertDontSee('Changed draft description');
    $this->get($publicUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertStreamedContent('published image bytes');

    $this->get(route('sites.published.media.show', [$site->slug, $draftAsset->id]))->assertNotFound();
    $this->get(route('sites.published.media.show', [$site->slug, $foreignAsset->id]))->assertNotFound();

    Storage::disk('s3')->delete($publishedAsset->storage_key);
    $missing = $this->get($publicUrl)->assertNotFound();
    expect($missing->getContent())->not->toContain($publishedAsset->storage_key);
});
