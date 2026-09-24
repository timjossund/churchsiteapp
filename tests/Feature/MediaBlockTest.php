<?php

use App\Models\Site;
use App\Models\User;

test('media blocks start with their exact empty content and append in order', function () {
    $site = Site::factory()->create();
    $this->actingAs($site->user);

    foreach (['image', 'text_image', 'video'] as $type) {
        $this->post(route('sites.blocks.store', $site), ['type' => $type])
            ->assertRedirect(route('sites.show', $site));
    }

    $blocks = $site->blocks()->orderBy('position')->get();

    expect($blocks->pluck('type')->all())->toBe(['image', 'text_image', 'video']);
    expect($blocks->pluck('position')->all())->toBe([0, 1, 2]);
    expect($blocks[0]->content)->toBe(['media_asset_id' => null]);
    expect($blocks[1]->content)->toBe(['heading' => '', 'body' => '', 'media_asset_id' => null]);
    expect($blocks[2]->content)->toBe(['url' => '']);
});

test('media blocks accept their exact editable content', function () {
    $site = Site::factory()->create();
    $image = $site->blocks()->create(['type' => 'image', 'position' => 0, 'content' => ['media_asset_id' => null]]);
    $textImage = $site->blocks()->create([
        'type' => 'text_image', 'position' => 1,
        'content' => ['heading' => '', 'body' => '', 'media_asset_id' => null],
    ]);
    $video = $site->blocks()->create(['type' => 'video', 'position' => 2, 'content' => ['url' => '']]);
    $this->actingAs($site->user);

    $this->patch(route('sites.blocks.update', [$site, $image]), [
        'content' => ['media_asset_id' => null],
    ])->assertRedirect(route('sites.show', $site));

    $textImageContent = ['heading' => 'Our community', 'body' => 'Join us this Sunday.', 'media_asset_id' => null];
    $this->patch(route('sites.blocks.update', [$site, $textImage]), [
        'content' => $textImageContent,
    ])->assertRedirect(route('sites.show', $site));
    expect($textImage->fresh()->content)->toBe($textImageContent);

    foreach ([
        'https://www.youtube.com/watch?v=abcDEF_1234&feature=share',
        'https://YOUTUBE.COM:443/watch?v=abcDEF_1234',
        'https://youtu.be/abcDEF_1234?si=share',
        'https://vimeo.com/123456789',
        'https://www.vimeo.com/123456789',
    ] as $url) {
        $this->patch(route('sites.blocks.update', [$site, $video]), [
            'content' => ['url' => $url],
        ])->assertRedirect(route('sites.show', $site));
        expect($video->fresh()->content)->toBe(['url' => $url]);
    }

    $this->patch(route('sites.blocks.update', [$site, $video]), [
        'content' => ['url' => '  https://youtube.com/watch?v=abcDEF_1234  '],
    ])->assertRedirect(route('sites.show', $site));
    expect($video->fresh()->content)->toBe(['url' => 'https://youtube.com/watch?v=abcDEF_1234']);

    $this->patch(route('sites.blocks.update', [$site, $video]), [
        'content' => ['url' => ''],
    ])->assertRedirect(route('sites.show', $site));
    expect($video->fresh()->content)->toBe(['url' => '']);
});

