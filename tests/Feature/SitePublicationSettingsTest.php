<?php

use App\Models\Site;
use Inertia\Testing\AssertableInertia as Assert;

test('sites start with unpublished address and metadata settings', function () {
    $site = Site::factory()->create();

    expect($site->slug)->toBeNull()
        ->and($site->homePage()->firstOrFail()->seo_title)->toBeNull()
        ->and($site->homePage()->firstOrFail()->seo_description)->toBeNull()
        ->and($site->published_snapshot)->toBeNull()
        ->and($site->published_at)->toBeNull();

    $this->actingAs($site->user)->get(route('sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('site.slug', null)
            ->missing('site.seo_title')
            ->missing('site.seo_description')
            ->where('site.published_at', null)
            ->missing('site.social_image'));
});

test('an owner can save a normalized address and trimmed optional metadata', function () {
    $site = Site::factory()->create();

    $this->actingAs($site->user)->patch(route('sites.update', $site), [
        'slug' => '  Grace-Church  ',
        'seo_title' => '  Grace Church | Home  ',
        'seo_description' => '  Join us Sunday.  ',
    ])->assertRedirect(route('sites.show', $site))->assertSessionHasNoErrors();

    $saved = $site->fresh();
    expect($saved->slug)->toBe('grace-church')
        ->and($saved->homePage()->firstOrFail()->seo_title)->toBe('Grace Church | Home')
        ->and($saved->homePage()->firstOrFail()->seo_description)->toBe('Join us Sunday.');
});

test('address and metadata validation preserve saved settings', function (array $input, string $error) {
    $site = Site::factory()->create([
        'slug' => 'grace-church',
    ]);
    $site->homePage()->firstOrFail()->update([
        'seo_title' => 'Saved title',
        'seo_description' => 'Saved description',
    ]);
    if (isset($input['duplicate_site'])) {
        Site::factory()->create(['slug' => 'other-church']);
        unset($input['duplicate_site']);
    }

    $this->actingAs($site->user)->patch(route('sites.update', $site), $input)
        ->assertSessionHasErrors($error);

    expect($site->fresh()->slug)->toBe('grace-church')
        ->and($site->homePage()->firstOrFail()->seo_title)->toBe('Saved title')
        ->and($site->homePage()->firstOrFail()->seo_description)->toBe('Saved description');
})->with([
    'unsupported address characters' => [['slug' => 'grace_church'], 'slug'],
    'duplicate address' => [['slug' => 'other-church', 'duplicate_site' => true], 'slug'],
    'title too long' => [['seo_title' => str_repeat('x', 256)], 'seo_title'],
    'description too long' => [['seo_description' => str_repeat('x', 2001)], 'seo_description'],
]);

test('published sites can retain their address while optional metadata is cleared', function () {
    $site = Site::factory()->create([
        'slug' => 'grace-church',
        'published_at' => now(),
        'published_snapshot' => ['version' => 1],
    ]);
    $site->homePage()->firstOrFail()->update([
        'seo_title' => 'Saved title',
        'seo_description' => 'Saved description',
    ]);

    $this->actingAs($site->user)->patch(route('sites.update', $site), [
        'slug' => 'grace-church',
        'seo_title' => '',
        'seo_description' => '',
    ])->assertRedirect(route('sites.show', $site))->assertSessionHasNoErrors();

    $saved = $site->fresh();
    expect($saved->slug)->toBe('grace-church')
        ->and($saved->homePage()->firstOrFail()->seo_title)->toBeNull()
        ->and($saved->homePage()->firstOrFail()->seo_description)->toBeNull();
});

test('a published site cannot change or clear its address', function (string $slug) {
    $site = Site::factory()->create([
        'slug' => 'grace-church',
        'published_at' => now(),
        'published_snapshot' => ['version' => 1],
    ]);

    $this->actingAs($site->user)->patch(route('sites.update', $site), ['slug' => $slug])
        ->assertSessionHasErrors('slug');

    expect($site->fresh()->slug)->toBe('grace-church');
})->with(['new address' => 'new-address', 'cleared address' => '']);

test('another owner cannot read or change publication settings', function () {
    $site = Site::factory()->create([
        'slug' => 'grace-church',
    ]);
    $site->homePage()->firstOrFail()->update([
        'seo_title' => 'Private draft title',
        'seo_description' => 'Private draft description',
    ]);
    $otherUser = Site::factory()->create()->user;

    $this->actingAs($otherUser)
        ->get(route('sites.show', $site))
        ->assertNotFound();

    $this->patch(route('sites.update', $site), [
        'slug' => 'changed-address',
        'seo_title' => 'Changed title',
        'seo_description' => 'Changed description',
    ])->assertNotFound();

    $saved = $site->fresh();
    expect($saved->slug)->toBe('grace-church')
        ->and($saved->homePage()->firstOrFail()->seo_title)->toBe('Private draft title')
        ->and($saved->homePage()->firstOrFail()->seo_description)->toBe('Private draft description');
});
