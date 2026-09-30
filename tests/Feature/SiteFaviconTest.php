<?php

use App\Actions\BuildSitePublicationSnapshot;
use App\Actions\FinalizeSiteDeletion;
use App\Actions\RequestSiteDeletion;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Site;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia as Assert;
use League\Flysystem\UnableToWriteFile;

beforeEach(function () {
    Storage::fake('s3');
    $this->site = Site::factory()->create();
    $this->actingAs($this->site->user);
});

test('sites default to no favicon and selected media deletion clears the reference', function () {
    expect($this->site->fresh()->favicon_media_asset_id)->toBeNull();
    $asset = $this->site->mediaAssets()->create(['storage_key' => "sites/{$this->site->id}/icon", 'mime_type' => 'image/png']);
    $this->site->update(['favicon_media_asset_id' => $asset->id]);
    expect($this->site->fresh()->faviconMediaAsset->id)->toBe($asset->id);
    $asset->delete();
    expect($this->site->fresh()->favicon_media_asset_id)->toBeNull();
});

test('owners upload replace and remove private favicons without deleting old media or changing a logo', function () {
    $site = $this->site;
    $logo = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/logo", 'mime_type' => 'image/jpeg']);
    $site->update(['logo_media_asset_id' => $logo->id]);
    $this->post(route('sites.favicon.store', $site), ['image' => UploadedFile::fake()->image('icon.png', 512, 512)->size(5120)])
        ->assertRedirect(route('sites.show', $site));
    $first = $site->fresh()->faviconMediaAsset;
    expect($first->mime_type)->toBe('image/png')->and($first->alt_text)->toBe('')
        ->and($first->storage_key)->toStartWith("sites/{$site->id}/")->not->toContain('icon.png');
    Storage::disk('s3')->assertExists($first->storage_key);
    expect(Storage::disk('s3')->getVisibility($first->storage_key))->toBe('private');
    $this->get(route('sites.media.show', [$site, $first]))->assertOk()->assertHeader('Content-Type', 'image/png');

    $this->post(route('sites.favicon.store', $site), ['image' => UploadedFile::fake()->image('second.png', 16, 16)])
        ->assertRedirect(route('sites.show', $site));
    $second = $site->fresh()->faviconMediaAsset;
    expect($second->id)->not->toBe($first->id)->and($site->fresh()->logo_media_asset_id)->toBe($logo->id);
    $this->delete(route('sites.favicon.destroy', $site))->assertRedirect(route('sites.show', $site));
    $this->delete(route('sites.favicon.destroy', $site))->assertRedirect(route('sites.show', $site));
    expect($site->fresh()->favicon_media_asset_id)->toBeNull()->and($site->mediaAssets()->count())->toBe(3);
    Storage::disk('s3')->assertExists($first->storage_key);
    Storage::disk('s3')->assertExists($second->storage_key);
});

