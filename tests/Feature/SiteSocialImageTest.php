<?php

use App\Models\Site;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('an owner can upload and clear a private social preview image', function () {
    Storage::fake('s3');
    $site = Site::factory()->create();

    $this->actingAs($site->user)->post(route('sites.social-image.store', $site), [
        'image' => UploadedFile::fake()->image('social.png', 20, 20),
    ])->assertRedirect(route('sites.show', $site));

    $asset = $site->fresh()->socialImage;
    expect($asset)->not->toBeNull()
        ->and($asset->mime_type)->toBe('image/png');
    Storage::disk('s3')->assertExists($asset->storage_key);
    expect(Storage::disk('s3')->getVisibility($asset->storage_key))->toBe('private');

    $this->delete(route('sites.social-image.destroy', $site))
        ->assertRedirect(route('sites.show', $site));
    expect($site->fresh()->social_image_id)->toBeNull();
    Storage::disk('s3')->assertExists($asset->storage_key);
});

test('a user cannot upload or clear another site social preview image', function () {
    Storage::fake('s3');
    $site = Site::factory()->create();
    $otherSite = Site::factory()->create();
    $asset = $otherSite->mediaAssets()->create([
        'storage_key' => "sites/{$otherSite->id}/social",
        'mime_type' => 'image/png',
    ]);

    $this->actingAs($site->user)
        ->post(route('sites.social-image.store', $otherSite), [
            'image' => UploadedFile::fake()->image('social.png', 20, 20),
        ])->assertNotFound();

    $this->delete(route('sites.social-image.destroy', $otherSite))->assertNotFound();
    expect($otherSite->fresh()->social_image_id)->toBeNull()
        ->and($otherSite->mediaAssets()->count())->toBe(1)
        ->and($asset->exists)->toBeTrue();
});
