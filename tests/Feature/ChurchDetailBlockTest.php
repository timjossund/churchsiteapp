<?php

use App\Models\Site;
use App\Models\SiteBlock;
use Inertia\Testing\AssertableInertia as Assert;

test('church detail blocks start with structured empty content', function () {
    $site = Site::factory()->create();
    $this->actingAs($site->user);

    foreach (['hero', 'service_times', 'contact'] as $type) {
        $this->post(route('sites.blocks.store', $site), ['type' => $type])
            ->assertRedirect(route('sites.show', $site));
    }

    $blocks = $site->blocks()->orderBy('position')->get();
    expect($blocks->pluck('type')->all())->toBe(['hero', 'service_times', 'contact']);
    expect($blocks[0]->content)->toBe([
        'heading' => '', 'body' => '', 'button_label' => '',
        'link_type' => 'none', 'target_block_id' => null, 'external_url' => '',
    ]);
    expect($blocks[1]->content)->toBe(['heading' => '', 'entries' => []]);
    expect($blocks[2]->content)->toBe(['heading' => '', 'email' => '', 'phone' => '']);
});

test('store rejects injected fields', function () {
    $site = Site::factory()->create();

    $this->actingAs($site->user)
        ->post(route('sites.blocks.store', $site), [
            'type' => 'hero', 'site_id' => $site->id, 'position' => 4, 'content' => ['heading' => 'Injected'],
        ])->assertSessionHasErrors(['site_id', 'position', 'content']);

    expect($site->blocks()->count())->toBe(0);

    $this->post(route('sites.blocks.store', $site), [
        'type' => 'hero', 'unexpected' => 'value',
    ])->assertSessionHasErrors('unexpected');

    expect($site->blocks()->count())->toBe(0);
});

test('hero links can point to an owned section or safe external URL', function () {
    $site = Site::factory()->create();
    $target = SiteBlock::factory()->for($site)->create();
    $hero = $site->blocks()->create([
        'type' => 'hero', 'position' => 1,
        'content' => ['heading' => '', 'body' => '', 'button_label' => '', 'link_type' => 'none', 'target_block_id' => null, 'external_url' => ''],
    ]);
    $this->actingAs($site->user);

    $section = [
        'heading' => 'Welcome', 'body' => 'Join us', 'button_label' => 'See more',
        'link_type' => 'section', 'target_block_id' => $target->id, 'external_url' => '',
    ];
    $this->patch(route('sites.blocks.update', [$site, $hero]), ['content' => $section])
        ->assertRedirect(route('sites.show', $site));
    expect($hero->fresh()->content)->toBe($section);

    $this->patch(route('sites.blocks.update', [$site, $hero]), [
        'content' => $section, 'unexpected' => 'value',
    ])->assertSessionHasErrors('unexpected');

    $external = [
        ...$section, 'button_label' => 'Visit', 'link_type' => 'external',
        'target_block_id' => null, 'external_url' => 'https://example.org/visit',
    ];
    $this->patch(route('sites.blocks.update', [$site, $hero]), ['content' => $external])
        ->assertRedirect(route('sites.show', $site));
    expect($hero->fresh()->content)->toBe($external);
});

