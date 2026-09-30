<?php

use App\Models\Site;
use App\Models\User;
use App\Support\EmbedFramePolicy;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->site = Site::factory()->create();
    $this->page = $this->site->homePage()->firstOrFail();
    $this->actingAs($this->site->user);
});

test('owner can create save reload and clear an embed block', function () {
    $this->post(route('sites.pages.blocks.store', [$this->site, $this->page]), ['type' => 'embed'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $block = $this->page->blocks()->firstOrFail();
    expect($block->content)->toBe(['heading' => '', 'url' => '']);
    foreach ([' https://calendar.google.com/calendar/embed?src=church%40example.org ', ''] as $url) {
        $this->patch(route('sites.pages.blocks.update', [$this->site, $this->page, $block]), [
            'content' => ['heading' => 'Events', 'url' => $url],
        ])->assertRedirect()->assertSessionHasNoErrors();
        expect($block->fresh()->content)->toBe(['heading' => 'Events', 'url' => trim($url)]);
        $this->get(route('sites.pages.show', [$this->site, $this->page]))
            ->assertInertia(fn (Assert $view) => $view->where('blocks.0.type', 'embed')->where('blocks.0.content.url', trim($url)));
    }
});

test('invalid iframe input leaves the saved embed unchanged', function (array $content, string $field) {
    $original = ['heading' => 'Events', 'url' => ''];
    $block = $this->page->blocks()->create(['type' => 'embed', 'position' => 0, 'content' => $original]);
    $this->patchJson(route('sites.pages.blocks.update', [$this->site, $this->page, $block]), ['content' => $content])
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect($block->fresh()->content)->toBe($original);
})->with([
    [['heading' => 'Events', 'url' => 'https://evil.test/'], 'content.url'],
    [['heading' => 'Events', 'url' => '<iframe src="https://calendar.google.com"></iframe>'], 'content.url'],
    [['heading' => 'Events', 'url' => 'https://calendar.google.com/calendar/embed?src=a&src=b'], 'content.url'],
    [['heading' => 'Events', 'url' => [], 'sandbox' => ''], 'content.url'],
    [['heading' => 'Events', 'url' => '', 'srcdoc' => '<script>bad</script>'], 'content'],
    [['heading' => 'Events', 'url' => '', 'allow' => 'camera'], 'content'],
    [['heading' => 'Events', 'url' => str_repeat('x', 8193)], 'content.url'],
]);

test('embed creation and updates remain scoped to owner site and page', function () {
    $block = $this->page->blocks()->create(['type' => 'embed', 'position' => 0, 'content' => ['heading' => '', 'url' => '']]);
    $otherSite = Site::factory()->for($this->site->user)->create();
    $otherPage = $this->site->pages()->create(['name' => 'Visit', 'position' => 1, 'is_home' => false]);
    $this->patch(route('sites.pages.blocks.update', [$this->site, $otherPage, $block]), ['content' => ['heading' => 'Changed', 'url' => '']])->assertNotFound();
    $this->patch(route('sites.pages.blocks.update', [$otherSite, $otherSite->homePage()->firstOrFail(), $block]), ['content' => ['heading' => 'Changed', 'url' => '']])->assertNotFound();
    $this->actingAs(User::factory()->create());
    $this->post(route('sites.pages.blocks.store', [$this->site, $this->page]), ['type' => 'embed'])->assertNotFound();
    $this->patch(route('sites.pages.blocks.update', [$this->site, $this->page, $block]), ['content' => ['heading' => 'Changed', 'url' => '']])->assertNotFound();
    expect($block->fresh()->content)->toBe(['heading' => '', 'url' => '']);
});

test('builder document and Inertia responses restrict frame navigation to approved providers', function () {
    $policy = EmbedFramePolicy::POLICY;
    foreach ([route('dashboard'), route('sites.show', $this->site), route('sites.pages.show', [$this->site, $this->page])] as $url) {
        $this->get($url)->assertOk()->assertHeader('Content-Security-Policy', $policy);
    }
    $this->get(route('sites.pages.show', [$this->site, $this->page]), ['X-Inertia' => 'true'])
        ->assertOk()->assertHeader('Content-Security-Policy', $policy);
    $this->get('/')->assertOk()->assertHeader('Content-Security-Policy', $policy);
});

test('frame policy retains an existing stricter policy', function () {
    $response = response('Existing response')->header('Content-Security-Policy', "default-src 'none'");
    EmbedFramePolicy::apply($response);
    expect($response->headers->all('Content-Security-Policy'))->toBe([
        "default-src 'none'", EmbedFramePolicy::POLICY,
    ]);
});

test('published calendars freeze configuration safely and disappear after removal', function () {
    $this->site->update(['slug' => 'public-calendar']);
    $block = $this->page->blocks()->create(['type' => 'embed', 'position' => 0, 'content' => ['heading' => 'Events <script>', 'url' => 'https://calendar.google.com/calendar/embed?src=first&mode=MONTH']]);
    $this->post(route('sites.publish', $this->site))->assertRedirect();
    $url = route('sites.published.show', 'public-calendar');
    $this->get($url)->assertOk()->assertHeader('Content-Security-Policy', EmbedFramePolicy::POLICY)
        ->assertSee('Events &lt;script&gt;', false)->assertSee('src=first&amp;mode=MONTH', false)
        ->assertSee('sandbox="allow-scripts allow-same-origin"', false)->assertSee('referrerpolicy="no-referrer"', false)
        ->assertSee('Open calendar')->assertDontSee('srcdoc=');
    $block->update(['content' => ['heading' => 'Draft', 'url' => 'https://calendar.google.com/calendar/embed?src=second']]);
    $this->get($url)->assertSee('src=first&amp;mode=MONTH', false)->assertDontSee('src=second');
    $this->post(route('sites.publish', $this->site))->assertRedirect();
    $this->get($url)->assertSee('src=second&amp;mode=AGENDA', false)->assertDontSee('src=first');
    $block->delete();
    $this->get($url)->assertSee('src=second&amp;mode=AGENDA', false);
    $this->post(route('sites.publish', $this->site))->assertRedirect();
    $this->get($url)->assertOk()->assertDontSee('<iframe', false);
});

test('malformed saved calendar URLs suppress the frame without breaking publication', function (mixed $url) {
    $this->site->update(['slug' => 'malformed-calendar']);
    $this->page->blocks()->create(['type' => 'embed', 'position' => 0, 'content' => ['heading' => 'Events', 'url' => $url]]);
    $this->post(route('sites.publish', $this->site))->assertRedirect();
    $this->get(route('sites.published.show', 'malformed-calendar'))->assertOk()->assertSee('Events')
        ->assertSee('Calendar is not available.')->assertDontSee('<iframe', false);
})->with([null, ['invalid'], '', 'https://evil.test/', 'https://calendar.google.com/calendar/embed?src=a&src=b']);
