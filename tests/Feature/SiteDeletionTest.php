<?php

use App\Actions\BuildSitePublicationSnapshot;
use App\Actions\OpenSiteBillingPortal;
use App\Actions\RequestSiteDeletion;
use App\Actions\ReserveCustomHostname;
use App\Actions\StartSiteCheckout;
use App\Http\Controllers\PublishedSiteController;
use App\Http\Controllers\SiteBlockController;
use App\Http\Controllers\SiteMediaController;
use App\Http\Controllers\SitePublishingController;
use App\Http\Requests\StoreSiteBlockRequest;
use App\Http\Requests\StoreSiteImageRequest;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

function siteWithDeletionContent(): Site
{
    $site = Site::factory()->create(['name' => 'Grace Church', 'slug' => 'grace-church']);
    $site->homePage()->firstOrFail()->blocks()->create([
        'type' => 'about', 'position' => 0, 'content' => ['heading' => 'Welcome', 'body' => 'Our church'],
    ]);
    $site->pages()->create(['name' => 'Visit', 'path' => 'visit', 'is_home' => false, 'position' => 1]);
    $asset = $site->mediaAssets()->create(['storage_key' => "sites/{$site->id}/photo.png", 'mime_type' => 'image/png']);
    Storage::fake('s3');
    Storage::disk('s3')->put($asset->storage_key, 'image bytes');
    $site->update(['logo_media_asset_id' => $asset->id]);
    $site->update(['published_snapshot' => app(BuildSitePublicationSnapshot::class)($site), 'published_at' => now()]);

    return $site;
}

test('site deletion removes content and revokes access while retaining cleanup identities', function () {
    $this->freezeTime();
    $site = siteWithDeletionContent();
    $otherSite = Site::factory()->for($site->user)->create();
    $domain = app(ReserveCustomHostname::class)->handle($site->user, $site->id, 'www.grace.example');
    $domain->forceFill(['cloudflare_id' => 'remote-id', 'state' => 'ready', 'verified_at' => now(), 'cname_matches' => true, 'check_id' => 'stale-check'])->save();
    $site->forceFill(['stripe_id' => 'cus_retained'])->save();
    $site->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_retained', 'stripe_status' => 'active', 'paid_until' => now()->addMonth()]);
    $asset = $site->mediaAssets()->firstOrFail();

    $deleted = app(RequestSiteDeletion::class)->handle($site->user, $site->id, $site->name);

    expect($deleted->deletion_requested_at->toDateTimeString() === now()->toDateTimeString())->toBeTrue()
        ->and($deleted->published_at)->toBeNull()->and($deleted->published_snapshot)->toBeNull()
        ->and($deleted->pages()->count())->toBe(0)->and($deleted->blocks()->count())->toBe(0)
        ->and($deleted->stripe_id)->toBe('cus_retained')->and($deleted->subscriptions()->count())->toBe(1)
        ->and($deleted->mediaAssets()->count())->toBe(1)->and($deleted->hasPaidDomainAccess())->toBeFalse()
        ->and($domain->fresh()->state)->toBe('removing')->and($domain->fresh()->cloudflare_id)->toBe('remote-id')
        ->and($domain->fresh()->check_id)->toBeNull()->and($domain->fresh()->verified_at)->toBeNull()
        ->and($otherSite->fresh()->deletion_requested_at)->toBeNull()->and($otherSite->pages()->count())->toBe(1)
        ->and($site->user->fresh()->deletion_requested_at)->toBeNull();
    Storage::disk('s3')->assertExists($asset->storage_key);

    $this->get(route('sites.published.show', $site->slug))->assertNotFound();
    $this->get(route('sites.published.pages.show', [$site->slug, 'visit']))->assertNotFound();
    $this->get(route('sites.published.media.show', [$site->slug, $asset]))->assertNotFound();
    $this->actingAs($site->user)->get(route('sites.media.show', [$site, $asset]))->assertNotFound();
    $this->get(route('sites.show', $otherSite))->assertOk();
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('sites', 2)->where('sites.1.id', $site->id)
        ->where('sites.1.deletion_requested_at', $deleted->deletion_requested_at->toISOString()));
});

test('deletion requires the current exact owned site name', function (string $name) {
    $site = siteWithDeletionContent();
    expect(fn () => app(RequestSiteDeletion::class)->handle($site->user, $site->id, $name))
        ->toThrow(ValidationException::class);
    expect($site->fresh()->deletion_requested_at)->toBeNull()
        ->and($site->pages()->count())->toBe(2)->and($site->fresh()->published_at)->not->toBeNull();
})->with(['', 'grace church', 'Another Church']);

