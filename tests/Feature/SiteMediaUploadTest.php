<?php

use App\Models\Site;
use App\Models\SiteBlock;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('owners can upload private JPEG and PNG assets to image-bearing blocks', function () {
    Storage::fake('s3');

    $site = Site::factory()->create();
    $image = $site->blocks()->create(['type' => 'image', 'position' => 0, 'content' => ['media_asset_id' => null]]);
    $textImage = $site->blocks()->create([
        'type' => 'text_image', 'position' => 1,
        'content' => ['heading' => 'Welcome', 'body' => '', 'media_asset_id' => null],
    ]);
    $this->actingAs($site->user);

    $this->post(route('sites.blocks.image.store', [$site, $image]), [
        'image' => UploadedFile::fake()->image('church.png', 20, 20)->size(5120),
        'alt_text' => 'Church members gathered',
    ])->assertRedirect(route('sites.show', $site));

    $this->post(route('sites.blocks.image.store', [$site, $textImage]), [
        'image' => UploadedFile::fake()->image('sanctuary.jpg', 20, 20),
        'alt_text' => 'Sanctuary',
    ])->assertRedirect(route('sites.show', $site));

    $assets = $site->mediaAssets()->orderBy('id')->get();

    expect($assets)->toHaveCount(2)
        ->and($assets[0]->mime_type)->toBe('image/png')
        ->and($assets[0]->alt_text)->toBe('Church members gathered')
        ->and($assets[1]->mime_type)->toBe('image/jpeg')
        ->and($image->fresh()->content['media_asset_id'])->toBe($assets[0]->id)
        ->and($textImage->fresh()->content['media_asset_id'])->toBe($assets[1]->id);

    foreach ($assets as $asset) {
        expect($asset->storage_key)->toStartWith("sites/{$site->id}/")
            ->and($asset->storage_key)->not->toContain('church.png', 'sanctuary.jpg');
        Storage::disk('s3')->assertExists($asset->storage_key);
        expect(Storage::disk('s3')->getVisibility($asset->storage_key))->toBe('private');
    }
});

test('invalid files and files over 5 MB leave an existing block image unchanged', function () {
    Storage::fake('s3');

    $site = Site::factory()->create();
    $asset = $site->mediaAssets()->create([
        'storage_key' => "sites/{$site->id}/existing",
        'mime_type' => 'image/png',
        'alt_text' => 'Existing image',
    ]);
    Storage::disk('s3')->put($asset->storage_key, 'existing image bytes', ['visibility' => 'private']);
    $block = $site->blocks()->create([
        'type' => 'image', 'position' => 0, 'content' => ['media_asset_id' => $asset->id],
    ]);
    $this->actingAs($site->user);

    $spoofedPath = tempnam(sys_get_temp_dir(), 'churchsite-upload-');
    file_put_contents($spoofedPath, 'plain text');
    $spoofedImage = new UploadedFile($spoofedPath, 'spoofed.png', 'image/png', UPLOAD_ERR_OK, true);

    try {
        $this->postJson(route('sites.blocks.image.store', [$site, $block]), [
            'image' => $spoofedImage,
        ])->assertUnprocessable()->assertJsonValidationErrors('image');
    } finally {
        @unlink($spoofedPath);
    }

    $this->postJson(route('sites.blocks.image.store', [$site, $block]), [
        'image' => UploadedFile::fake()->image('too-large.png', 10, 10)->size(5121),
    ])->assertUnprocessable()->assertJsonValidationErrors('image');

    $this->postJson(route('sites.blocks.image.store', [$site, $block]), [
        'image' => UploadedFile::fake()->image('injected.png', 10, 10),
        'logo_media_asset_id' => 123,
    ])->assertUnprocessable()->assertJsonValidationErrors('logo_media_asset_id');

    expect($block->fresh()->content['media_asset_id'])->toBe($asset->id)
        ->and($asset->fresh()->alt_text)->toBe('Existing image')
        ->and($site->mediaAssets()->count())->toBe(1);
    Storage::disk('s3')->assertExists($asset->storage_key);
});

test('storage failure is returned as an image error without changing the saved reference', function () {
    $site = Site::factory()->create();
    $asset = $site->mediaAssets()->create([
        'storage_key' => "sites/{$site->id}/existing",
        'mime_type' => 'image/png',
        'alt_text' => 'Existing image',
    ]);
    $block = $site->blocks()->create([
        'type' => 'image', 'position' => 0, 'content' => ['media_asset_id' => $asset->id],
    ]);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->andReturn(false);
    $disk->shouldReceive('delete')->once()->andReturn(true);
    Storage::shouldReceive('disk')->with('s3')->once()->andReturn($disk);

    $this->actingAs($site->user)->postJson(route('sites.blocks.image.store', [$site, $block]), [
        'image' => UploadedFile::fake()->image('new.png', 10, 10),
    ])->assertUnprocessable()->assertJsonValidationErrors('image');

    expect($block->fresh()->content['media_asset_id'])->toBe($asset->id)
        ->and($site->mediaAssets()->count())->toBe(1);
});