test('invalid favicon files preserve the selected image', function (Closure $file) {
    $asset = $this->site->mediaAssets()->create(['storage_key' => "sites/{$this->site->id}/previous", 'mime_type' => 'image/png']);
    $this->site->update(['favicon_media_asset_id' => $asset->id]);
    $original = $file();
    // Use the real MIME detector: Laravel's fake file reports MIME from its filename.
    $upload = new UploadedFile($original->getPathname(), $original->getClientOriginalName(), 'image/png', null, true);
    $this->postJson(route('sites.favicon.store', $this->site), ['image' => $upload])
        ->assertUnprocessable()->assertJsonValidationErrors('image');
    expect($this->site->fresh()->favicon_media_asset_id)->toBe($asset->id)
        ->and($this->site->mediaAssets()->count())->toBe(1);
    expect(Storage::disk('s3')->allFiles())->toBe([]);
})->with([
    'non-square' => fn () => UploadedFile::fake()->image('icon.png', 32, 16),
    'JPEG' => fn () => UploadedFile::fake()->image('icon.jpg', 32, 32),
    'GIF' => fn () => UploadedFile::fake()->image('icon.gif', 32, 32),
    'SVG' => fn () => UploadedFile::fake()->createWithContent('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
    'malformed PNG' => fn () => UploadedFile::fake()->createWithContent('icon.png', 'not an image'),
    'oversized' => function () {
        $image = UploadedFile::fake()->image('icon.png', 32, 32);
        file_put_contents($image->getPathname(), str_repeat('x', 5121 * 1024), FILE_APPEND);

        return $image;
    },
    'misleading extension' => function () {
        $image = UploadedFile::fake()->image('real.png', 32, 32);

        return UploadedFile::fake()->createWithContent('icon.jpg', file_get_contents($image->getPathname()));
    },
    'disguised JPEG' => function () {
        $image = UploadedFile::fake()->image('real.jpg', 32, 32);

        return UploadedFile::fake()->createWithContent('icon.png', file_get_contents($image->getPathname()));
    },
]);

test('favicon upload rejects absent files and client assignment fields', function (array $extra) {
    $data = $extra === [] ? [] : ['image' => UploadedFile::fake()->image('icon.png', 32, 32), ...$extra];
    $this->postJson(route('sites.favicon.store', $this->site), $data)->assertUnprocessable()->assertJsonValidationErrors('image');
    expect($this->site->fresh()->favicon_media_asset_id)->toBeNull()->and($this->site->mediaAssets()->count())->toBe(0);
})->with([[[]], [['alt_text' => 'Ignored']], [['favicon_media_asset_id' => 1]], [['storage_key' => 'sites/other/icon']], [['url' => 'https://example.org/icon.png']]]);

test('favicon writes and private reads deny other owners and sites', function () {
    $other = Site::factory()->create();
    foreach ([$other, Site::factory()->for(User::factory())->create()] as $site) {
        $this->postJson(route('sites.favicon.store', $site), ['image' => UploadedFile::fake()->image('icon.png', 32, 32)])->assertNotFound();
        $this->deleteJson(route('sites.favicon.destroy', $site))->assertNotFound();
        expect($site->fresh()->favicon_media_asset_id)->toBeNull()->and($site->mediaAssets()->count())->toBe(0);
    }
    $this->post(route('sites.favicon.store', $this->site), ['image' => UploadedFile::fake()->image('icon.png', 32, 32)])->assertRedirect();
    $asset = $this->site->fresh()->faviconMediaAsset;
    $sameOwnerSite = Site::factory()->for($this->site->user)->create();
    $this->get(route('sites.media.show', [$sameOwnerSite, $asset]))->assertNotFound();
    $this->actingAs($other->user)->get(route('sites.media.show', [$this->site, $asset]))->assertNotFound();
});

test('guests cannot upload or remove favicons', function () {
    auth()->logout();
    $this->postJson(route('sites.favicon.store', $this->site), ['image' => UploadedFile::fake()->image('icon.png', 32, 32)])->assertUnauthorized();
    $this->deleteJson(route('sites.favicon.destroy', $this->site))->assertUnauthorized();
    expect($this->site->mediaAssets()->count())->toBe(0);
});

test('failed storage writes preserve the previous favicon and clean only the new object', function (bool $throws) {
    $asset = $this->site->mediaAssets()->create(['storage_key' => "sites/{$this->site->id}/previous", 'mime_type' => 'image/png']);
    $this->site->update(['favicon_media_asset_id' => $asset->id]);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $write = $disk->shouldReceive('putFileAs')->once();
    $throws ? $write->andThrow(UnableToWriteFile::atLocation('new favicon')) : $write->andReturn(false);
    $disk->shouldReceive('delete')->once()->withArgs(fn (string $key): bool => str_starts_with($key, "sites/{$this->site->id}/") && $key !== $asset->storage_key)->andReturn(true);
    Storage::shouldReceive('disk')->with('s3')->andReturn($disk);
    $this->postJson(route('sites.favicon.store', $this->site), ['image' => UploadedFile::fake()->image('icon.png', 32, 32)])
        ->assertUnprocessable()->assertJsonValidationErrors('image');
    expect($this->site->fresh()->favicon_media_asset_id)->toBe($asset->id)->and($this->site->mediaAssets()->count())->toBe(1);
})->with([false, true]);

test('unexpected assignment failures roll back media and clean the newly stored object', function () {
    Event::listen('eloquent.updating: '.Site::class, function (Site $site): void {
        if ($site->isDirty('favicon_media_asset_id')) {
            throw new RuntimeException('Unexpected assignment failure');
        }
    });
    $this->withoutExceptionHandling();
    expect(fn () => $this->post(route('sites.favicon.store', $this->site), ['image' => UploadedFile::fake()->image('icon.png', 32, 32)]))
        ->toThrow(RuntimeException::class, 'Unexpected assignment failure');
    expect($this->site->fresh()->favicon_media_asset_id)->toBeNull()->and($this->site->mediaAssets()->count())->toBe(0);
    expect(Storage::disk('s3')->allFiles())->toBe([]);
});

test('uploading and removing favicons do not modify an existing publication', function () {
    $snapshot = app(BuildSitePublicationSnapshot::class)($this->site);
    $this->site->update(['published_snapshot' => $snapshot, 'published_at' => now()]);
    $this->post(route('sites.favicon.store', $this->site), ['image' => UploadedFile::fake()->image('icon.png', 32, 32)])->assertRedirect();
    expect($this->site->fresh()->published_snapshot)->toBe($snapshot);
    $this->delete(route('sites.favicon.destroy', $this->site))->assertRedirect();
    expect($this->site->fresh()->published_snapshot)->toBe($snapshot);
});

test('site deletion clears favicon selection retains cleanup inventory and denies later favicon writes', function () {
    $this->post(route('sites.favicon.store', $this->site), ['image' => UploadedFile::fake()->image('icon.png', 32, 32)])->assertRedirect();
    $asset = $this->site->fresh()->faviconMediaAsset;
    $deleted = app(RequestSiteDeletion::class)->handle($this->site->user, $this->site->id, $this->site->name);
    expect($deleted->favicon_media_asset_id)->toBeNull()->and($deleted->mediaAssets()->count())->toBe(1);
    Storage::disk('s3')->assertExists($asset->storage_key);
    $this->postJson(route('sites.favicon.store', $this->site), ['image' => UploadedFile::fake()->image('icon.png', 32, 32)])->assertNotFound();
    $this->deleteJson(route('sites.favicon.destroy', $this->site))->assertNotFound();
});

test('settings show the owned favicon after upload replacement reload and removal', function () {
    $this->get(route('sites.show', $this->site))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Sites/Settings')->where('site.favicon', null));
    foreach (['first.png', 'second.png'] as $filename) {
        $this->post(route('sites.favicon.store', $this->site), ['image' => UploadedFile::fake()->image($filename, 32, 32)])
            ->assertRedirect(route('sites.show', $this->site));
        $asset = $this->site->fresh()->faviconMediaAsset;
        $this->get(route('sites.show', $this->site))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Sites/Settings')->where('site.favicon.media_asset_id', $asset->id)
            ->where('site.favicon.url', route('sites.media.show', [$this->site, $asset])));
    }
    $this->delete(route('sites.favicon.destroy', $this->site))->assertRedirect();
    $this->get(route('sites.show', $this->site))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('site.favicon', null));
});

