<?php

use App\Models\Site;
use App\Models\SiteBlock;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->site = Site::factory()->create(['slug' => 'contact-map-test']);
    $this->content = ['heading' => 'Visit us', 'email' => '', 'phone' => '', 'address' => '123 Main St'];
    $this->block = SiteBlock::factory()->for($this->site)->create(['type' => 'contact', 'content' => $this->content]);
    $this->mapUrl = 'https://www.openstreetmap.org/?mlat=39.8&mlon=-89.65#map=16/39.8/-89.65';
    $this->actingAs($this->site->user);
});

test('an owner can enable, retain, clear, and reload a contact map', function () {
    foreach ([
        ['enabled' => true, 'url' => $this->mapUrl],
        ['enabled' => false, 'url' => $this->mapUrl],
        ['enabled' => false, 'url' => ''],
    ] as $map) {
        $this->patch(route('sites.blocks.update', [$this->site, $this->block]), [
            'content' => [...$this->content, 'map' => $map],
        ])->assertSessionHasNoErrors()->assertRedirect();

        expect($this->block->fresh()->content)->toBe([...$this->content, 'map' => $map]);
        $this->get(route('sites.pages.show', [$this->site, $this->site->homePage()->firstOrFail()]))
            ->assertInertia(fn (Assert $page) => $page->where('blocks.0.content.map', $map));
    }
});

test('contact maps do not require a street address and trim pasted links', function () {
    $content = ['heading' => 'Visit us', 'email' => '', 'phone' => '', 'map' => ['enabled' => true, 'url' => '  '.$this->mapUrl.'  ']];
    $this->patch(route('sites.blocks.update', [$this->site, $this->block]), ['content' => $content])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($this->block->fresh()->content['map'])->toBe(['enabled' => true, 'url' => $this->mapUrl]);
});

test('contact blocks without map settings stay valid', function () {
    $this->patch(route('sites.blocks.update', [$this->site, $this->block]), ['content' => $this->content])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($this->block->fresh()->content)->toBe($this->content);
});

test('invalid contact map settings leave saved content unchanged', function (mixed $map, string $field) {
    $this->patch(route('sites.blocks.update', [$this->site, $this->block]), [
        'content' => [...$this->content, 'map' => $map],
    ])->assertSessionHasErrors($field);
    expect($this->block->fresh()->content)->toBe($this->content);
})->with([
    'null' => [null, 'content.map'],
    'string' => ['https://www.openstreetmap.org/', 'content.map'],
    'empty object' => [[], 'content.map'],
    'missing enabled' => [['url' => ''], 'content.map.enabled'],
    'missing URL' => [['enabled' => false], 'content.map.url'],
    'string enabled' => [['enabled' => 'true', 'url' => ''], 'content.map.enabled'],
    'integer enabled' => [['enabled' => 0, 'url' => ''], 'content.map.enabled'],
    'array URL' => [['enabled' => false, 'url' => []], 'content.map.url'],
    'null URL' => [['enabled' => false, 'url' => null], 'content.map.url'],
    'enabled blank' => [['enabled' => true, 'url' => '  '], 'content.map.url'],
    'missing marker' => [['enabled' => true, 'url' => 'https://www.openstreetmap.org/#map=16/39.8/-89.65'], 'content.map.url'],
    'foreign host' => [['enabled' => true, 'url' => 'https://evil.example/?mlat=39.8&mlon=-89.65'], 'content.map.url'],
    'unsafe disabled URL' => [['enabled' => false, 'url' => 'javascript:alert(1)'], 'content.map.url'],
    'HTML' => [['enabled' => true, 'url' => '<iframe src="https://www.openstreetmap.org/"></iframe>'], 'content.map.url'],
    'unknown map field' => [['enabled' => false, 'url' => '', 'iframe' => 'untrusted'], 'content.map'],
]);

test('map settings are rejected on other block types', function () {
    $block = SiteBlock::factory()->for($this->site)->create(['type' => 'plain_text', 'content' => ['body' => 'Existing text']]);
    $this->patch(route('sites.blocks.update', [$this->site, $block]), [
        'content' => ['body' => 'Changed text', 'map' => ['enabled' => true, 'url' => $this->mapUrl]],
    ])->assertSessionHasErrors('content.map');
    expect($block->fresh()->content)->toBe(['body' => 'Existing text']);
});

test('another account cannot change a contact map', function () {
    $this->actingAs(User::factory()->create())->patch(route('sites.blocks.update', [$this->site, $this->block]), [
        'content' => [...$this->content, 'map' => ['enabled' => true, 'url' => $this->mapUrl]],
    ])->assertNotFound();
    expect($this->block->fresh()->content)->toBe($this->content);
});

