<?php

use App\Models\Site;
use App\Models\SiteBlock;
use App\Models\User;

test('a site keeps ordered blocks and JSON content separate from other sites', function () {
    $firstOwner = User::factory()->create();
    $secondOwner = User::factory()->create();
    $firstSite = Site::factory()->for($firstOwner)->create();
    $secondSite = Site::factory()->for($secondOwner)->create();

    $about = $firstSite->blocks()->create([
        'type' => 'about',
        'position' => 0,
        'content' => ['heading' => 'Our story', 'body' => "First line\nSecond line"],
    ]);
    $plainText = $firstSite->blocks()->create([
        'type' => 'plain_text',
        'position' => 1,
        'content' => ['body' => 'Welcome'],
    ]);
    $headingText = $firstSite->blocks()->create([
        'type' => 'heading_text',
        'position' => 2,
        'content' => ['heading' => 'Visit us', 'body' => 'Everyone is welcome'],
    ]);
    $secondSiteBlock = SiteBlock::factory()->for($secondSite)->create();

    expect($firstSite->blocks()->orderBy('position')->pluck('id')->all())->toBe([$about->id, $plainText->id, $headingText->id]);
    expect($secondSite->blocks()->pluck('id')->all())->toBe([$secondSiteBlock->id]);
    expect($about->fresh()->content)->toBe(['heading' => 'Our story', 'body' => "First line\nSecond line"]);
    expect($headingText->fresh()->content)->toBe(['heading' => 'Visit us', 'body' => 'Everyone is welcome']);
    expect($plainText->site->is($firstSite))->toBeTrue();
});

test('deleting a site removes its blocks without affecting another site', function () {
    $firstSite = Site::factory()->create();
    $secondSite = Site::factory()->create();
    $firstBlock = SiteBlock::factory()->for($firstSite)->create();
    $secondBlock = SiteBlock::factory()->for($secondSite)->create();

    $firstSite->delete();

    $this->assertDatabaseMissing('site_blocks', ['id' => $firstBlock->id]);
    $this->assertDatabaseHas('site_blocks', ['id' => $secondBlock->id]);
});
