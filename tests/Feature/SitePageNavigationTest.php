<?php

use App\Models\Site;
use Inertia\Testing\AssertableInertia as Assert;

/** @return list<array{name: string, url: string, current: string}> */
function publishedPageNavigation(string $html): array
{
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML($html);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $links = [];
    foreach ((new DOMXPath($document))->query('//nav[@aria-label="Site pages"]//a') as $link) {
        $links[] = ['name' => trim($link->textContent), 'url' => $link->getAttribute('href'), 'current' => $link->getAttribute('aria-current')];
    }

    return $links;
}

test('published navigation follows frozen page order and identifies the current page without a section-link menu', function (string $theme) {
    $site = Site::factory()->create(['slug' => 'page-navigation', 'theme_key' => $theme]);
    $home = $site->homePage()->firstOrFail();
    $home->update(['position' => 2]);
    $about = $site->pages()->create(['name' => 'About', 'position' => 0]);
    $blank = $site->pages()->create(['name' => 'Blank', 'position' => 1]);
    $homeBlock = $home->blocks()->create(['type' => 'heading_text', 'position' => 0, 'content' => ['heading' => 'Home section', 'body' => '']]);
    $aboutBlock = $about->blocks()->create(['type' => 'heading_text', 'position' => 0, 'content' => ['heading' => 'Our story', 'body' => '']]);
    $hero = $about->blocks()->create(['type' => 'hero', 'position' => 1, 'content' => ['button_label' => 'Read story', 'link_type' => 'section', 'target_block_id' => $aboutBlock->id]]);
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    $homeUrl = route('sites.published.show', $site->slug);
    $aboutUrl = route('sites.published.pages.show', [$site->slug, 'about']);
    $blankUrl = route('sites.published.pages.show', [$site->slug, 'blank']);
    $html = $this->get($aboutUrl)->assertOk()->assertSee('data-theme="'.$theme.'"', false)
        ->assertDontSee('aria-label="Page sections"', false)->assertDontSee('On this page')->assertDontSee('>Pages</p>', false)->assertSee('href="#block-'.$aboutBlock->id.'"', false)
        ->assertDontSee('href="#block-'.$homeBlock->id.'"', false)->getContent();
    expect(publishedPageNavigation($html))->toBe([
        ['name' => 'About', 'url' => $aboutUrl, 'current' => 'page'],
        ['name' => 'Blank', 'url' => $blankUrl, 'current' => ''],
        ['name' => 'Home', 'url' => $homeUrl, 'current' => ''],
    ]);
    $blankHtml = $this->get($blankUrl)->assertOk()->assertDontSee('aria-label="Page sections"', false)->getContent();
    expect(publishedPageNavigation($blankHtml)[1]['current'])->toBe('page');
    expect(publishedPageNavigation($this->get($homeUrl)->assertOk()->getContent())[2]['current'])->toBe('page');

    $about->update(['name' => 'Renamed', 'path' => 'our-story', 'position' => 3]);
    $blank->delete();
    expect(publishedPageNavigation($this->get($aboutUrl)->assertOk()->getContent()))->toBe(publishedPageNavigation($html));
    $this->get(route('sites.pages.show', [$site, $about]))->assertInertia(fn (Assert $response) => $response
        ->where('navigation_pages', [['id' => $home->id, 'name' => 'Home'], ['id' => $about->id, 'name' => 'Renamed']]));
    // Corrupting a hero target to another page must never produce a cross-page section link.
    $hero->update(['content' => ['button_label' => 'Wrong target', 'link_type' => 'section', 'target_block_id' => $homeBlock->id]]);
    $this->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    $newUrl = route('sites.published.pages.show', [$site->slug, 'our-story']);
    $newHtml = $this->get($newUrl)->assertOk()->assertDontSee('href="#block-'.$homeBlock->id.'"', false)->getContent();
    expect(publishedPageNavigation($newHtml))->toBe([
        ['name' => 'Home', 'url' => $homeUrl, 'current' => ''],
        ['name' => 'Renamed', 'url' => $newUrl, 'current' => 'page'],
    ]);
})->with(['warm', 'clean', 'bold']);

test('Home-only sites expose one current root link and long page names are escaped', function () {
    $site = Site::factory()->create(['slug' => 'home-navigation']);
    $home = $site->homePage()->firstOrFail();
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    $url = route('sites.published.show', $site->slug);
    expect(publishedPageNavigation($this->get($url)->assertOk()->getContent()))->toBe([
        ['name' => 'Home', 'url' => $url, 'current' => 'page'],
    ]);
    $name = '<script>alert(1)</script>'.str_repeat('LongName', 25);
    $page = $site->pages()->create(['name' => $name, 'position' => 1]);
    $this->get(route('sites.pages.show', [$site, $home]))->assertInertia(fn (Assert $response) => $response
        ->where('navigation_pages.1.id', $page->id)->where('navigation_pages.1.name', $name));
    $this->post(route('sites.publish', $site))->assertSessionHasNoErrors();
    $html = $this->get($url)->assertOk()->assertDontSee('<script>alert(1)</script>', false)->getContent();
    expect(publishedPageNavigation($html)[1]['name'])->toBe($name);
});