test('a database failure rolls back the new block reference and cleans up its uploaded object', function () {
    Storage::fake('s3');

    $site = Site::factory()->create();
    $oldAsset = $site->mediaAssets()->create([
        'storage_key' => "sites/{$site->id}/old",
        'mime_type' => 'image/png',
        'alt_text' => 'Old image',
    ]);
    Storage::disk('s3')->put($oldAsset->storage_key, 'old image', ['visibility' => 'private']);
    $block = $site->blocks()->create([
        'type' => 'image', 'position' => 0, 'content' => ['media_asset_id' => $oldAsset->id],
    ]);
    SiteBlock::updating(fn () => throw new RuntimeException('Simulated database write failure.'));

    $this->actingAs($site->user)->postJson(route('sites.blocks.image.store', [$site, $block]), [
        'image' => UploadedFile::fake()->image('new.png', 10, 10),
        'alt_text' => 'New image',
    ])->assertServerError();

    expect($block->fresh()->content['media_asset_id'])->toBe($oldAsset->id)
        ->and($site->mediaAssets()->count())->toBe(1);
    Storage::disk('s3')->assertExists($oldAsset->storage_key);
    expect(Storage::disk('s3')->allFiles("sites/{$site->id}"))->toBe([$oldAsset->storage_key]);
});

test('owners can read only their private same-site media and edit its alt text', function () {
    Storage::fake('s3');

    $site = Site::factory()->create();
    $asset = $site->mediaAssets()->create([
        'storage_key' => "sites/{$site->id}/church-photo",
        'mime_type' => 'image/png',
        'alt_text' => null,
    ]);
    Storage::disk('s3')->put($asset->storage_key, 'private image bytes', ['visibility' => 'private']);

    $this->get(route('sites.media.show', [$site, $asset]))
        ->assertRedirect(route('login'));

    $this->actingAs($site->user)
        ->get(route('sites.media.show', [$site, $asset]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertStreamedContent('private image bytes');

    $this->patch(route('sites.media.update', [$site, $asset]), ['alt_text' => 'Choir singing'])
        ->assertRedirect(route('sites.show', $site));
    expect($asset->fresh()->alt_text)->toBe('Choir singing');

    $this->patchJson(route('sites.media.update', [$site, $asset]), [
        'alt_text' => 'Injected update', 'site_id' => 99,
    ])->assertUnprocessable()->assertJsonValidationErrors('site_id');
    expect($asset->fresh()->alt_text)->toBe('Choir singing');

    $otherSite = Site::factory()->create();
    $this->get(route('sites.media.show', [$otherSite, $asset]))->assertNotFound();
    $this->patch(route('sites.media.update', [$otherSite, $asset]), ['alt_text' => 'Wrong owner'])
        ->assertNotFound();
    expect($asset->fresh()->alt_text)->toBe('Choir singing');
});

test('the editor receives same-site private image URLs for its block and logo previews', function () {
    $site = Site::factory()->create();
    $asset = $site->mediaAssets()->create([
        'storage_key' => "sites/{$site->id}/preview",
        'mime_type' => 'image/png',
        'alt_text' => 'Welcome sign',
    ]);
    $site->update(['logo_media_asset_id' => $asset->id]);
    $block = $site->blocks()->create([
        'type' => 'image', 'position' => 0, 'content' => ['media_asset_id' => $asset->id],
    ]);

    $this->actingAs($site->user)
        ->get(route('sites.show', $site))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sites/Show')
            ->where('site.logo.url', route('sites.media.show', [$site, $asset]))
            ->where('site.logo.alt_text', 'Welcome sign')
            ->where('blocks.0.id', $block->id)
            ->where('blocks.0.media_url', route('sites.media.show', [$site, $asset]))
            ->where('blocks.0.alt_text', 'Welcome sign'));
});

test('owners can upload, replace, and clear a site logo without deleting old assets', function () {
    Storage::fake('s3');

    $site = Site::factory()->create();
    $this->actingAs($site->user);

    $this->post(route('sites.logo.store', $site), [
        'image' => UploadedFile::fake()->image('logo.png', 20, 20),
        'alt_text' => 'Church logo',
    ])->assertRedirect(route('sites.show', $site));
    $firstAsset = $site->fresh()->logoMediaAsset;

    $this->post(route('sites.logo.store', $site), [
        'image' => UploadedFile::fake()->image('new-logo.jpg', 20, 20),
    ])->assertRedirect(route('sites.show', $site));
    $secondAsset = $site->fresh()->logoMediaAsset;

    expect($secondAsset)->not->toBeNull()
        ->and($secondAsset->id)->not->toBe($firstAsset->id)
        ->and($secondAsset->alt_text)->toBeNull()
        ->and($site->mediaAssets()->count())->toBe(2);

    $this->delete(route('sites.logo.destroy', $site))->assertRedirect(route('sites.show', $site));
    expect($site->fresh()->logo_media_asset_id)->toBeNull();
    Storage::disk('s3')->assertExists($firstAsset->storage_key);
    Storage::disk('s3')->assertExists($secondAsset->storage_key);
});

test('media writes and references remain isolated to the authenticated site owner', function () {
    Storage::fake('s3');

    $site = Site::factory()->create();
    $otherSite = Site::factory()->create();
    $block = $site->blocks()->create(['type' => 'image', 'position' => 0, 'content' => ['media_asset_id' => null]]);
    $otherAsset = $otherSite->mediaAssets()->create([
        'storage_key' => "sites/{$otherSite->id}/image",
        'mime_type' => 'image/png',
    ]);

    $this->postJson(route('sites.blocks.image.store', [$site, $block]), [
        'image' => UploadedFile::fake()->image('image.png', 10, 10),
    ])->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->postJson(route('sites.blocks.image.store', [$site, $block]), [
            'image' => UploadedFile::fake()->image('image.png', 10, 10),
        ])->assertNotFound();

    $this->actingAs($site->user)
        ->patchJson(route('sites.blocks.update', [$site, $block]), [
            'content' => ['media_asset_id' => $otherAsset->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('content.media_asset_id');

    expect($block->fresh()->content['media_asset_id'])->toBeNull()
        ->and($site->mediaAssets()->count())->toBe(0);
});
