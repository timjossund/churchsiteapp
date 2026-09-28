<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;

test('development editor visits do not force a reload when built asset versions change', function () {
    Vite::partialMock()->shouldReceive('isRunningHot')->andReturn(true);
    $site = Site::factory()->create();
    $url = route('sites.pages.show', [$site, $site->homePage()->firstOrFail()]);
    $this->actingAs($site->user);

    foreach (['https://assets.example.test/first', 'https://assets.example.test/second'] as $assetUrl) {
        config(['app.asset_url' => $assetUrl]);
        expect(app(HandleInertiaRequests::class)->version(Request::create($url)))->toBeNull();
        $this->get($url, ['X-Inertia' => 'true'])
            ->assertOk()->assertHeaderMissing('X-Inertia-Location')
            ->assertJsonPath('component', 'Sites/Show');
    }
});

test('production keeps asset version changes and their reload response', function () {
    Vite::partialMock()->shouldReceive('isRunningHot')->andReturn(false);
    config(['app.asset_url' => 'https://assets.example.test/current']);
    $version = hash('xxh128', 'https://assets.example.test/current');
    $site = Site::factory()->create();
    $url = route('sites.pages.show', [$site, $site->homePage()->firstOrFail()]);
    $this->actingAs($site->user);

    expect(app(HandleInertiaRequests::class)->version(Request::create($url)))->toBe($version);
    $this->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])->assertOk();
    $this->get($url, ['X-Inertia' => 'true', 'X-Inertia-Version' => 'older-build'])
        ->assertStatus(409)->assertHeader('X-Inertia-Location', $url);
});
