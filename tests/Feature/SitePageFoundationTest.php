<?php

use App\Actions\BuildSitePublicationSnapshot;
use App\Models\Site;
use App\Models\SiteBlock;
use App\Models\SitePage;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('new sites and factory sites each have exactly one blank Home', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner)->post(route('sites.store'), ['name' => 'New Church'])->assertRedirect();
    $created = $owner->sites()->firstOrFail();
    $factorySite = Site::factory()->create();

    foreach ([$created, $factorySite] as $site) {
        expect($site->pages()->count())->toBe(1);
        $home = $site->homePage()->firstOrFail();
        expect($home->name)->toBe('Home')
            ->and($home->is_home)->toBeTrue()
            ->and($home->position)->toBe(0)
            ->and($home->site->is($site))->toBeTrue()
            ->and($home->blocks()->count())->toBe(0);
        $site->update(['name' => 'Renamed Church']);
        expect($site->pages()->count())->toBe(1);
    }
});

test('a failed Home creation rolls back the new site', function () {
    $owner = User::factory()->create();
    SitePage::creating(fn () => throw new RuntimeException('Simulated Home creation failure.'));

    $this->actingAs($owner)->post(route('sites.store'), ['name' => 'New Church'])->assertServerError();

    $this->assertDatabaseCount('sites', 0);
    $this->assertDatabaseCount('site_pages', 0);
});

test('block factories and existing creation assign Home while explicit page creation preserves its site', function () {
    $site = Site::factory()->create();
    $home = $site->homePage()->firstOrFail();
    $factoryBlock = SiteBlock::factory()->for($site)->create();
    $standaloneBlock = SiteBlock::factory()->create();
    $block = $site->blocks()->create(['type' => 'plain_text', 'position' => 1, 'content' => ['body' => 'Home copy']]);
    $page = $site->pages()->create(['name' => 'About', 'position' => 1]);
    $pageBlock = $page->blocks()->create(['type' => 'plain_text', 'position' => 0, 'content' => ['body' => 'About copy']]);

    expect($factoryBlock->page_id)->toBe($home->id)
        ->and($block->page->is($home))->toBeTrue()
        ->and($standaloneBlock->page_id)->toBe($standaloneBlock->site->homePage()->firstOrFail()->id)
        ->and($pageBlock->site_id)->toBe($site->id)
        ->and($pageBlock->page_id)->toBe($page->id);
});

test('block creation rejects mismatched site and page relationships', function () {
    $site = Site::factory()->create();
    $other = Site::factory()->create();

    expect(fn () => SiteBlock::factory()->for($site)->create([
        'page_id' => $other->homePage()->firstOrFail()->id,
    ]))->toThrow(LogicException::class);

    $this->assertDatabaseCount('site_blocks', 0);
});