test('hero rejects unsafe or foreign targets and keeps saved content', function ($changes, $error) {
    $site = Site::factory()->create();
    $hero = $site->blocks()->create([
        'type' => 'hero', 'position' => 0,
        'content' => ['heading' => '', 'body' => '', 'button_label' => '', 'link_type' => 'none', 'target_block_id' => null, 'external_url' => ''],
    ]);
    $foreign = SiteBlock::factory()->create();
    $content = [
        'heading' => '', 'body' => '', 'button_label' => 'Go',
        'link_type' => 'section', 'target_block_id' => $foreign->id, 'external_url' => '',
    ];
    foreach ($changes as $key => $value) {
        $content[$key] = $value === 'SELF' ? $hero->id : $value;
    }

    $this->actingAs($site->user)
        ->patch(route('sites.blocks.update', [$site, $hero]), ['content' => $content])
        ->assertSessionHasErrors($error);
    expect($hero->fresh()->content['link_type'])->toBe('none');
})->with([
    'foreign section' => [[], 'content.target_block_id'],
    'self section' => [['target_block_id' => 'SELF'], 'content.target_block_id'],
    'unsafe URL' => [['link_type' => 'external', 'target_block_id' => null, 'external_url' => 'javascript:alert(1)'], 'content.external_url'],
    'external link with section target' => [['link_type' => 'external', 'external_url' => 'https://example.org'], 'content.target_block_id'],
    'hidden button with a label' => [['link_type' => 'none', 'target_block_id' => null], 'content.button_label'],
    'missing label' => [['button_label' => ''], 'content.button_label'],
    'extra field' => [['script' => '<script>'], 'content'],
]);

test('deleting a section target clears its hero button without changing the text', function (bool $stringTarget) {
    $site = Site::factory()->create();
    $target = SiteBlock::factory()->for($site)->create();
    $hero = $site->blocks()->create([
        'type' => 'hero', 'position' => 1,
        'content' => [
            'heading' => 'Welcome', 'body' => 'Join us', 'button_label' => 'Visit',
            'link_type' => 'section', 'target_block_id' => $stringTarget ? (string) $target->id : $target->id, 'external_url' => '',
        ],
    ]);

    $this->actingAs($site->user)
        ->delete(route('sites.blocks.destroy', [$site, $target]))
        ->assertRedirect(route('sites.show', $site));

    expect($hero->fresh()->content)->toBe([
        'heading' => 'Welcome', 'body' => 'Join us', 'button_label' => '',
        'link_type' => 'none', 'target_block_id' => null, 'external_url' => '',
    ]);
})->with(['integer target' => false, 'legacy string target' => true]);

test('service times save ordered weekly entries and reject malformed rows', function () {
    $site = Site::factory()->create();
    $block = $site->blocks()->create(['type' => 'service_times', 'position' => 0, 'content' => ['heading' => '', 'entries' => []]]);
    $this->actingAs($site->user);
    $content = ['heading' => 'Join us', 'entries' => [
        ['day' => 'sunday', 'time' => '09:30', 'label' => 'First service'],
        ['day' => 'wednesday', 'time' => '18:00', 'label' => ''],
    ]];

    $this->patch(route('sites.blocks.update', [$site, $block]), ['content' => $content])->assertRedirect();
    expect($block->fresh()->content)->toBe($content);

    foreach ([
        [['day' => '', 'time' => '', 'label' => ''], ['content.entries.0.day', 'content.entries.0.time']],
        [['day' => '', 'time' => '09:30', 'label' => ''], 'content.entries.0.day'],
        [['day' => 'sunday', 'time' => '', 'label' => ''], 'content.entries.0.time'],
        [['day' => 'funday', 'time' => '09:30', 'label' => ''], 'content.entries.0.day'],
        [['day' => 'sunday', 'time' => '25:00', 'label' => ''], 'content.entries.0.time'],
        [['day' => 'sunday', 'time' => '09:30', 'label' => '', 'extra' => 'x'], 'content.entries.0'],
    ] as [$row, $error]) {
        $this->patch(route('sites.blocks.update', [$site, $block]), [
            'content' => ['heading' => 'Bad', 'entries' => [$row]],
        ])->assertSessionHasErrors($error);
        expect($block->fresh()->content)->toBe($content);
    }
});