test('settings never expose another sites favicon even if a persisted reference is invalid', function () {
    $other = Site::factory()->create();
    $asset = $other->mediaAssets()->create(['storage_key' => "sites/{$other->id}/icon", 'mime_type' => 'image/png']);
    $this->site->update(['favicon_media_asset_id' => $asset->id]);
    $this->get(route('sites.show', $this->site))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('site.favicon', null));
});

test('favicon partial reload refreshes site data without refreshing page manager drafts', function (bool $runningHot) {
    Vite::partialMock()->shouldReceive('isRunningHot')->andReturn($runningHot);
    $this->post(route('sites.favicon.store', $this->site), ['image' => UploadedFile::fake()->image('icon.png', 32, 32)])->assertRedirect();
    $asset = $this->site->fresh()->faviconMediaAsset;
    $this->get(route('sites.show', $this->site), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create(route('sites.show', $this->site))) ?? '',
        'X-Inertia-Partial-Component' => 'Sites/Settings',
        'X-Inertia-Partial-Data' => 'site',
    ])->assertOk()->assertJsonPath('props.site.favicon.media_asset_id', $asset->id)
        ->assertJsonMissingPath('props.pages');
})->with(['development assets' => true, 'built assets' => false]);

test('whole site cleanup removes favicon objects and records while preserving other sites', function () {
    $this->post(route('sites.favicon.store', $this->site), ['image' => UploadedFile::fake()->image('icon.png', 32, 32)])->assertRedirect();
    $asset = $this->site->fresh()->faviconMediaAsset;
    $other = Site::factory()->for($this->site->user)->create();
    $otherAsset = $other->mediaAssets()->create(['storage_key' => "sites/{$other->id}/favicon", 'mime_type' => 'image/png']);
    Storage::disk('s3')->put($otherAsset->storage_key, 'other site icon');
    app(RequestSiteDeletion::class)->handle($this->site->user, $this->site->id, $this->site->name);
    expect(app(FinalizeSiteDeletion::class)->handle($this->site->id))->toBeTrue();
    Storage::disk('s3')->assertMissing($asset->storage_key);
    Storage::disk('s3')->assertExists($otherAsset->storage_key);
    expect(Site::find($this->site->id))->toBeNull()->and($other->fresh()->mediaAssets()->count())->toBe(1);
});