test('deletion is owner scoped and retrying does not change its original timestamp', function () {
    $site = siteWithDeletionContent();
    expect(fn () => app(RequestSiteDeletion::class)->handle(User::factory()->create(), $site->id, $site->name))
        ->toThrow(ModelNotFoundException::class);
    $first = app(RequestSiteDeletion::class)->handle($site->user, $site->id, ' Grace Church ');
    $this->travel(5)->minutes();
    $again = app(RequestSiteDeletion::class)->handle($site->user, $site->id, $site->name);
    expect($again->deletion_requested_at->equalTo($first->deletion_requested_at))->toBeTrue();
});

test('pending deletion blocks all site routes before validation or side effects', function () {
    $site = siteWithDeletionContent();
    $page = $site->homePage()->firstOrFail();
    $block = $site->blocks()->firstOrFail();
    $asset = $site->mediaAssets()->firstOrFail();
    app(RequestSiteDeletion::class)->handle($site->user, $site->id, $site->name);
    $this->actingAs($site->user);
    $checked = 0;
    foreach (app('router')->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'sites/{site}') || $route->getName() === 'sites.destroy') {
            continue;
        }
        $uri = strtr($route->uri(), ['{site}' => $site->id, '{page}' => $page->id, '{block}' => $block->id, '{mediaAsset}' => $asset->id]);
        $this->call($route->methods()[0], '/'.$uri)->assertNotFound();
        $checked++;
    }
    expect($checked)->toBeGreaterThan(20);
    Storage::disk('s3')->assertExists($asset->storage_key);
    expect($site->fresh()->pages()->count())->toBe(0);
});

test('already validated block creation cannot cross the deletion lock boundary', function () {
    $site = Site::factory()->create();
    $request = Mockery::mock(StoreSiteBlockRequest::class);
    $request->shouldReceive('user')->andReturn($site->user);
    $request->shouldNotReceive('validated');
    app(RequestSiteDeletion::class)->handle($site->user, $site->id, $site->name);
    expect(fn () => app(SiteBlockController::class)->store($request, $site->id))->toThrow(ModelNotFoundException::class);
    expect($site->blocks()->count())->toBe(0);
});

test('an in flight publish cannot restore a deleting site', function () {
    $site = siteWithDeletionContent();
    $request = Request::create('/sites/'.$site->id.'/publish', 'POST');
    $request->setUserResolver(fn () => $site->user);
    $snapshot = Mockery::mock(BuildSitePublicationSnapshot::class);
    $snapshot->shouldNotReceive('__invoke');
    app(RequestSiteDeletion::class)->handle($site->user, $site->id, $site->name);
    expect(fn () => app(SitePublishingController::class)->publish($request, $site->id, $snapshot))->toThrow(ModelNotFoundException::class);
    expect($site->fresh()->published_at)->toBeNull();
});

test('internal billing and domain creation deny pending sites before provider calls', function (string $action) {
    $site = Site::factory()->create();
    app(RequestSiteDeletion::class)->handle($site->user, $site->id, $site->name);
    expect(fn () => match ($action) {
        'checkout' => app(StartSiteCheckout::class)->handle($site->user, $site->id, 'monthly'),
        'portal' => app(OpenSiteBillingPortal::class)->handle($site->user, $site->id),
        'domain' => app(ReserveCustomHostname::class)->handle($site->user, $site->id, 'www.grace.example'),
    })->toThrow(ModelNotFoundException::class);
})->with(['checkout', 'portal', 'domain']);

test('direct published render helpers reject deletion even with retained snapshot data', function () {
    $site = siteWithDeletionContent();
    $asset = $site->mediaAssets()->firstOrFail();
    $site->forceFill(['deletion_requested_at' => now()])->save();
    $controller = app(PublishedSiteController::class);
    foreach ([fn () => $controller->renderSite($site), fn () => $controller->siteMedia($site, $asset->id), fn () => $controller->validatePublication($site)] as $read) {
        try {
            $read();
            $this->fail('A deleting site must not render.');
        } catch (HttpException $error) {
            expect($error->getStatusCode())->toBe(404);
        }
    }
});

test('an already validated upload cannot write storage after deletion', function () {
    $site = Site::factory()->create();
    $request = Mockery::mock(StoreSiteImageRequest::class);
    $request->shouldReceive('user')->andReturn($site->user);
    $request->shouldReceive('validated')->with('image')->andReturn(UploadedFile::fake()->image('logo.png'));
    $request->shouldReceive('validated')->with('alt_text')->andReturn('Logo');
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldNotReceive('putFileAs');
    $disk->shouldNotReceive('delete');
    Storage::shouldReceive('disk')->with('s3')->andReturn($disk);
    app(RequestSiteDeletion::class)->handle($site->user, $site->id, $site->name);

    expect(fn () => app(SiteMediaController::class)->uploadLogo($request, $site->id))->toThrow(ModelNotFoundException::class);
    expect($site->mediaAssets()->count())->toBe(0);
});
