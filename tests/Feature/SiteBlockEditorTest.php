<?php

use App\Models\Site;
use App\Models\SiteBlock;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an owned blank site opens with an empty block list', function () {
    $site = Site::factory()->create();

    $this->actingAs($site->user)
        ->get(route('sites.show', $site))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sites/Show')
            ->where('site.id', $site->id)
            ->has('blocks', 0));
});

test('an owner can append each text block type and reload them in order', function () {
    $site = Site::factory()->create();
    $this->actingAs($site->user);

    foreach (['about', 'plain_text', 'heading_text'] as $type) {
        $this->post(route('sites.blocks.store', $site), [
            'type' => $type,
        ])->assertRedirect(route('sites.show', $site));
    }

    $blocks = $site->blocks()->orderBy('position')->get();

    expect($blocks->pluck('type')->all())->toBe(['about', 'plain_text', 'heading_text']);
    expect($blocks->pluck('position')->all())->toBe([0, 1, 2]);
    expect($blocks[0]->content)->toBe(['heading' => '', 'body' => '']);
    expect($blocks[1]->content)->toBe(['body' => '']);
    expect($blocks[2]->content)->toBe(['heading' => '', 'body' => '']);

    $this->get(route('sites.show', $site))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sites/Show')
            ->has('blocks', 3)
            ->where('blocks.0.id', $blocks[0]->id)
            ->where('blocks.1.type', 'plain_text')
            ->where('blocks.2.position', 2));
});

test('invalid block types do not create a block', function ($type) {
    $site = Site::factory()->create();

    $this->actingAs($site->user)
        ->post(route('sites.blocks.store', $site), ['type' => $type])
        ->assertSessionHasErrors('type');

    $this->assertDatabaseCount('site_blocks', 0);
})->with(['unknown', '', 123]);

test('guests and other owners cannot read or add site blocks', function () {
    $site = Site::factory()->create();

    $this->post(route('sites.blocks.store', $site), ['type' => 'about'])
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('sites.show', $site))
        ->assertNotFound();
    $this->post(route('sites.blocks.store', $site), ['type' => 'about'])
        ->assertNotFound();
    $this->post(route('sites.blocks.store', $site), ['type' => 'invalid'])
        ->assertNotFound();

    $this->assertDatabaseCount('site_blocks', 0);
});

test('an owner can save the exact content shape for each text block', function () {
    $site = Site::factory()->create();
    $about = SiteBlock::factory()->for($site)->create([
        'type' => 'about',
        'content' => ['heading' => '', 'body' => ''],
    ]);
    $plainText = SiteBlock::factory()->for($site)->create(['position' => 1]);
    $headingText = SiteBlock::factory()->for($site)->create([
        'type' => 'heading_text',
        'position' => 2,
        'content' => ['heading' => '', 'body' => ''],
    ]);

    $this->actingAs($site->user)
        ->patch(route('sites.blocks.update', [$site, $about]), [
            'content' => ['heading' => 'Our story', 'body' => "First line\nSecond line"],
        ])->assertRedirect(route('sites.show', $site));
    $this->patch(route('sites.blocks.update', [$site, $plainText]), [
        'content' => ['body' => 'Welcome'],
    ])->assertRedirect(route('sites.show', $site));
    $this->patch(route('sites.blocks.update', [$site, $headingText]), [
        'content' => ['heading' => 'Visit us', 'body' => 'We meet on Sunday'],
    ])->assertRedirect(route('sites.show', $site));

    expect($about->fresh()->content)->toBe(['heading' => 'Our story', 'body' => "First line\nSecond line"]);
    expect($plainText->fresh()->content)->toBe(['body' => 'Welcome']);
    expect($headingText->fresh()->content)->toBe(['heading' => 'Visit us', 'body' => 'We meet on Sunday']);

    $this->patch(route('sites.blocks.update', [$site, $about]), [
        'content' => ['heading' => '', 'body' => ''],
    ])->assertRedirect(route('sites.show', $site));
    expect($about->fresh()->content)->toBe(['heading' => '', 'body' => '']);
});

test('invalid or extra content fields cannot change a saved block', function ($content, $extra = []) {
    $site = Site::factory()->create();
    $block = SiteBlock::factory()->for($site)->create([
        'content' => ['body' => 'Saved text'],
    ]);

    $this->actingAs($site->user)
        ->patch(route('sites.blocks.update', [$site, $block]), [
            'content' => $content,
            ...$extra,
        ])->assertSessionHasErrors();

    expect($block->fresh()->content)->toBe(['body' => 'Saved text']);
})->with([
    'missing body' => [[]],
    'number body' => [['body' => 42]],
    'null body' => [['body' => null]],
    'extra heading' => [['body' => 'Changed', 'heading' => 'No']],
    'extra field' => [['body' => 'Changed', 'script' => '<script>']],
    'type change' => [['body' => 'Changed'], ['type' => 'about']],
    'position change' => [['body' => 'Changed'], ['position' => 99]],
]);

test('guests and foreign site or block IDs cannot edit content', function () {
    $ownerSite = Site::factory()->create();
    $otherSite = Site::factory()->create();
    $block = SiteBlock::factory()->for($ownerSite)->create();
    $otherBlock = SiteBlock::factory()->for($otherSite)->create();

    $this->patch(route('sites.blocks.update', [$ownerSite, $block]), [
        'content' => ['body' => 'Guest change'],
    ])->assertRedirect(route('login'));

    $this->actingAs($ownerSite->user)
        ->patch(route('sites.blocks.update', [$ownerSite, $otherBlock]), [
            'content' => ['body' => 'Cross-site change'],
        ])->assertNotFound();

    $this->actingAs($otherSite->user)
        ->patch(route('sites.blocks.update', [$ownerSite, $block]), [
            'content' => ['body' => 'Cross-owner change'],
        ])->assertNotFound();

    expect($block->fresh()->content)->toBe(['body' => '']);
    expect($otherBlock->fresh()->content)->toBe(['body' => '']);
});