test('media blocks reject malformed fields and unsafe video URLs without changing saved content', function () {
    $site = Site::factory()->create();
    $image = $site->blocks()->create(['type' => 'image', 'position' => 0, 'content' => ['media_asset_id' => null]]);
    $textImage = $site->blocks()->create([
        'type' => 'text_image', 'position' => 1,
        'content' => ['heading' => 'Saved heading', 'body' => 'Saved text', 'media_asset_id' => null],
    ]);
    $video = $site->blocks()->create([
        'type' => 'video', 'position' => 2,
        'content' => ['url' => 'https://youtube.com/watch?v=abcDEF_1234'],
    ]);
    $this->actingAs($site->user);

    foreach ([
        [['media_asset_id' => 1], 'content.media_asset_id'],
        [['media_asset_id' => null, 'alt_text' => 'Injected'], 'content'],
    ] as [$content, $error]) {
        $this->patch(route('sites.blocks.update', [$site, $image]), ['content' => $content])
            ->assertSessionHasErrors($error);
        expect($image->fresh()->content)->toBe(['media_asset_id' => null]);
    }

    foreach ([
        [['heading' => 'Changed', 'body' => 'Text', 'media_asset_id' => 1], 'content.media_asset_id'],
        [['heading' => 'Changed', 'body' => 'Text'], 'content.media_asset_id'],
        [['heading' => 'Changed', 'body' => 'Text', 'media_asset_id' => null, 'extra' => 'x'], 'content'],
    ] as [$content, $error]) {
        $this->patch(route('sites.blocks.update', [$site, $textImage]), ['content' => $content])
            ->assertSessionHasErrors($error);
        expect($textImage->fresh()->content)->toBe(['heading' => 'Saved heading', 'body' => 'Saved text', 'media_asset_id' => null]);
    }

    foreach ([
        'http://youtube.com/watch?v=abcDEF_1234',
        'https://youtube.com.evil.test/watch?v=abcDEF_1234',
        'https://%79outube.com/watch?v=abcDEF_1234',
        'https://youtube.com/watch?v=abcDEF_1234&list=playlist',
        'https://youtube.com/watch?v=abcDEF_1234&v[]=bad',
        'https://youtube.com/watch?v=abcDEF_1234&v=abcDEF_1234',
        'https://youtube.com/watch?v[]=abcDEF_1234',
        'https://youtube.com/watch?v=abcDEF_1234&list[]=PL123',
        'https://youtube.com/watch?v=abcDEF_1234&',
        'https://youtube.com/watch?&v=abcDEF_1234',
        'https://youtu.be/abcDEF_1234?si=share&&feature=test',
        'https://@youtube.com/watch?v=abcDEF_1234',
        'https://:@youtube.com/watch?v=abcDEF_1234',
        "https://youtube.com/watch?v=abc\tDEF_1234",
        "https://youtu.be/abc\tEF_1234",
        "https://youtube.com/watch?v=abcDEF_1234&x=a\tb",
        "https://youtube.com/watch?v=abcDEF_1234#x\ty",
        'https://youtu.be/short',
        'javascript:alert(1)',
        'https://vimeo.com/123456789/abcdef',
        'https://vimeo.com@evil.test/123456789',
    ] as $url) {
        $this->patch(route('sites.blocks.update', [$site, $video]), [
            'content' => ['url' => $url],
        ])->assertSessionHasErrors('content.url');
        expect($video->fresh()->content)->toBe(['url' => 'https://youtube.com/watch?v=abcDEF_1234']);
    }

    $this->patch(route('sites.blocks.update', [$site, $video]), [
        'content' => ['url' => 'https://youtube.com/watch?v=abcDEF_1234', 'embed' => '<iframe>'],
    ])->assertSessionHasErrors('content');
});

test('image-bearing media blocks reject false and empty media references', function () {
    $site = Site::factory()->create();
    $image = $site->blocks()->create(['type' => 'image', 'position' => 0, 'content' => ['media_asset_id' => null]]);
    $textImage = $site->blocks()->create([
        'type' => 'text_image', 'position' => 1,
        'content' => ['heading' => 'Saved heading', 'body' => 'Saved text', 'media_asset_id' => null],
    ]);
    $this->actingAs($site->user);

    foreach ([[$image, ['media_asset_id' => null]], [$textImage, [
        'heading' => 'Saved heading', 'body' => 'Saved text', 'media_asset_id' => null,
    ]]] as [$block, $savedContent]) {
        foreach ([false, ''] as $invalidReference) {
            $content = $savedContent;
            $content['media_asset_id'] = $invalidReference;

            $this->patchJson(route('sites.blocks.update', [$site, $block]), ['content' => $content])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('content.media_asset_id');

            expect($block->fresh()->content)->toBe($savedContent);
        }
    }
});

test('media block routes remain scoped to the owning account', function () {
    $site = Site::factory()->create();
    $video = $site->blocks()->create(['type' => 'video', 'position' => 0, 'content' => ['url' => '']]);

    $this->actingAs(User::factory()->create())
        ->patch(route('sites.blocks.update', [$site, $video]), ['content' => ['url' => '']])
        ->assertNotFound();

    expect($video->fresh()->content)->toBe(['url' => '']);
});