test('page backfill preserves populated and blank legacy sites and their publication bytes', function () {
    $site = Site::factory()->create(['slug' => 'legacy-church', 'theme_key' => 'bold']);
    $blank = Site::factory()->create();
    $asset = $site->mediaAssets()->create([
        'storage_key' => "sites/{$site->id}/legacy-image",
        'mime_type' => 'image/png',
        'alt_text' => 'A church',
    ]);
    $about = SiteBlock::factory()->for($site)->create([
        'type' => 'text_image', 'position' => 4,
        'content' => ['heading' => 'About', 'body' => 'Original copy', 'media_asset_id' => $asset->id, 'image_side' => 'right'],
    ]);
    SiteBlock::factory()->for($site)->create([
        'type' => 'hero', 'position' => 2,
        'content' => ['heading' => 'Welcome', 'body' => '', 'button_label' => 'About', 'link_type' => 'section', 'target_block_id' => $about->id, 'external_url' => ''],
    ]);
    $snapshotBuilder = app(BuildSitePublicationSnapshot::class);
    $snapshot = $snapshotBuilder($site);
    $fingerprint = $snapshotBuilder->fingerprint($snapshot);
    $snapshot['draft_fingerprint'] = $fingerprint;
    $site->update(['published_snapshot' => $snapshot, 'published_at' => now()->subDay()]);

    // Roll back only the new schema in the isolated test database to reproduce the legacy shape.
    $migration = require database_path('migrations/2026_09_26_000001_create_site_pages_table.php');
    $migration->down();
    $originalSites = DB::table('sites')->orderBy('id')->get()->toJson();
    $originalBlocks = DB::table('site_blocks')->orderBy('id')->get()->map(fn ($block) => (array) $block)->all();
    $originalMedia = DB::table('media_assets')->get()->toJson();

    $migration->up();

    expect(DB::table('sites')->orderBy('id')->get()->toJson())->toBe($originalSites)
        ->and(DB::table('media_assets')->get()->toJson())->toBe($originalMedia)
        ->and($site->pages()->count())->toBe(1)
        ->and($blank->pages()->count())->toBe(1)
        ->and($blank->homePage()->firstOrFail()->blocks()->count())->toBe(0);
    $home = $site->homePage()->firstOrFail();
    foreach ($originalBlocks as $original) {
        $current = (array) DB::table('site_blocks')->find($original['id']);
        expect($current['page_id'])->toBe($home->id);
        unset($current['page_id']);
        expect($current)->toBe($original);
    }
    expect($snapshotBuilder->fingerprint($snapshotBuilder($site->fresh())))->toBe($fingerprint);
    $this->get(route('sites.published.show', $site->slug))->assertOk()->assertSee('Original copy');
});

test('the block page foreign key remains required and enforced after backfill', function () {
    $site = Site::factory()->create();
    $attributes = ['site_id' => $site->id, 'type' => 'plain_text', 'position' => 0, 'content' => '{}'];

    expect(fn () => DB::table('site_blocks')->insert($attributes))
        ->toThrow(QueryException::class);
    expect(fn () => DB::table('site_blocks')->insert($attributes + ['page_id' => 999999]))
        ->toThrow(QueryException::class);
});

test('Home editor and publication exclude other draft pages and their images', function () {
    $site = Site::factory()->create(['slug' => 'home-only']);
    $homeBlock = SiteBlock::factory()->for($site)->create(['content' => ['body' => 'Public home copy']]);
    $this->actingAs($site->user)->post(route('sites.publish', $site))->assertRedirect();
    $original = $site->fresh()->published_snapshot;
    $page = $site->pages()->create(['name' => 'Private draft', 'position' => 1]);
    $asset = $site->mediaAssets()->create([
        'storage_key' => "sites/{$site->id}/draft-only-image", 'mime_type' => 'image/png', 'alt_text' => 'Private draft image',
    ]);
    $page->blocks()->create(['type' => 'image', 'position' => 0, 'content' => ['media_asset_id' => $asset->id]]);
    $page->blocks()->create(['type' => 'plain_text', 'position' => 1, 'content' => ['body' => 'Private page copy']]);

    $this->get(route('sites.pages.show', [$site, $site->homePage()->firstOrFail()]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('blocks', 1)
        ->where('blocks.0.id', $homeBlock->id)
        ->where('site.has_unpublished_changes', false));
    expect($site->fresh()->published_snapshot)->toBe($original);
    $this->post(route('sites.publish', $site))->assertRedirect();
    expect($site->fresh()->published_snapshot)->toBe($original);
    $this->get(route('sites.published.show', $site->slug))->assertOk()
        ->assertSee('Public home copy')->assertDontSee('Private page copy');
    $this->get(route('sites.published.media.show', [$site->slug, $asset->id]))->assertNotFound();
});

test('deleting a site removes its pages and blocks while preserving another site', function () {
    $site = Site::factory()->create();
    $other = Site::factory()->create();
    $block = SiteBlock::factory()->for($site)->create();
    $otherBlock = SiteBlock::factory()->for($other)->create();
    $homeId = $block->page_id;

    $site->delete();

    $this->assertDatabaseMissing('site_pages', ['id' => $homeId]);
    $this->assertDatabaseMissing('site_blocks', ['id' => $block->id]);
    $this->assertDatabaseHas('site_pages', ['id' => $otherBlock->page_id]);
    $this->assertDatabaseHas('site_blocks', ['id' => $otherBlock->id]);
});
