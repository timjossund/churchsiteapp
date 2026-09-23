<?php

use App\Models\Site;
use App\Models\SiteBlock;
use App\Models\User;

test('an owner can reorder all blocks and removal closes position gaps', function () {
    $site = Site::factory()->create();
    $first = SiteBlock::factory()->for($site)->create(['position' => 0]);
    $second = SiteBlock::factory()->for($site)->create(['position' => 1]);
    $third = SiteBlock::factory()->for($site)->create(['position' => 2]);

    $this->actingAs($site->user)
        ->patch(route('sites.blocks.order', $site), [
            'expected_order' => [$first->id, $second->id, $third->id],
            'order' => [$third->id, $first->id, $second->id],
        ])->assertRedirect(route('sites.show', $site));

    expect($site->blocks()->orderBy('position')->pluck('id')->all())->toBe([
        $third->id, $first->id, $second->id,
    ]);
    expect($site->blocks()->orderBy('position')->pluck('position')->all())->toBe([0, 1, 2]);

    $this->delete(route('sites.blocks.destroy', [$site, $first]))
        ->assertRedirect(route('sites.show', $site));

    $this->assertDatabaseMissing('site_blocks', ['id' => $first->id]);
    expect($site->blocks()->orderBy('position')->pluck('id')->all())->toBe([$third->id, $second->id]);
    expect($site->blocks()->orderBy('position')->pluck('position')->all())->toBe([0, 1]);

    $this->delete(route('sites.blocks.destroy', [$site, $third]))->assertRedirect();
    $this->delete(route('sites.blocks.destroy', [$site, $second]))->assertRedirect();

    expect($site->blocks()->count())->toBe(0);
    $this->patch(route('sites.blocks.order', $site), [
        'expected_order' => [],
        'order' => [],
    ])->assertRedirect(route('sites.show', $site));
});

test('invalid, foreign, and stale order requests leave every position intact', function () {
    $site = Site::factory()->create();
    $first = SiteBlock::factory()->for($site)->create(['position' => 0]);
    $second = SiteBlock::factory()->for($site)->create(['position' => 1]);
    $third = SiteBlock::factory()->for($site)->create(['position' => 2]);
    $foreign = SiteBlock::factory()->create();
    $current = [$first->id, $second->id, $third->id];
    $this->actingAs($site->user);

    foreach ([
        ['expected_order' => $current, 'order' => [$first->id, $second->id]],
        ['expected_order' => $current, 'order' => [$first->id, $first->id, $third->id]],
        ['expected_order' => $current, 'order' => [$first->id, $second->id, $foreign->id]],
        ['expected_order' => $current, 'order' => [10 => $third->id, 20 => $first->id, 30 => $second->id]],
        ['expected_order' => [10 => $first->id, 20 => $second->id, 30 => $third->id], 'order' => [$third->id, $first->id, $second->id]],
        ['expected_order' => [$second->id, $first->id, $third->id], 'order' => [$third->id, $first->id, $second->id]],
        ['order' => [$third->id, $first->id, $second->id]],
    ] as $payload) {
        $this->patch(route('sites.blocks.order', $site), $payload)
            ->assertSessionHasErrors();
        expect($site->blocks()->orderBy('position')->pluck('id')->all())->toBe($current);
        expect($site->blocks()->orderBy('position')->pluck('position')->all())->toBe([0, 1, 2]);
    }
});

test('guests and other owners cannot reorder or remove site blocks', function () {
    $site = Site::factory()->create();
    $block = SiteBlock::factory()->for($site)->create();
    $otherSite = Site::factory()->create();
    $otherBlock = SiteBlock::factory()->for($otherSite)->create();

    $this->patch(route('sites.blocks.order', $site), [
        'expected_order' => [$block->id],
        'order' => [$block->id],
    ])->assertRedirect(route('login'));
    $this->delete(route('sites.blocks.destroy', [$site, $block]))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->patch(route('sites.blocks.order', $site), [
            'expected_order' => [$block->id],
            'order' => [$block->id],
        ])->assertNotFound();
    $this->delete(route('sites.blocks.destroy', [$site, $block]))
        ->assertNotFound();

    $this->actingAs($site->user)
        ->delete(route('sites.blocks.destroy', [$site, $otherBlock]))
        ->assertNotFound();

    $this->assertDatabaseHas('site_blocks', ['id' => $block->id, 'position' => 0]);
    $this->assertDatabaseHas('site_blocks', ['id' => $otherBlock->id, 'position' => 0]);
});