test('guests cannot change a contact map', function () {
    auth()->logout();
    $this->patch(route('sites.blocks.update', [$this->site, $this->block]), [
        'content' => [...$this->content, 'map' => ['enabled' => true, 'url' => $this->mapUrl]],
    ])->assertRedirect(route('login'));
    expect($this->block->fresh()->content)->toBe($this->content);
});

test('contact map requests cannot cross site or page boundaries', function () {
    $otherSite = Site::factory()->for($this->site->user)->create();
    $otherPage = $this->site->pages()->create(['name' => 'About', 'path' => 'about', 'is_home' => false, 'position' => 1]);
    $content = [...$this->content, 'map' => ['enabled' => true, 'url' => $this->mapUrl]];
    $this->patch(route('sites.blocks.update', [$otherSite, $this->block]), ['content' => $content])->assertNotFound();
    $this->patch(route('sites.pages.blocks.update', [$this->site, $otherPage, $this->block]), ['content' => $content])->assertNotFound();
    expect($this->block->fresh()->content)->toBe($this->content);
});

test('published contact maps retain contact details and follow the Publish boundary', function () {
    $map = ['enabled' => true, 'url' => $this->mapUrl];
    $this->patch(route('sites.blocks.update', [$this->site, $this->block]), [
        'content' => [...$this->content, 'email' => 'hello@example.org', 'phone' => '555-1234', 'map' => $map],
    ])->assertRedirect();
    $this->post(route('sites.publish', $this->site))->assertRedirect();

    $public = route('sites.published.show', $this->site->slug);
    $this->get($public)->assertOk()
        ->assertSee('marker=39.8%2C-89.65', false)
        ->assertSee('grid items-start gap-6 md:grid-cols-2', false)
        ->assertSee('title="Location map: Visit us"', false)
        ->assertSee('loading="lazy"', false)
        ->assertSee('View on OpenStreetMap')->assertSee('OpenStreetMap contributors')
        ->assertSee('123 Main St')->assertSee('mailto:hello@example.org', false)->assertSee('tel:5551234', false)
        ->assertSee('Get directions');

    $this->patch(route('sites.blocks.update', [$this->site, $this->block]), [
        'content' => [...$this->content, 'map' => ['enabled' => true, 'url' => 'https://www.openstreetmap.org/?mlat=40&mlon=-90']],
    ])->assertRedirect();
    $this->get($public)->assertOk()->assertSee('marker=39.8%2C-89.65', false)->assertDontSee('marker=40%2C-90', false);
    $this->post(route('sites.publish', $this->site))->assertRedirect();
    $this->get($public)->assertOk()->assertSee('marker=40%2C-90', false)->assertDontSee('marker=39.8%2C-89.65', false);

    $this->patch(route('sites.blocks.update', [$this->site, $this->block]), [
        'content' => [...$this->content, 'map' => ['enabled' => false, 'url' => $this->mapUrl]],
    ])->assertRedirect();
    $this->get($public)->assertOk()->assertSee('export/embed.html', false);
    $this->post(route('sites.publish', $this->site))->assertRedirect();
    $this->get($public)->assertOk()->assertDontSee('export/embed.html', false)
        ->assertSee('123 Main St')->assertSee('Get directions');
});

test('malformed legacy maps do not take published contact details offline', function (mixed $map) {
    $this->block->update(['content' => [...$this->content, 'map' => $map]]);
    $this->post(route('sites.publish', $this->site))->assertRedirect();
    $this->get(route('sites.published.show', $this->site->slug))->assertOk()
        ->assertSee('123 Main St')->assertSee('Get directions')
        ->assertDontSee('export/embed.html', false)->assertDontSee('evil.example', false);
})->with([
    'missing' => [null],
    'scalar' => ['untrusted'],
    'array URL' => [['enabled' => true, 'url' => ['untrusted']]],
    'non-boolean enabled' => [['enabled' => 'true', 'url' => 'https://www.openstreetmap.org/?mlat=1&mlon=2']],
    'unsafe URL' => [['enabled' => true, 'url' => 'https://evil.example/?mlat=1&mlon=2']],
    'no pin' => [['enabled' => true, 'url' => 'https://www.openstreetmap.org/#map=19/35.084491/-92.518695']],
]);

test('a contact map can be published without an address and escapes its title', function () {
    $this->block->update(['content' => [
        'heading' => '<script>"Visit us"</script>', 'email' => '', 'phone' => '',
        'map' => ['enabled' => true, 'url' => $this->mapUrl],
    ]]);
    $this->post(route('sites.publish', $this->site))->assertRedirect();
    $this->get(route('sites.published.show', $this->site->slug))->assertOk()
        ->assertSee('export/embed.html', false)->assertDontSee('Get directions')
        ->assertSee('Location map: &lt;script&gt;&quot;Visit us&quot;&lt;/script&gt;', false)
        ->assertDontSee('<script>"Visit us"</script>', false);
});
