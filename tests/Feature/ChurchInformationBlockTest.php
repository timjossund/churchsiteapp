<?php

use App\Models\Site;
use App\Models\SiteBlock;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an owner can save and reload a service-time grid without changing its entries', function () {
    $site = Site::factory()->create();
    $entries = [
        ['day' => 'sunday', 'time' => '10:30', 'label' => 'Worship'],
        ['day' => 'wednesday', 'time' => '18:00', 'label' => 'Prayer'],
    ];
    $block = SiteBlock::factory()->for($site)->create([
        'type' => 'service_times',
        'content' => ['heading' => 'Join us', 'entries' => $entries],
    ]);

    $this->actingAs($site->user)
        ->patch(route('sites.blocks.update', [$site, $block]), [
            'content' => [
                'heading' => 'Join us',
                'entries' => $entries,
                'style' => ['layout' => 'grid'],
            ],
        ])->assertRedirect(route('sites.show', $site));

    expect($block->fresh()->content)->toBe([
        'heading' => 'Join us',
        'entries' => $entries,
        'style' => ['layout' => 'grid'],
    ]);

    $this->get(route('sites.pages.show', [$site, $site->homePage()->firstOrFail()]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('blocks.0.content.style.layout', 'grid')
            ->where('blocks.0.content.entries.1.label', 'Prayer'));

    $this->actingAs(User::factory()->create())
        ->patch(route('sites.blocks.update', [$site, $block]), [
            'content' => ['heading' => 'Changed', 'entries' => [], 'style' => ['layout' => 'list']],
        ])->assertNotFound();
    expect($block->fresh()->content['style']['layout'])->toBe('grid');
});

test('service-time layout values are restricted to their block type', function (string $type, mixed $layout) {
    $site = Site::factory()->create();
    $content = match ($type) {
        'service_times' => ['heading' => 'Gather', 'entries' => []],
        'text_image' => ['heading' => 'Welcome', 'body' => '', 'media_asset_id' => null],
        default => ['heading' => 'Contact', 'email' => '', 'phone' => ''],
    };
    $block = SiteBlock::factory()->for($site)->create(['type' => $type, 'content' => $content]);

    $this->actingAs($site->user)
        ->patch(route('sites.blocks.update', [$site, $block]), [
            'content' => [...$content, 'style' => ['layout' => $layout]],
        ])->assertSessionHasErrors('content.style.layout');

    expect($block->fresh()->content)->toBe($content);
})->with([
    ['service_times', 'image_left'],
    ['service_times', 'stacked'],
    ['service_times', ['grid']],
    ['text_image', 'grid'],
    ['contact', 'grid'],
]);

test('the published service-time layout changes only after publish and preserves entry order', function () {
    $site = Site::factory()->create(['slug' => 'service-layouts']);
    $entries = [
        ['day' => 'sunday', 'time' => '10:30', 'label' => 'Worship'],
        ['day' => 'wednesday', 'time' => '18:00', 'label' => 'Prayer'],
    ];
    $block = $site->blocks()->create([
        'type' => 'service_times',
        'position' => 0,
        'content' => ['heading' => 'Join us', 'entries' => $entries],
    ]);
    $site->blocks()->create([
        'type' => 'service_times',
        'position' => 1,
        'content' => [
            'heading' => 'Earlier content',
            'entries' => [['day' => 'friday', 'time' => '19:00', 'label' => 'Legacy']],
            'style' => ['layout' => 'unknown-old-value'],
        ],
    ]);

    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertRedirect();
    auth()->logout();
    $this->get(route('sites.published.show', $site->slug))
        ->assertOk()
        ->assertSee('data-service-layout="list"', false);

    $this->actingAs($site->user)->patch(route('sites.blocks.update', [$site, $block]), [
        'content' => ['heading' => 'Join us', 'entries' => $entries, 'style' => ['layout' => 'grid']],
    ])->assertRedirect();
    auth()->logout();
    $this->get(route('sites.published.show', $site->slug))
        ->assertOk()
        ->assertSee('data-service-layout="list"', false)
        ->assertDontSee('data-service-layout="grid"', false);

    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertRedirect();
    auth()->logout();
    $html = $this->get(route('sites.published.show', $site->slug))
        ->assertOk()
        ->assertSee('data-service-layout="grid"', false)
        ->assertSee('data-service-layout="list"', false)
        ->assertSee('sm:grid-cols-2', false)
        ->assertSee('10:30 AM')
        ->assertSee('6:00 PM')
        ->getContent();
    expect(strpos($html, 'Worship'))->toBeLessThan(strpos($html, 'Prayer'));
});