test('contact accepts safe details and rejects malformed values', function () {
    $site = Site::factory()->create();
    $block = $site->blocks()->create(['type' => 'contact', 'position' => 0, 'content' => ['heading' => '', 'email' => '', 'phone' => '']]);
    $this->actingAs($site->user);
    $content = ['heading' => 'Contact us', 'email' => 'hello@example.org', 'phone' => '+1 (555) 123-4567'];
    $this->patch(route('sites.blocks.update', [$site, $block]), ['content' => $content])->assertRedirect();
    expect($block->fresh()->content)->toBe($content);

    foreach ([
        [['heading' => '', 'email' => 'invalid', 'phone' => '123'], 'content.email'],
        [['heading' => '', 'email' => 'a..b@example.org', 'phone' => '123'], 'content.email'],
        [['heading' => '', 'email' => '', 'phone' => '555;pause'], 'content.phone'],
        [['heading' => '', 'email' => '', 'phone' => "555\n1234"], 'content.phone'],
        [['heading' => '', 'email' => '', 'phone' => '()'], 'content.phone'],
    ] as [$invalid, $error]) {
        $this->patch(route('sites.blocks.update', [$site, $block]), ['content' => $invalid])
            ->assertSessionHasErrors($error);
        expect($block->fresh()->content)->toBe($content);
    }
});

test('empty church detail fields remain valid after clearing saved content', function () {
    $site = Site::factory()->create();
    $this->actingAs($site->user);

    foreach ([
        'hero' => [
            'heading' => '', 'body' => '', 'button_label' => '',
            'link_type' => 'none', 'target_block_id' => null, 'external_url' => '',
        ],
        'service_times' => ['heading' => '', 'entries' => []],
        'contact' => ['heading' => '', 'email' => '', 'phone' => ''],
    ] as $type => $content) {
        $block = $site->blocks()->create(['type' => $type, 'position' => $site->blocks()->count(), 'content' => $content]);
        $this->patch(route('sites.blocks.update', [$site, $block]), ['content' => $content])
            ->assertRedirect(route('sites.show', $site));
        expect($block->fresh()->content)->toBe($content);
    }
});

test('all six block types appear in page order after reordering', function () {
    $site = Site::factory()->create();
    $this->actingAs($site->user);

    foreach (['hero', 'about', 'plain_text', 'heading_text', 'service_times', 'contact'] as $type) {
        $this->post(route('sites.blocks.store', $site), ['type' => $type])->assertRedirect();
    }

    $current = $site->blocks()->orderBy('position')->pluck('id')->all();
    $this->patch(route('sites.blocks.order', $site), [
        'expected_order' => $current,
        'order' => array_reverse($current),
    ])->assertRedirect(route('sites.show', $site));

    $this->get(route('sites.pages.show', [$site, $site->homePage()->firstOrFail()]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sites/Show')
            ->has('blocks', 6)
            ->where('blocks.0.type', 'contact')
            ->where('blocks.5.type', 'hero'));
});

test('hero numeric string targets reload as integers and clear on deletion', function () {
    $site = Site::factory()->create();
    $target = SiteBlock::factory()->for($site)->create();
    $hero = $site->blocks()->create([
        'type' => 'hero', 'position' => 1,
        'content' => ['heading' => '', 'body' => '', 'button_label' => '', 'link_type' => 'none', 'target_block_id' => null, 'external_url' => ''],
    ]);
    $content = [
        'heading' => 'Welcome', 'body' => 'Join us', 'button_label' => 'See more',
        'link_type' => 'section', 'target_block_id' => (string) $target->id, 'external_url' => '',
    ];

    $this->actingAs($site->user)
        ->patch(route('sites.blocks.update', [$site, $hero]), ['content' => $content])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('sites.show', $site));

    expect($hero->fresh()->content)->toBe([...$content, 'target_block_id' => $target->id]);
    $this->get(route('sites.pages.show', [$site, $site->homePage()->firstOrFail()]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sites/Show')
            ->where('blocks.1.content.target_block_id', $target->id));

    $this->delete(route('sites.blocks.destroy', [$site, $target]))
        ->assertRedirect(route('sites.show', $site));
    expect($hero->fresh()->content)->toBe([
        ...$content, 'button_label' => '', 'link_type' => 'none', 'target_block_id' => null,
    ]);
});