test('an owner can save and reload one multiline contact address', function () {
    $site = Site::factory()->create();
    $block = SiteBlock::factory()->for($site)->create([
        'type' => 'contact',
        'content' => ['heading' => 'Visit us', 'email' => '', 'phone' => ''],
    ]);
    $address = "123 Main St\nSpringfield, IL 62701";

    $this->actingAs($site->user)->patch(route('sites.blocks.update', [$site, $block]), [
        'content' => ['heading' => 'Visit us', 'email' => '', 'phone' => '', 'address' => $address],
    ])->assertRedirect(route('sites.show', $site));

    expect($block->fresh()->content['address'])->toBe($address);
    $this->get(route('sites.pages.show', [$site, $site->homePage()->firstOrFail()]))
        ->assertInertia(fn (Assert $page) => $page->where('blocks.0.content.address', $address));
});

test('contact address input must be plain text and only belongs on Contact blocks', function () {
    $site = Site::factory()->create();
    $contact = SiteBlock::factory()->for($site)->create([
        'type' => 'contact',
        'content' => ['heading' => 'Visit us', 'email' => '', 'phone' => ''],
    ]);
    $about = SiteBlock::factory()->for($site)->create([
        'type' => 'about',
        'content' => ['heading' => 'About', 'body' => 'Welcome'],
    ]);

    $this->actingAs($site->user)->patch(route('sites.blocks.update', [$site, $contact]), [
        'content' => ['heading' => 'Visit us', 'email' => '', 'phone' => '', 'address' => ['unsafe']],
    ])->assertSessionHasErrors('content.address');
    $this->patch(route('sites.blocks.update', [$site, $contact]), [
        'content' => ['heading' => 'Visit us', 'email' => '', 'phone' => '', 'directions_url' => 'javascript:alert(1)'],
    ])->assertSessionHasErrors('content');
    $this->patch(route('sites.blocks.update', [$site, $about]), [
        'content' => ['heading' => 'About', 'body' => 'Welcome', 'address' => '123 Main St'],
    ])->assertSessionHasErrors('content.address');

    expect($contact->fresh()->content)->toBe(['heading' => 'Visit us', 'email' => '', 'phone' => '']);
    expect($about->fresh()->content)->toBe(['heading' => 'About', 'body' => 'Welcome']);
});

test('published contact addresses are escaped and directions follow the last publication', function () {
    $site = Site::factory()->create(['slug' => 'contact-address']);
    $address = "  123 Main & <script>\nSpringfield, IL 62701  ";
    $contact = $site->blocks()->create([
        'type' => 'contact',
        'position' => 0,
        'content' => [
            'heading' => 'Visit us',
            'email' => 'hello@example.test',
            'phone' => '+1 (555) 123-4567',
            'address' => $address,
        ],
    ]);

    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertRedirect();
    auth()->logout();
    $published = $this->get(route('sites.published.show', $site->slug))->assertOk();
    $published
        ->assertSee('123 Main &amp; &lt;script&gt;', false)
        ->assertSee('href="https://www.google.com/maps/dir/?api=1&amp;destination='.urlencode(trim($address)).'"', false)
        ->assertSee('target="_blank" rel="noopener noreferrer"', false)
        ->assertSee('mailto:hello@example.test', false)
        ->assertSee('tel:+15551234567', false)
        ->assertDontSee('123 Main & <script>', false);

    $this->actingAs($site->user)->patch(route('sites.blocks.update', [$site, $contact]), [
        'content' => [
            'heading' => 'Visit us', 'email' => 'hello@example.test',
            'phone' => '+1 (555) 123-4567', 'address' => '456 New Road',
        ],
    ])->assertRedirect();
    auth()->logout();
    $this->get(route('sites.published.show', $site->slug))
        ->assertOk()
        ->assertSee('123 Main &amp; &lt;script&gt;', false)
        ->assertDontSee('456 New Road');

    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertRedirect();
    auth()->logout();
    $this->get(route('sites.published.show', $site->slug))
        ->assertOk()
        ->assertSee('456 New Road')
        ->assertDontSee('123 Main &amp; &lt;script&gt;', false);

    $this->actingAs($site->user)->patch(route('sites.blocks.update', [$site, $contact]), [
        'content' => [
            'heading' => 'Visit us', 'email' => 'hello@example.test',
            'phone' => '+1 (555) 123-4567', 'address' => " \n ",
        ],
    ])->assertRedirect();
    $this->post(route('sites.publish', $site))->assertRedirect();
    auth()->logout();
    $this->get(route('sites.published.show', $site->slug))
        ->assertOk()
        ->assertDontSee('Get directions')
        ->assertSee('mailto:hello@example.test', false)
        ->assertSee('tel:+15551234567', false);
});

test('malformed legacy contact addresses do not render a directions link', function () {
    $site = Site::factory()->create(['slug' => 'old-contact-address']);
    $site->blocks()->create([
        'type' => 'contact',
        'position' => 0,
        'content' => ['heading' => 'Contact', 'email' => '', 'phone' => '', 'address' => ['unsafe']],
    ]);

    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertRedirect();
    auth()->logout();
    $this->get(route('sites.published.show', $site->slug))
        ->assertOk()
        ->assertDontSee('Get directions');
});
